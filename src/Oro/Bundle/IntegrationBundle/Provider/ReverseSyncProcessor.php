<?php

namespace Oro\Bundle\IntegrationBundle\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorRegistry;
use Oro\Bundle\IntegrationBundle\Entity\Channel as Integration;
use Oro\Bundle\IntegrationBundle\Entity\Status;
use Oro\Bundle\IntegrationBundle\Event\SyncEvent;
use Oro\Bundle\IntegrationBundle\Exception\InvalidConnectorException;
use Oro\Bundle\IntegrationBundle\ImportExport\Job\Executor;
use Oro\Bundle\IntegrationBundle\Logger\LoggerStrategy;
use Oro\Bundle\IntegrationBundle\Manager\TypesRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Synchronizes data from the application using an export processor
 */
class ReverseSyncProcessor extends AbstractSyncProcessor
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
     * {@inheritdoc}
     *
     * @throws InvalidConnectorException
     */
    public function process(Integration $integration, $connector, array $parameters = [])
    {
        if (!$integration->isEnabled()) {
            return false;
        }

        $this->logger->info(sprintf('Start processing "%s" connector', $connector));
        $realConnector = $this->getRealConnector($integration, $connector);

        $processorAliases = $this->processorRegistry->getProcessorAliasesByEntity(
            ProcessorRegistry::TYPE_EXPORT,
            $realConnector->getImportEntityFQCN()
        );

        $configuration = [
            ProcessorRegistry::TYPE_EXPORT =>
                array_merge(
                    [
                        'entityName'     => $realConnector->getImportEntityFQCN(),
                        'processorAlias' => reset($processorAliases),
                        'channel'        => $integration->getId()
                    ],
                    $parameters
                ),
        ];

        return $this->processExport($integration, $realConnector, $configuration);
    }

    /**
     * @param Integration        $integration
     * @param ConnectorInterface $connector
     * @param array              $configuration
     *
     * @return bool
     *
     * @throws InvalidConnectorException
     */
    protected function processExport(Integration $integration, ConnectorInterface $connector, array $configuration)
    {
        $this->assertValidConnector($connector);
        /** @var TwoWaySyncConnectorInterface $connector */
        $exportJobName = $connector->getExportJobName();

        $syncBeforeEvent = $this->dispatchSyncEvent(SyncEvent::SYNC_BEFORE, $exportJobName, $configuration);

        $configuration = $syncBeforeEvent->getConfiguration();
        $jobResult = $this->jobExecutor->executeJob(ProcessorRegistry::TYPE_EXPORT, $exportJobName, $configuration);

        $this->dispatchSyncEvent(SyncEvent::SYNC_AFTER, $exportJobName, $configuration, $jobResult);

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
        $this->logger->notice($message);

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

        return $isSuccess;
    }

    /**
     * Creates integration connector's status.
     *
     * @param ConnectorInterface $connector
     *
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
            strtoupper(ProcessorRegistry::TYPE_EXPORT),
            parent::formatResultMessage($context)
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function assertValidConnector(ConnectorInterface $connector)
    {
        if (!($connector instanceof TwoWaySyncConnectorInterface)) {
            throw new InvalidConnectorException('This connector does not support reverse sync.');
        }
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
