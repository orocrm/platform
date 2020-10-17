<?php

namespace Oro\Bundle\FilterBundle\Filter;

use Doctrine\DBAL\Platforms\PostgreSQL92Platform;
use Oro\Bundle\FilterBundle\Datasource\FilterDatasourceAdapterInterface;
use Oro\Bundle\FilterBundle\Form\Type\Filter\TextFilterType;

class StringFilter extends AbstractFilter
{
    /**
     * {@inheritdoc}
     */
    protected $joinOperators = [
        FilterUtility::TYPE_NOT_EMPTY => FilterUtility::TYPE_EMPTY,
        TextFilterType::TYPE_NOT_CONTAINS => TextFilterType::TYPE_CONTAINS,
        TextFilterType::TYPE_NOT_IN => TextFilterType::TYPE_IN,
    ];

    /**
     * {@inheritDoc}
     */
    protected function buildExpr(FilterDatasourceAdapterInterface $ds, $comparisonType, $fieldName, $data)
    {
        $parameterName = $ds->generateParameterName($this->getName());
        $this->setCaseSensitivity($ds);
        $expr = $this->buildComparisonExpr(
            $ds,
            $comparisonType,
            $fieldName,
            $parameterName
        );
        $this->resetCaseSensitivity($ds);
        if (!in_array($comparisonType, [FilterUtility::TYPE_EMPTY, FilterUtility::TYPE_NOT_EMPTY])) {
            $ds->setParameter(
                $parameterName,
                $this->convertValue($data['value'])
            );
        }

        return $expr;
    }

    /**
     * @param array $data
     * @return array|mixed|string
     */
    protected function convertValue($data)
    {
        if (is_array($data) && !$this->isCaseInsensitive()) {
            // used when e.g. we have type TextFilterType::TYPE_IN and search is case-sensitive
            return array_map(array($this, 'convertData'), $data);
        } elseif (is_array($data)) {
            // used when e.g. we have type TextFilterType::TYPE_IN and search is case-insensitive
            return array_map('mb_strtolower', $data);
        } elseif (!$this->isCaseInsensitive()) {
            // used when e.g. we have type other then TextFilterType::TYPE_IN and search is case sensitive
            return $this->convertData($data);
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    protected function getFormType()
    {
        return TextFilterType::class;
    }

    /**
     * @param mixed $data
     *
     * @return array|bool
     */
    protected function parseData($data)
    {
        $type = isset($data['type']) ? $data['type'] : null;
        if (!in_array($type, [FilterUtility::TYPE_EMPTY, FilterUtility::TYPE_NOT_EMPTY])
            && (!is_array($data) || !array_key_exists('value', $data) || 
                ($data['value'] !== '0' && empty($data['value'])))
        ) {
            return false;
        }

        $data['type'] = $type;
        $data['value'] = $this->parseValue($data['type'], $data['value']);

        return $data;
    }

    /**
     * Build an expression used to filter data
     *
     * @param FilterDatasourceAdapterInterface $ds
     * @param int                              $comparisonType
     * @param string                           $fieldName
     * @param string                           $parameterName
     *
     * @return string
     */
    protected function buildComparisonExpr(
        FilterDatasourceAdapterInterface $ds,
        $comparisonType,
        $fieldName,
        $parameterName
    ) {
        switch ($comparisonType) {
            case TextFilterType::TYPE_EQUAL:
                return $ds->expr()->eq($fieldName, $parameterName, true);
            case TextFilterType::TYPE_NOT_CONTAINS:
                return $ds->expr()->notLike($fieldName, $parameterName, true);
            case TextFilterType::TYPE_IN:
                return $ds->expr()->in($fieldName, $parameterName, true);
            case TextFilterType::TYPE_NOT_IN:
                return $ds->expr()->notIn($fieldName, $parameterName, true);
            case FilterUtility::TYPE_EMPTY:
                $emptyString = $ds->expr()->literal('');

                if ($this->isCompositeField($ds, $fieldName)) {
                    $fieldName = $ds->expr()->trim($fieldName);
                }

                return $ds->expr()->orX(
                    $ds->expr()->isNull($fieldName),
                    $ds->expr()->eq($fieldName, $emptyString)
                );
            case FilterUtility::TYPE_NOT_EMPTY:
                $emptyString = $ds->expr()->literal('');

                if ($this->isCompositeField($ds, $fieldName)) {
                    $fieldName = $ds->expr()->trim($fieldName);
                }

                return $ds->expr()->andX(
                    $ds->expr()->isNotNull($fieldName),
                    $ds->expr()->neq($fieldName, $emptyString)
                );
            default:
                return $ds->expr()->like($fieldName, $parameterName, true);
        }
    }

    /**
     * @param FilterDatasourceAdapterInterface $ds
     * @param string                           $fieldName
     *
     * @return bool
     */
    protected function isCompositeField(FilterDatasourceAdapterInterface $ds, $fieldName)
    {
        return (bool)preg_match('/(?<![\w:.])(CONCAT)\s*\(/im', $ds->getFieldByAlias($fieldName));
    }

    /**
     * Return a value depending on comparison type
     *
     * @param int    $comparisonType
     * @param string $value
     *
     * @return mixed
     */
    protected function parseValue($comparisonType, $value)
    {
        switch ($comparisonType) {
            case TextFilterType::TYPE_CONTAINS:
            case TextFilterType::TYPE_NOT_CONTAINS:
                return sprintf('%%%s%%', $value);
            case TextFilterType::TYPE_STARTS_WITH:
                return sprintf('%s%%', $value);
            case TextFilterType::TYPE_ENDS_WITH:
                return sprintf('%%%s', $value);
            case TextFilterType::TYPE_IN:
            case TextFilterType::TYPE_NOT_IN:
                return array_map('trim', explode(',', $value));
            default:
                return $value;
        }
    }

    /**
     * @param FilterDatasourceAdapterInterface $ds
     */
    protected function setCaseSensitivity(FilterDatasourceAdapterInterface $ds)
    {
        $platform = $ds->getDatabasePlatform();
        if ($platform instanceof PostgreSQL92Platform && $this->isCaseInsensitive()) {
            $ds->expr()->setCaseInsensitive(true);
        }
    }

    /**
     * @param FilterDatasourceAdapterInterface $ds
     */
    protected function resetCaseSensitivity(FilterDatasourceAdapterInterface $ds)
    {
        $ds->expr()->setCaseInsensitive(false);
    }

    /**
     * @return bool
     */
    protected function isCaseInsensitive()
    {
        // if param doesn't exists it means case insensitive search (backward compatibility)
        return (!$this->has(FilterUtility::CASE_INSENSITIVE_KEY) || $this->get(FilterUtility::CASE_INSENSITIVE_KEY));
    }
}
