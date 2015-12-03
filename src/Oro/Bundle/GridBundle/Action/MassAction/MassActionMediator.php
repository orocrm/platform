<?php

namespace Oro\Bundle\GridBundle\Action\MassAction;

use Oro\Bundle\GridBundle\Action\MassAction\MassActionInterface;

use Oro\Bundle\GridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\GridBundle\Datagrid\IterableResultInterface;
use Oro\Bundle\GridBundle\Datagrid\ResultRecordInterface;
use Symfony\Component\HttpFoundation\Request;

class MassActionMediator implements MassActionMediatorInterface
{
    /**
     * @var MassActionInterface
     */
    protected $massAction;

    /**
     * @var \Iterator|ResultRecordInterface[]
     */
    protected $results;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var DatagridInterface|null
     */
    protected $datagrid;

    /**
     * @param MassActionInterface $massAction
     * @param DatagridInterface $datagrid
     * @param IterableResultInterface $results
     * @param array $data
     */
    public function __construct(
        MassActionInterface $massAction,
        DatagridInterface $datagrid,
        IterableResultInterface $results,
        array $data = array()
    ) {
        $this->massAction = $massAction;
        $this->results    = $results;
        $this->data       = $data;
        $this->datagrid   = $datagrid;
    }

    /**
     * {@inheritDoc}
     */
    public function getMassAction()
    {
        return $this->massAction;
    }

    /**
     * {@inheritDoc}
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * {@inheritDoc}
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * {@inheritDoc}
     */
    public function getDatagrid()
    {
        return $this->datagrid;
    }
}
