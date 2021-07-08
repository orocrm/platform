<?php

namespace Oro\Bundle\IntegrationBundle\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorRegistry;
use Oro\Bundle\IntegrationBundle\Entity\Channel as Integration;
use Oro\Bundle\IntegrationBundle\Entity\Status;
use Oro\Bundle\IntegrationBundle\Event\SyncEvent;
use Oro\Bundle\IntegrationBundle\ImportExport\Job\Executor;
use Oro\Bundle\IntegrationBundle\Logger\LoggerStrategy;
use Oro\Bundle\IntegrationBundle\Manager\TypesRegistry;
use Oro\Component\PhpUtils\ArrayUtil;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Synchronizes data to the application using an import processor
 */
class SyncProcessor extends AbstractSyncProcessor
{
    /** @var ManagerRegistry */
    protected $doctrineRegistry;

    /**
     * @param ManagerRegistry          $doctrineRegistry
     * @param ProcessorRegistry        $processorRegistry
     * @param Executor                 $jobExecutor
     * @param TypesRegistry            $registry
     * @param LoggerStrategy           $logger
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        ManagerRegistry $doctrineRegistry,
        ProcessorRegistry $processorRegistry,
        Executor $jobExecutor,
        TypesRegistry $registry,
        EventDispatcherInterface $eventDispatcher,
        LoggerStrategy $logger = null
    ) {
        $this->doctrineRegistry = $doctrineRegistry;

        parent::__construct($processorRegistry, $jobExecutor, $registry, $eventDispatcher, $logger);
    }

    /**
     * Process integration synchronization
     * By default, if $connector is empty, will process all connectors of given integration
     *
     * {@inheritdoc}
     *
     * @return boolean
     */
    public function process(Integration $integration, $connector = null, array $parameters = [])
    {
        if (!$integration->isEnabled()) {
            $this->logger->error(
                sprintf(
                    'Integration "%s" with type "%s" is not enabled. Cannot process synchronization.',
                    $integration->getName(),
                    $integration->getType()
                )
            );

            return false;
        }

        $callback = null;

        if ($connector) {
            $callback = function ($integrationConnector) use ($connector) {
                return $integrationConnector === $connector;
            };
        }

        return $this->processConnectors($integration, $parameters, $callback);
    }

    /**
     * Process integration synchronization
     * By default, if $connector is empty, will process all connectors of given integration
     *
     * @param Integration $integration Integration object
     * @param array       $parameters  Connector additional parameters
     * @param callable    $callback    Callback to filter connectors
     *
     * @return boolean
     */
    protected function processConnectors(Integration $integration, array $parameters = [], callable $callback = null)
    {
        $connectors = $this->getConnectorsToProcess($integration, $callback);

        $isSuccess = true;

        $processedConnectorStatuses = [];
        foreach ($connectors as $connector) {
            try {
                if (!$this->isConnectorAllowed($connector, $integration, $processedConnectorStatuses)) {
                    continue;
                }
                $status = $this->processIntegrationConnector($integration, $connector, $parameters);

                $isSuccess = $isSuccess && $this->isIntegrationConnectorProcessSuccess($status);
                $processedConnectorStatuses[$connector->getType()] = $status;
            } catch (\Exception $exception) {
                $isSuccess = false;
                $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
            }
        }

        return $isSuccess;
    }

    /**
     * @param Integration   $integration
     * @param callable|null $callback
     *
     * @return ConnectorInterface[]
     */
    protected function getConnectorsToProcess(Integration $integration, callable $callback = null)
    {
        $connectors = $this->loadConnectorsToProcess($integration, $callback);

        return $this->getSortedConnectors($connectors);
    }

    /**
     * @param Integration $integration
     * @param callable    $callback
     *
     * @return ConnectorInterface[]
     */
    protected function loadConnectorsToProcess($integration, callable $callback = null)
    {
        $connectorTypes = $this->getTypesOfConnectorsToProcess($integration, $callback);

        /** @var ConnectorInterface[] $realConnectors */
        $connectors = array_map(function ($connectorType) use ($integration) {
            return $this->getRealConnector($integration, $connectorType);
        }, $connectorTypes);

        return $connectors;
    }

    /**
     * @param Integration $integration
     * @param callable    $callback
     *
     * @return string[]
     */
    protected function getTypesOfConnectorsToProcess(Integration $integration, callable $callback = null)
    {
        $connectors = $integration->getConnectors();

        if ($callback) {
            $connectors = array_filter($connectors, $callback);
        }

        return $connectors;
    }

    /**
     * @param ConnectorInterface[] $connectors
     *
     * @return ConnectorInterface[]
     */
    protected function getSortedConnectors(array $connectors)
    {
        $sortCallback = function ($connector) {
            return $connector instanceof OrderedConnectorInterface
                ? $connector->getOrder()
                : OrderedConnectorInterface::DEFAULT_ORDER;
        };
        ArrayUtil::sortBy($connectors, false, $sortCallback);

        return $connectors;
    }

    /**
     * Checks whether connector is allowed to process. Logs information if connector is not allowed.
     *
     * @param ConnectorInterface $connector
     * @param Integration        $integration
     * @param Status[]           $processedConnectorStatuses
     *
     * @return bool
     */
    protected function isConnectorAllowed(
        ConnectorInterface $connector,
        Integration $integration,
        array $processedConnectorStatuses
    ) {
        if ($connector instanceof AllowedConnectorInterface
            && !$connector->isAllowed($integration, $processedConnectorStatuses)
        ) {
            $this->logger->info(
                sprintf(
                    'Connector with type "%s" is not allowed and it\'s processing is skipped.',
                    $connector->getType()
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Process integration connector
     *
     * @param Integration        $integration Integration object
     * @param ConnectorInterface $connector   Connector object
     * @param array              $parameters  Connector additional parameters
     *
     * @return Status
     */
    protected function processIntegrationConnector(
        Integration $integration,
        ConnectorInterface $connector,
        array $parameters = []
    ) {
        try {
            $this->logger->notice(sprintf('Start processing "%s" connector', $connector->getType()));

            $processorAliases = $this->processorRegistry->getProcessorAliasesByEntity(
                ProcessorRegistry::TYPE_IMPORT,
                $connector->getImportEntityFQCN()
            );
        } catch (\Exception $exception) {
            // log and continue
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            $status = $this->createConnectorStatus($connector)
                ->setCode(Status::STATUS_FAILED)
                ->setMessage($exception->getMessage());
            $this->addConnectorStatusAndFlush($integration, $status);

            return $status;
        }

        $configuration = [
            ProcessorRegistry::TYPE_IMPORT =>
                array_merge(
                    [
                        'processorAlias' => reset($processorAliases),
                        'entityName'     => $connector->getImportEntityFQCN(),
                        'channel'        => $integration->getId(),
                        'channelType'    => $integration->getType(),
                    ],
                    $parameters
                ),
        ];

        return $this->processImport($integration, $connector, $configuration);
    }

    /**
     * @param Integration        $integration
     * @param ConnectorInterface $connector
     * @param array              $configuration
     *
     * @return Status
     */
    protected function processImport(Integration $integration, ConnectorInterface $connector, array $configuration)
    {
        $importJobName = $connector->getImportJobName();

        $syncBeforeEvent = $this->dispatchSyncEvent(SyncEvent::SYNC_BEFORE, $importJobName, $configuration);

        $configuration = $syncBeforeEvent->getConfiguration();
        $jobResult = $this->jobExecutor->executeJob(ProcessorRegistry::TYPE_IMPORT, $importJobName, $configuration);

        $this->dispatchSyncEvent(SyncEvent::SYNC_AFTER, $importJobName, $configuration, $jobResult);

        $context = $jobResult->getContext();
        $connectorData = $errors = [];
        if ($context) {
            $connectorData = $context->getValue(ConnectorInterface::CONTEXT_CONNECTOR_DATA_KEY);
            $errors = $context->getErrors();
        }
        $exceptions = $jobResult->getFailureExceptions();
        $isSuccess = $jobResult->isSuccessful() && empty($exceptions);

        $status = $this->createConnectorStatus($connector);
        $status->setData((array)$connectorData);

        $message = $this->formatResultMessage($context);
        $this->logger->info($message);

        if ($isSuccess) {
            if ($errors) {
                $warningsText = 'Some entities were skipped due to warnings:' . PHP_EOL;
                $warningsText .= implode(PHP_EOL, $errors);
                $message .= PHP_EOL . $warningsText;

                if ($integration->getSynchronizationSettings()->offsetGetOr('logWarnings', false)) {
                    foreach ($errors as $error) {
                        $this->logger->error($error);
                    }
                }
            }

            $status->setCode(Status::STATUS_COMPLETED)->setMessage($message);
        } else {
            $this->logger->error('Errors have occurred:');
            $exceptions = implode(PHP_EOL, $exceptions);

            $this->logger->error($exceptions);
            $status->setCode(Status::STATUS_FAILED)->setMessage($exceptions);
        }

        $this->addConnectorStatusAndFlush($integration, $status);

        return $status;
    }

    /**
     * Creates status of connector of integration.
     *
     * @param ConnectorInterface $connector
     * @return Status
     */
    protected function createConnectorStatus(ConnectorInterface $connector)
    {
        $status = new Status();
        $status->setConnector($connector->getType());

        return $status;
    }

    /**
     * {@inheritdoc}
     */
    protected function formatResultMessage(ContextInterface $context = null)
    {
        return sprintf(
            '[%s] %s',
            strtoupper(ProcessorRegistry::TYPE_IMPORT),
            parent::formatResultMessage($context)
        );
    }

    /**
     * Saves connector's status.
     *
     * @param Integration $integration
     * @param Status      $status
     *
     * @return void
     */
    protected function addConnectorStatusAndFlush(Integration $integration, Status $status)
    {
        $this->doctrineRegistry
            ->getRepository('OroIntegrationBundle:Channel')
            ->addStatusAndFlush($integration, $status);
    }
}
