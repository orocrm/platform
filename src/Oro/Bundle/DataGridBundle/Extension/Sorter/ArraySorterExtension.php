<?php

namespace Oro\Bundle\DataGridBundle\Extension\Sorter;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datasource\ArrayDatasource\ArrayDatasource;
use Oro\Bundle\DataGridBundle\Datasource\DatasourceInterface;
use Oro\Bundle\DataGridBundle\Exception\InvalidArgumentException;
use Oro\Bundle\DataGridBundle\Exception\UnexpectedTypeException;

class ArraySorterExtension extends AbstractSorterExtension
{
    const ASC_SORTING = 'ASC';
    const DESC_SORTING = 'DESC';

    /** {@inheritdoc} */
    public function isApplicable(DatagridConfiguration $config)
    {
        return
            $config->getDatasourceType() === ArrayDatasource::TYPE
            && parent::isApplicable($config);
    }

    /**
     * @param array $sorter
     * @param string $direction
     * @param DatasourceInterface $datasource
     * @throws UnexpectedTypeException
     * @throws InvalidArgumentException
     */
    protected function addSorterToDatasource(array $sorter, $direction, DatasourceInterface $datasource)
    {
        if (!$datasource instanceof ArrayDatasource) {
            throw new UnexpectedTypeException($datasource, ArrayDatasource::class);
        }

        $results = $datasource->getArraySource();
        if ($results) {
            $sortedResults = $this->sortArray($results, $sorter['data_name'], $direction);
            $datasource->setArraySource($sortedResults);
        }
    }

    /**
     * @param array $data
     * @param string $sortingKey
     * @param string $direction
     * @return array
     */
    protected function sortArray(array $data, $sortingKey, $direction)
    {
        usort(
            $data,
            function ($currentRow, $nextRow) use ($sortingKey, $direction) {
                $compareIndex = strnatcmp(
                    $this->safeStringConvert($currentRow[$sortingKey]),
                    $this->safeStringConvert($nextRow[$sortingKey])
                );
                if ($direction === self::DESC_SORTING && $compareIndex !== 0) {
                    $compareIndex = $compareIndex > 0 ? -1 : 1;
                }

                return $compareIndex;
            }
        );

        return $data;
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function safeStringConvert($value)
    {
        if ($value instanceof \DateTime) {
            $value = $value->getTimestamp();
        }

        return iconv('utf-8', 'ascii//TRANSLIT', strtolower((string)$value));
    }
}
