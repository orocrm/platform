<?php

namespace Oro\Bundle\FilterBundle\Extension\Orm;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\QueryBuilder;

use Oro\Bundle\FilterBundle\Extension\Configuration;

use Oro\Bundle\FilterBundle\Form\Type\Filter\ChoiceFilterType;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;

class ChoiceFilter extends AbstractFilter
{
    /**
     * {@inheritdoc}
     */
    protected function getFormType()
    {
        return ChoiceFilterType::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(QueryBuilder $qb, $data)
    {
        $data = $this->parseData($data);
        if (!$data) {
            return false;
        }

        $operator  = $this->getOperator($data['type']);
        $parameter = $this->generateQueryParameterName();

        if ('IN' == $operator) {
            $expression = $qb->expr()->in($this->get(self::DATA_NAME_KEY), ':' . $parameter);
        } else {
            $expression = $qb->expr()->notIn($this->get(self::DATA_NAME_KEY), ':' . $parameter);
        }

        $this->applyFilterToClause($qb, $expression);
        /** @var $qb QueryBuilder */
        $qb->setParameter($parameter, $data['value']);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata()
    {
        $formView  = $this->getForm()->createView();
        $fieldView = $formView->children['value'];

        $choices = array_map(
            function (ChoiceView $choice) {
                return [
                    'label' => $choice->label,
                    'value' => $choice->value
                ];
            },
            $fieldView->vars['choices']
        );


        $metadata                    = parent::getMetadata();
        $metadata['choices']         = $choices;
        $metadata['populateDefault'] = $formView->vars['populate_default'];

        if ($fieldView->vars['multiple']) {
            $metadata[Configuration::TYPE_KEY] = 'multichoice';
        }
        return $metadata;
    }

    /**
     * @param mixed $data
     *
     * @return array|bool
     */
    protected function parseData($data)
    {
        if (!is_array($data)
            || !array_key_exists('value', $data)
            || $data['value'] === ''
            || is_null($data['value'])
            || ((is_array($data['value']) || $data['value'] instanceof Collection) && !count($data['value']))
        ) {
            return false;
        }

        $value = $data['value'];

        if ($value instanceof Collection) {
            $value = $value->getValues();
        }
        if (!is_array($value)) {
            $value = array($value);
        }

        $data['type']  = isset($data['type']) ? $data['type'] : null;
        $data['value'] = $value;

        return $data;
    }

    /**
     * Get operator string
     *
     * @param int $type
     *
     * @return string
     */
    protected function getOperator($type)
    {
        $type = (int)$type;

        $operatorTypes = array(
            ChoiceFilterType::TYPE_CONTAINS     => 'IN',
            ChoiceFilterType::TYPE_NOT_CONTAINS => 'NOT IN',
        );

        return isset($operatorTypes[$type]) ? $operatorTypes[$type] : 'IN';
    }
}
