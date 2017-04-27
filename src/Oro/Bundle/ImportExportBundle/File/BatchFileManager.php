<?php

namespace Oro\Bundle\ImportExportBundle\File;

use Oro\Bundle\ImportExportBundle\Context\Context;
use Oro\Bundle\ImportExportBundle\Exception\InvalidConfigurationException;
use Oro\Bundle\ImportExportBundle\Reader\AbstractFileReader;
use Oro\Bundle\ImportExportBundle\Writer\FileStreamWriter;

class BatchFileManager
{
    /**
     * @var int
     */
    protected $sizeOfBatch;
    /**
     * @var AbstractFileReader
     */
    protected $reader;

    /**
     * @var FileStreamWriter
     */
    protected $writer;

    /**
     * @var FileManager
     */
    protected $fileManager;

    /**
     * @param FileManager $fileManager
     * @param integer $sizeOfBatch
     */
    public function __construct(FileManager $fileManager, $sizeOfBatch)
    {
        $this->fileManager = $fileManager;
        $this->sizeOfBatch = $sizeOfBatch;
    }

    /**
     * @param AbstractFileReader $reader
     */
    public function setReader(AbstractFileReader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * @param FileStreamWriter $writer
     */
    public function setWriter(FileStreamWriter $writer)
    {
        $this->writer = $writer;
    }

    /**
     * This method split file on chunk, and return file name array.
     *
     * @param string $pathFile
     * @return array
     */
    public function splitFile($pathFile)
    {
        if (! ($this->writer && $this->reader)) {
            throw new InvalidConfigurationException('Reader and Writer must be configured.');
        }
        $context = new Context(['filePath' => $pathFile]);
        $this->reader->initializeByContext($context);
        $data = [];
        $i = 0;
        $header = null;
        $files = [];
        $extension = pathinfo($pathFile, PATHINFO_EXTENSION);
        while ($row = $this->reader->read($context)) {
            $header = $header ?: $this->reader->getHeader();
            $data[] = $row;
            if (++$i == $this->sizeOfBatch) {
                $files[] = $this->writeBatch($data, $header, $extension);
                $data = [];
                $i = 0;
            }
        }

        if ($i) {
            $files[] = $this->writeBatch($data, $header, $extension);
        }

        return $files;
    }

    /**
     * @param array $data
     * @param array|null $header
     * @param string $extension
     *
     * @return string
     */
    private function writeBatch(array $data, $header, $extension)
    {
        $batchFileName = FileManager::generateUniqueFileName($extension);
        $batchFilePath = FileManager::generateTmpFilePath($batchFileName);
        $writerContext = new Context(['filePath'=> $batchFilePath, 'header' => $header]);
        $this->writer->setImportExportContext($writerContext);
        $this->writer->write($data);
        $this->writer->close();
        $this->fileManager->writeFileToStorage($batchFilePath, $batchFileName);
        @unlink($batchFilePath);

        return $batchFileName;
    }

    /**
     * @param array $files
     * @param string $summaryFile
     * @return string
     */
    public function mergeFiles(array $files, $summaryFile)
    {
        if (! ($this->writer && $this->reader)) {
            throw new InvalidConfigurationException('Reader and Writer must be configured.');
        }
        $contextWriter = null;
        foreach ($files as $file) {
            $contextReader = new Context(['filePath' => $file]);
            $this->reader->initializeByContext($contextReader);
            while ($item = $this->reader->read($contextReader)) {
                if (! $contextWriter) {
                    $contextWriter = new Context(
                        [
                            'filePath' => $summaryFile,
                            'header' => $this->reader->getHeader(),
                            'firstLineIsHeader' => true,
                        ]
                    );
                    $this->writer->setImportExportContext($contextWriter);
                }
                $this->writer->write([$item]);
            }
        }
        $this->writer->close();

        return $summaryFile;
    }
}
