<?php

namespace Oro\Bundle\DashboardBundle\Model;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\DashboardBundle\Entity\ActiveDashboard;
use Oro\Bundle\DashboardBundle\Entity\Dashboard;
use Oro\Bundle\DashboardBundle\Entity\Widget;
use Oro\Bundle\SecurityBundle\Authentication\Token\OrganizationAwareTokenInterface;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Manages dashboards.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class Manager
{
    /**
     * @var Factory
     */
    protected $factory;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * Constructor
     *
     * @param Factory               $factory
     * @param EntityManager         $entityManager
     * @param AclHelper             $aclHelper
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(
        Factory $factory,
        EntityManager $entityManager,
        AclHelper $aclHelper,
        TokenStorageInterface $tokenStorage
    ) {
        $this->factory = $factory;
        $this->entityManager = $entityManager;
        $this->aclHelper = $aclHelper;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Find dashboard model by id
     *
     * @param integer $id
     *
     * @return DashboardModel|null
     */
    public function findDashboardModel($id)
    {
        $entity = $this->entityManager->getRepository('OroDashboardBundle:Dashboard')->find($id);

        if ($entity) {
            return $this->getDashboardModel($entity);
        }

        return null;
    }

    /**
     * Find dashboard model by criteria
     *
     * @param array      $criteria
     * @param array|null $orderBy
     *
     * @return DashboardModel|null
     */
    public function findOneDashboardModelBy(array $criteria, array $orderBy = null)
    {
        $entity = $this->entityManager->getRepository('OroDashboardBundle:Dashboard')
            ->findOneBy($criteria, $orderBy);

        if ($entity) {
            return $this->getDashboardModel($entity);
        }

        return null;
    }

    /**
     * Find dashboard widget model by id
     *
     * @param integer $id
     *
     * @return WidgetModel|null
     */
    public function findWidgetModel($id)
    {
        $entity = $this->entityManager->getRepository('OroDashboardBundle:Widget')->find($id);

        if ($entity) {
            return $this->getWidgetModel($entity);
        }

        return null;
    }

    /**
     * Get dashboard
     *
     * @param Dashboard $entity
     *
     * @return DashboardModel
     */
    public function getDashboardModel(Dashboard $entity)
    {
        return $this->factory->createDashboardModel($entity);
    }

    /**
     * Get dashboard widget
     *
     * @param Widget $entity
     *
     * @return WidgetModel
     */
    public function getWidgetModel(Widget $entity)
    {
        return $this->factory->createWidgetModel($entity);
    }

    /**
     * Get all dashboards
     *
     * @param array $entities
     *
     * @return DashboardModel[]
     */
    public function getDashboardModels(array $entities)
    {
        $result = [];
        foreach ($entities as $entity) {
            $result[] = $this->getDashboardModel($entity);
        }

        return $result;
    }

    /**
     * Create dashboard
     *
     * @return DashboardModel
     */
    public function createDashboardModel()
    {
        $dashboard = new Dashboard();
        $token = $this->tokenStorage->getToken();
        if ($token instanceof OrganizationAwareTokenInterface) {
            $dashboard->setOrganization($token->getOrganization());
        }

        return $this->getDashboardModel($dashboard);
    }

    /**
     * Create dashboard widget
     *
     * @param string $widgetName
     *
     * @return WidgetModel
     */
    public function createWidgetModel($widgetName)
    {
        $widget = new Widget();

        $widget->setLayoutPosition([0, 0]);
        $widget->setName($widgetName);

        return $this->getWidgetModel($widget);
    }

    /**
     * @param EntityModelInterface $entityModel
     * @param boolean              $flush
     */
    public function save(EntityModelInterface $entityModel, $flush = false)
    {
        if ($entityModel instanceof DashboardModel && $entityModel->getStartDashboard() && !$entityModel->getId()) {
            $this->copyWidgets($entityModel, $entityModel->getStartDashboard());
        }

        $this->entityManager->persist($entityModel->getEntity());

        if ($flush) {
            $this->entityManager->flush($entityModel->getEntity());
        }
    }

    /**
     * @param EntityModelInterface $entityModel
     */
    public function remove(EntityModelInterface $entityModel)
    {
        $this->entityManager->remove($entityModel->getEntity());
    }

    /**
     * Find active dashboard or default dashboard
     *
     * @param User|null $user
     *
     * @return DashboardModel|null
     */
    public function findUserActiveOrDefaultDashboard(?User $user)
    {
        // No user, no way to determine dashboard. Even findDefaultDashboard requires proper user token.
        if ($user === null) {
            return null;
        }
        
        $activeDashboard = $this->findUserActiveDashboard($user);
        return $activeDashboard ? $activeDashboard : $this->findDefaultDashboard();
    }

    /**
     * Find active dashboard
     *
     * @param User $user
     *
     * @return DashboardModel|null
     */
    public function findUserActiveDashboard(User $user)
    {
        /** @var OrganizationAwareTokenInterface $token */
        $token = $this->tokenStorage->getToken();
        $organization = $token->getOrganization();
        $dashboard = $this->entityManager->getRepository('OroDashboardBundle:ActiveDashboard')
            ->findOneBy(array('user' => $user, 'organization' => $organization));

        if ($dashboard) {
            return $this->getDashboardModel($dashboard->getDashboard());
        }

        return null;
    }

    /**
     * Find default dashboard
     *
     * @return DashboardModel|null
     */
    public function findDefaultDashboard()
    {
        /** @var OrganizationAwareTokenInterface $token */
        $token = $this->tokenStorage->getToken();
        $organization = $token->getOrganization();
        $dashboard = $this->entityManager->getRepository('OroDashboardBundle:Dashboard')
            ->findDefaultDashboard($organization);

        if ($dashboard) {
            return $this->getDashboardModel($dashboard);
        }

        return null;
    }

    /**
     * @param string $permission
     * @param int $organizationId
     *
     * @return DashboardModel[]
     */
    public function findAllowedDashboards($permission = 'VIEW', $organizationId = null)
    {
        $qb = $this->entityManager->getRepository('OroDashboardBundle:Dashboard')->createQueryBuilder('dashboard');
        if ($organizationId) {
            $qb->andWhere($qb->expr()->eq('dashboard.organization', ':organizationId'))
                ->setParameter('organizationId', $organizationId);
        }
        return $this->getDashboardModels($this->aclHelper->apply($qb, $permission)->execute());
    }

    /**
     * @param string $permission
     * @param int $organizationId
     *
     * @return array
     */
    public function findAllowedDashboardsShortenedInfo($permission = 'VIEW', $organizationId = null)
    {
        $qb = $this->entityManager->getRepository('OroDashboardBundle:Dashboard')
            ->createQueryBuilder('dashboard')
            ->select('dashboard.id, dashboard.label');
        if ($organizationId) {
            $qb->andWhere($qb->expr()->eq('dashboard.organization', ':organizationId'))
                ->setParameter('organizationId', $organizationId);
        }
        return $this->aclHelper->apply($qb, $permission)->execute();
    }

    /**
     * Set current dashboard as active for passed user
     *
     * @param DashboardModel $dashboard
     * @param User           $user
     * @param bool           $flush
     *
     * @return bool
     */
    public function setUserActiveDashboard(DashboardModel $dashboard, User $user, $flush = false)
    {
        /** @var OrganizationAwareTokenInterface $token */
        $token = $this->tokenStorage->getToken();
        $organization = $token->getOrganization();
        $activeDashboard = $this->entityManager
            ->getRepository('OroDashboardBundle:ActiveDashboard')
            ->findOneBy(array('user' => $user, 'organization' => $organization));

        if (!$activeDashboard) {
            $activeDashboard = new ActiveDashboard();

            $activeDashboard->setUser($user);
            $activeDashboard->setOrganization($organization);
            $this->entityManager->persist($activeDashboard);
        }

        $entity = $dashboard->getEntity();
        $activeDashboard->setDashboard($entity);

        if ($flush) {
            $this->entityManager->flush($activeDashboard);
        }
    }

    /**
     * Copy widgets from source entity to dashboard model
     *
     * @param DashboardModel $target
     * @param Dashboard      $source
     */
    protected function copyWidgets(DashboardModel $target, Dashboard $source)
    {
        foreach ($source->getWidgets() as $sourceWidget) {
            $widgetModel = $this->copyWidgetModel($sourceWidget);
            $this->save($widgetModel, false);
            $target->addWidget($widgetModel);
        }
    }

    /**
     * Copy widget model by entity
     *
     * @param Widget $sourceWidget
     *
     * @return WidgetModel
     */
    protected function copyWidgetModel(Widget $sourceWidget)
    {
        $widget = new Widget();

        $widget->setLayoutPosition($sourceWidget->getLayoutPosition());
        $widget->setName($sourceWidget->getName());
        $widget->setOptions($sourceWidget->getOptions());

        return $this->getWidgetModel($widget);
    }
}
