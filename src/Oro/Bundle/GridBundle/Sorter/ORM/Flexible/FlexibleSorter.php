<?php

namespace Oro\Bundle\GridBundle\Sorter\ORM\Flexible;

use Oro\Bundle\FlexibleEntityBundle\Entity\Repository\FlexibleEntityRepository;
use Oro\Bundle\FlexibleEntityBundle\Manager\FlexibleManager;
use Oro\Bundle\FlexibleEntityBundle\Manager\FlexibleManagerRegistry;

use Oro\Bundle\GridBundle\Datagrid\ProxyQueryInterface;
use Oro\Bundle\GridBundle\Field\FieldDescriptionInterface;
use Oro\Bundle\GridBundle\Sorter\ORM\Sorter;

class FlexibleSorter extends Sorter
{
    /**
     * @var FlexibleManagerRegistry
     */
    protected $flexibleRegistry;

    /**
     * @var FlexibleManager
     */
    protected $flexibleManager;

    /**
     * @param FlexibleManagerRegistry $flexibleRegistry
     */
    public function __construct(FlexibleManagerRegistry $flexibleRegistry)
    {
        $this->flexibleRegistry = $flexibleRegistry;
    }

    /**
     * @param FieldDescriptionInterface $field
     * @param string $direction
     * @throws \LogicException
     */
    public function initialize(FieldDescriptionInterface $field, $direction = null)
    {
        parent::initialize($field, $direction);

        $flexibleEntityName = $field->getOption('flexible_name');
        if (!$flexibleEntityName) {
            throw new \LogicException('Flexible entity sorter must have flexible entity name.');
        }

        $this->flexibleManager = $this->flexibleRegistry->getManager($flexibleEntityName);
    }

    /**
     * @param ProxyQueryInterface $queryInterface
     * @param string|null $direction
     */
    public function apply(ProxyQueryInterface $queryInterface, $direction = null)
    {
        $this->setDirection($direction);
        $queryBuilder = $queryInterface->getQueryBuilder();

        /** @var $entityRepository FlexibleEntityRepository */
        $entityRepository = $this->flexibleManager->getFlexibleRepository();
        $entityRepository->applySorterByAttribute($queryBuilder, $this->getField()->getFieldName(), $direction);
    }
}
