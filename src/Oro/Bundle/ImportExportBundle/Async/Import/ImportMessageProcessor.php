<?php

namespace Oro\Bundle\ImportExportBundle\Async\Import;

use Oro\Bundle\ImportExportBundle\Async\ImportExportResultSummarizer;

use Oro\Bundle\ImportExportBundle\File\FileManager;
use Oro\Bundle\ImportExportBundle\Handler\AbstractImportHandler;
use Oro\Bundle\MessageQueueBundle\Entity\Job;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\JobRunner;
use Oro\Component\MessageQueue\Job\JobStorage;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Oro\Component\MessageQueue\Util\JSON;
use Psr\Log\LoggerInterface;

class ImportMessageProcessor implements MessageProcessorInterface
{
    /**
     * @var AbstractImportHandler
     */
    protected $importHandler;

    /**
     * @var JobRunner
     */
    protected $jobRunner;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ImportExportResultSummarizer
     */
    protected $importExportResultSummarizer;

    /**
     * @var FileManager
     */
    protected $fileManager;

    /**
     * @var JobStorage
     */
    protected $jobStorage;

    /**
     * @param JobRunner $jobRunner
     * @param ImportExportResultSummarizer $importExportResultSummarizer
     * @param JobStorage $jobStorage
     * @param LoggerInterface $logger
     * @param FileManager $fileManager
     * @param AbstractImportHandler $importHandler
     */
    public function __construct(
        JobRunner $jobRunner,
        ImportExportResultSummarizer $importExportResultSummarizer,
        JobStorage $jobStorage,
        LoggerInterface $logger,
        FileManager $fileManager,
        AbstractImportHandler $importHandler
    ) {
        $this->importHandler = $importHandler;
        $this->jobRunner = $jobRunner;
        $this->importExportResultSummarizer = $importExportResultSummarizer;
        $this->jobStorage = $jobStorage;
        $this->logger = $logger;
        $this->fileManager = $fileManager;
    }

    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session)
    {
        if (! $body = $this->getNormalizeBody($message)) {
            $this->logger->critical(
                sprintf(
                    '[%s] Got invalid message. body: %s',
                    (new \ReflectionClass($this))->getShortName(),
                    $message->getBody()
                ),
                ['message' => $message]
            );

            return self::REJECT;
        }

        $result = $this->jobRunner->runDelayed(
            $body['jobId'],
            function (JobRunner $jobRunner, Job $job) use ($body) {
                return $this->handleImport($body, $job);
            }
        );

        $this->fileManager->deleteFile($body['fileName']);

        return $result ? self::ACK : self::REJECT;
    }

    /**
     * @param MessageInterface $message
     * @return array|null
     */
    protected function getNormalizeBody(MessageInterface $message)
    {
        $body = JSON::decode($message->getBody());

        if (! isset(
            $body['fileName'],
            $body['jobName'],
            $body['processorAlias'],
            $body['jobId'],
            $body['process'],
            $body['originFileName']
        )) {
            return null;
        }

        return array_replace_recursive([
                'options' => []
            ], $body);
    }

    /**
     * @param array $body
     * @param Job $job
     * @return bool
     */
    protected function handleImport(array $body, Job $job)
    {
        $filePath = $this->fileManager->writeToTmpLocalStorage($body['fileName']);
        $this->importHandler->setImportingFileName($filePath);
        $result = $this->importHandler->handle(
            $body['process'],
            $body['jobName'],
            $body['processorAlias'],
            $body['options']
        );
        $this->saveJobResult($job, $result);
        $this->logger->info(
            $this->importExportResultSummarizer->getImportSummaryMessage(
                array_merge(['originFileName' => $body['originFileName']], $result),
                $body['process'],
                $this->logger
            ),
            ['message' => $body]
        );

        return !!$result['success'];
    }

    /**
     * @param Job $job
     * @param array $data
     */
    protected function saveJobResult(Job $job, array $data)
    {
        unset($data['message']);
        unset($data['importInfo']);

        $job = $this->jobStorage->findJobById($job->getId());
        if (isset($data['errors']) && ! empty(($data['errors']))) {
            $data['errorLogFile'] = $this->saveToStorageErrorLog($data['errors']);
        }

        unset($data['errors']);
        $job->setData($data);

        $this->jobStorage->saveJob($job);
    }

    /**
     * @param array $errors
     * @return string
     */
    protected function saveToStorageErrorLog(array &$errors)
    {
        /** @var FileManager $fileManager */
        $fileManager = $this->fileManager;
        $errorAsJson = json_encode($errors);

        $fileName = str_replace('.', '', uniqid('import')) .'.json';

        $fileManager->getFileSystem()->write($fileName, $errorAsJson);

        return $fileName;
    }
}
