<?php

namespace Oro\Bundle\EmailBundle\Datagrid;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;

use Symfony\Component\Form\FormFactoryInterface;

use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\EmailBundle\Entity\Manager\MailboxManager;
use Oro\Bundle\EmailBundle\Entity\Provider\EmailOwnerProviderStorage;
use Oro\Bundle\EmailBundle\Filter\EmailStringFilter;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\FilterBundle\Filter\FilterUtility;
use Oro\Bundle\FilterBundle\Datasource\Orm\OrmFilterDatasourceAdapter;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\SecurityFacade;

class EmailQueryFactory
{
    /** @var EmailOwnerProviderStorage */
    protected $emailOwnerProviderStorage;

    /** @var EntityNameResolver */
    protected $entityNameResolver;

    /** @var string */
    protected $fromEmailExpression;

    /** @var Registry */
    protected $mailboxManager;

    /** @var SecurityFacade */
    protected $securityFacade;

    /** @var FormFactoryInterface */
    protected $formFactory;

    /** @var FilterUtility */
    protected $filterUtil;

    /**
     * @param EmailOwnerProviderStorage $emailOwnerProviderStorage
     * @param EntityNameResolver        $entityNameResolver
     * @param MailboxManager            $mailboxManager
     * @param SecurityFacade            $securityFacade
     * @param FormFactoryInterface      $formFactory
     * @param FilterUtility             $filterUtil
     */
    public function __construct(
        EmailOwnerProviderStorage $emailOwnerProviderStorage,
        EntityNameResolver $entityNameResolver,
        MailboxManager $mailboxManager,
        SecurityFacade $securityFacade,
        FormFactoryInterface $formFactory,
        FilterUtility $filterUtil
    ) {
        $this->emailOwnerProviderStorage = $emailOwnerProviderStorage;
        $this->entityNameResolver        = $entityNameResolver;
        $this->mailboxManager            = $mailboxManager;
        $this->securityFacade            = $securityFacade;
        $this->formFactory               = $formFactory;
        $this->filterUtil                = $filterUtil;
    }

    /**
     * @param QueryBuilder $qb                  Source query builder
     * @param string       $emailFromTableAlias EmailAddress table alias of joined Email#fromEmailAddress association
     */
    public function prepareQuery(QueryBuilder $qb, $emailFromTableAlias = 'a')
    {
        $qb->addSelect($this->getFromEmailExpression($emailFromTableAlias));
        foreach ($this->emailOwnerProviderStorage->getProviders() as $provider) {
            $fieldName = $this->emailOwnerProviderStorage->getEmailOwnerFieldName($provider);

            $qb->leftJoin(sprintf('%s.%s', $emailFromTableAlias, $fieldName), $fieldName);
        }
    }

    /**
     * Apply custom ACL checks
     *
     * @param QueryBuilder $qb
     */
    public function applyAcl(QueryBuilder $qb)
    {
        $exprs = [$qb->expr()->eq('eu.owner', ':owner')];

        $organization = $this->getOrganization();
        if ($organization) {
            $exprs[] = $qb->expr()->eq('eu.organization ', ':organization');
            $qb->setParameter('organization', $organization->getId());
        }
        $uoCheck = call_user_func_array([$qb->expr(), 'andX'], $exprs);

        $mailboxIds = $this->getAvailableMailboxIds();
        if (!empty($mailboxIds)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $uoCheck,
                    $qb->expr()->in('eu.mailboxOwner', ':mailboxIds')
                )
            );
            $qb->setParameter('mailboxIds', $mailboxIds);
        } else {
            $qb->andWhere($uoCheck);
        }

        $qb->setParameter('owner', $this->securityFacade->getLoggedUserId());
    }

    /**
     * Apply custom ACL checks in case of threaded emails view enabled.
     * Adds additional WHERE condition:
     *
     * o0_.id IN (
     *  SELECT max(u.id)
     *  FROM OroEmailBundle:EmailUser as u
     *  INNER JOIN OroEmailBundle:Email as m on u.email_id = m.id
     *  WHERE
     *    m.thread_id is not null
     *    AND (
     *      (
     *        u.user_owner_id = {owner_id}
     *        AND u.organization_id = {organization_id}
     *      )
     *      OR u.mailbox_owner_id IN ( {allowed_mailboxes_ids} )
     *    )
     *  GROUP BY m.thread_id
     * )
     * OR (o3_.is_head = 1 AND o3_.thread_id is null)
     *
     * @param QueryBuilder           $qb
     * @param DatagridInterface|null $datagrid
     * @param array                  $filters
     */
    public function applyAclThreadsGrouping(
        QueryBuilder $qb,
        $datagrid = null,
        $filters = []
    ) {
        $innerQb = $qb->getEntityManager()->createQueryBuilder();
        $innerQb
            ->select('MAX(u.id)')
            ->from('OroEmailBundle:EmailUser', 'u')
            ->innerJoin('OroEmailBundle:Email', 'm', 'WITH', 'u.email = m.id')
            ->where(
                $innerQb->expr()->andX(
                    $innerQb->expr()->isNotNull('m.thread'),
                    $this->getOwningExpression($innerQb->expr(), 'u')
                )
            )
            ->groupBy('m.thread');

        $exprs = [
            $qb->expr()->isNull('e.thread'),
            $qb->expr()->eq('e.head', 'TRUE')
        ];

        $threadedExpressions = null;
        $threadedExpressionsParameters = null;
        if ($datagrid && $filters) {
            list (
                $threadedExpressions,
                $threadedExpressionsParameters
            ) = $this->prepareSearchFilters($datagrid, $filters, 'mm');
        }

        if ($threadedExpressions) {
            $filterQb = $qb->getEntityManager()->createQueryBuilder();
            $filterExpressions = call_user_func_array([$filterQb->expr(), 'andX'], $threadedExpressions);
            $filterQb
                ->select('IDENTITY(mm.thread)')
                ->from('OroEmailBundle:EmailUser', 'uu')
                ->innerJoin('OroEmailBundle:Email', 'mm', 'WITH', 'uu.email = mm.id')
                ->where($filterExpressions);

            list (
                $notThreadedExpressions,
                $notThreadedExpressionsParameters
            ) = $this->prepareSearchFilters($datagrid, $filters, 'e');

            $expression = call_user_func_array(
                [$qb->expr(), 'andX'],
                array_merge($exprs, $notThreadedExpressions)
            );

            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->andX(
                        $qb->expr()->in('eu.id', $innerQb->getDQL()),
                        $qb->expr()->in('e.thread', $filterQb->getDQL())
                    ),
                    $expression
                )
            );

            /** @var Parameter[] $params */
            $params = array_merge($threadedExpressionsParameters, $notThreadedExpressionsParameters);
            foreach ($params as $param) {
                $qb->setParameter($param->getName(), $param->getValue(), $param->getType());
            }
        } else {
            $expression = call_user_func_array([$qb->expr(), 'andX'], $exprs);
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->in('eu.id', $innerQb->getDQL()),
                    $expression
                )
            );
        }
    }

    /**
     * In case of threaded emails view enabled, adds email counting (SELECT COUNT) expression.
     *
     * SELECT COUNT(eu.id)
     *   FROM oro_email_user AS eu
     *   INNER JOIN oro_email e ON (eu.email_id = e.id)
     *   WHERE
     *     e.thread_id = o3_.thread_id
     *     AND (
     *       (
     *         eu.user_owner_id = {owner_id}
     *         AND eu.organization_id = {organization_id}
     *       )
     *       OR eu.mailbox_owner_id IN ( {allowed_mailboxes_ids} )
     *    )
     *    AND o3_.thread_id IS NOT NULL -- `o3_` is alias for `oro_email` table from base query
     * as thread_email_count
     *
     * @param QueryBuilder $qb
     * @param bool         $isThreadGroupingEnabled
     */
    public function addEmailsCount(QueryBuilder $qb, $isThreadGroupingEnabled)
    {
        // in case threading view is disabled the default value for counting is `0`
        $selectExpression = '0 AS thread_email_count';

        if ($isThreadGroupingEnabled) {
            $innerQb = $qb->getEntityManager()->createQueryBuilder();
            $innerQb
                ->select('COUNT(emailUser.id)')
                ->from('OroEmailBundle:EmailUser', 'emailUser')
                ->innerJoin('OroEmailBundle:Email', 'email', 'WITH', 'emailUser.email = email.id')
                ->where(
                    $innerQb->expr()->andX(
                        $innerQb->expr()->isNotNull('e.thread'),
                        $innerQb->expr()->eq('email.thread', 'e.thread'),
                        $this->getOwningExpression($innerQb->expr(), 'emailUser')
                    )
                );

            $selectExpression = '(' . $innerQb->getDQL() . ') AS thread_email_count';
        }

        $qb->addSelect($selectExpression);
    }

    /**
     * @param DatagridInterface $datagrid
     * @param array             $filters
     * @param string            $alias
     *
     * @return array
     */
    protected function prepareSearchFilters($datagrid, $filters, $alias = '')
    {
        $searchExpressions = null;
        $searchExpressionsParameters = null;
        $filterColumnsMap = [
            'subject' => 'subject',
            'from'    => 'fromName',
        ];
        $searchFilters = array_intersect_key(
            $filters,
            array_flip(array_keys($filterColumnsMap))
        );

        if ($searchFilters) {
            /** @var OrmDatasource $ormDataSource */
            $ormDataSource = $datagrid->getDatasource();
            $queryBuilder = clone $ormDataSource->getQueryBuilder();

            $parameters = $datagrid->getParameters();

            $datagridConfig = $datagrid->getConfig();
            $filterTypes = $datagridConfig->offsetGetByPath('[filters][columns]');

            foreach ($searchFilters as $columnName => $filterData) {
                $filterConfig = $filterTypes[$columnName];
                $filterConfig['data_name'] = $alias
                    ? sprintf('%s.%s', $alias, $filterColumnsMap[$columnName])
                    : $filterConfig['data_name'];

                $datasourceAdapter = new OrmFilterDatasourceAdapter($queryBuilder);
                $mFilter = new EmailStringFilter($this->formFactory, $this->filterUtil);
                $mFilter->init($columnName, $filterConfig);
                $mFilter->apply($datasourceAdapter, $filterData);

                $searchExpressions = $mFilter->getExpression();
                $searchExpressionsParameters = $mFilter->getParameters();

                unset($filters[$columnName]);
                $parameters->set('_filter', $filters);
            }
        }

        if (null !== $searchExpressions && !is_array($searchExpressions)) {
            $searchExpressions = [$searchExpressions];
        }

        return [
            $searchExpressions,
            $searchExpressionsParameters
        ];
    }

    /**
     * Builds owning expression part, being used in case of threaded emails view enabled.
     *
     *  (
     *    eu.user_owner_id = {owner_id}
     *    AND eu.organization_id = {organization_id}
     *  )
     *  OR eu.mailbox_owner_id IN ( {allowed_mailboxes_ids} )
     *
     * @param Expr   $expr
     * @param string $tableAlias
     *
     * @return Expr\Andx|Expr\Comparison|Expr\Orx
     */
    protected function getOwningExpression($expr, $tableAlias)
    {
        $user         = $this->securityFacade->getLoggedUser();
        $organization = $this->getOrganization();

        if ($organization === null) {
            $ownerExpression =
                $expr->eq($tableAlias . '.owner', $user->getId());
        } else {
            $ownerExpression = $expr->andX(
                $expr->eq($tableAlias . '.owner', $user->getId()),
                $expr->eq($tableAlias . '.organization', $organization->getId())
            );
        }

        $availableMailboxIds = $this->getAvailableMailboxIds();
        if ($availableMailboxIds) {
            return $expr->orX(
                $ownerExpression,
                $expr->in($tableAlias . '.mailboxOwner', $this->getAvailableMailboxIds())
            );
        } else {
            return $ownerExpression;
        }
    }

    /**
     * @return array
     */
    protected function getAvailableMailboxIds()
    {
        return $this->mailboxManager->findAvailableMailboxIds(
            $this->securityFacade->getLoggedUser(),
            $this->getOrganization()
        );
    }

    /**
     * @return Organization|null
     */
    protected function getOrganization()
    {
        return $this->securityFacade->getOrganization();
    }

    /**
     * @param string $emailFromTableAlias EmailAddress table alias of joined Email#fromEmailAddress association
     *
     * @return string
     */
    protected function getFromEmailExpression($emailFromTableAlias)
    {
        $providers = $this->emailOwnerProviderStorage->getProviders();
        if (empty($providers)) {
            return sprintf('%s.email', $emailFromTableAlias);
        }

        $expressionsByOwner = [];
        foreach ($providers as $provider) {
            $relationAlias                      = $this->emailOwnerProviderStorage->getEmailOwnerFieldName($provider);
            $expressionsByOwner[$relationAlias] = $this->entityNameResolver->getNameDQL(
                $provider->getEmailOwnerClass(),
                $relationAlias
            );
        }

        $expression = '';
        foreach ($expressionsByOwner as $alias => $expressionPart) {
            $expression .= sprintf('WHEN %s.%s IS NOT NULL THEN %s ', $emailFromTableAlias, $alias, $expressionPart);
        }
        $expression = sprintf('CASE %sELSE \'\' END', $expression);

        // if has owner then use expression to expose formatted name, use email otherwise
        return sprintf(
            'CONCAT(\'\', CASE WHEN %1$s.hasOwner = true THEN (%2$s) ELSE %1$s.email END) as fromEmailExpression',
            $emailFromTableAlias,
            $expression
        );
    }
}
