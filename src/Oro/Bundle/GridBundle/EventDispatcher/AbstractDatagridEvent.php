<?php

namespace Oro\Bundle\GridBundle\EventDispatcher;

use Oro\Bundle\GridBundle\Datagrid\DatagridInterface;

use Symfony\Component\EventDispatcher\Event;

abstract class AbstractDatagridEvent extends Event implements DatagridEventInterface
{
    /**
     * @var DatagridInterface
     */
    protected $datagrid;

    /**
     * @param DatagridInterface $datagrid
     */
    public function __construct(DatagridInterface $datagrid = null)
    {
        $this->datagrid = $datagrid;
    }

    /**
     * @return DatagridInterface
     */
    public function getDatagrid()
    {
        return $this->datagrid;
    }

    /**
     * @param string $datagridName
     * @return bool
     */
    public function isDatagridName($datagridName)
    {
        return $this->datagrid->getName() == $datagridName;
    }
}
