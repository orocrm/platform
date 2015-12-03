<?php

namespace Oro\Bundle\GridBundle\Filter\ORM\Flexible;

use Oro\Bundle\FilterBundle\Form\Type\Filter\DateRangeFilterType;
use Oro\Bundle\GridBundle\Datagrid\ProxyQueryInterface;
use Oro\Bundle\GridBundle\Filter\ORM\AbstractDateFilter;

abstract class AbstractFlexibleDateFilter extends AbstractFlexibleFilter
{
    /**
     * @var string
     */
    protected $parentFilterClass = 'Oro\\Bundle\\GridBundle\\Filter\\ORM\\AbstractDateFilter';

    /**
     * @var AbstractDateFilter
     */
    protected $parentFilter;

    /**
     * {@inheritdoc}
     */
    public function filter(ProxyQueryInterface $queryBuilder, $alias, $field, $data)
    {
        $data = $this->parentFilter->parseData($data);
        if (!$data) {
            return;
        }

        $dateStartValue = $data['date_start'];
        $dateEndValue = $data['date_end'];
        $type = $data['type'];

        if ($type == DateRangeFilterType::TYPE_NOT_BETWEEN) {
            $this->applyFilterNotBetween($queryBuilder, $dateStartValue, $dateEndValue, $field);
        } else {
            $this->applyFilterBetween($queryBuilder, $dateStartValue, $dateEndValue, $field);
        }
    }

    /**
     * @param ProxyQueryInterface $queryBuilder
     * @param string $dateStartValue
     * @param string $dateEndValue
     * @param string $field
     */
    protected function applyFilterBetween(
        ProxyQueryInterface $queryBuilder,
        $dateStartValue,
        $dateEndValue,
        $field
    ) {
        if ($dateStartValue) {
            $this->applyFlexibleFilter($queryBuilder, $field, $dateStartValue, '>=');
        }

        if ($dateEndValue) {
            $this->applyFlexibleFilter($queryBuilder, $field, $dateEndValue, '<=');
        }
    }

    /**
     * @param ProxyQueryInterface $queryBuilder
     * @param string $dateStartValue
     * @param string $dateEndValue
     * @param string $field
     */
    protected function applyFilterNotBetween(
        ProxyQueryInterface $queryBuilder,
        $dateStartValue,
        $dateEndValue,
        $field
    ) {
        $values = array();
        $operators = array();

        if ($dateStartValue) {
            $values['from'] = $dateStartValue;
            $operators['from'] = '<';
        }

        if ($dateEndValue) {
            $values['to'] = $dateEndValue;
            $operators['to'] = '>';
        }

        if ($values && $operators) {
            $this->applyFlexibleFilter($queryBuilder, $field, $values, $operators);
        }
    }
}
