<?php

namespace Oro\Bundle\ImportExportBundle\Reader;

use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;

use Akeneo\Bundle\BatchBundle\Item\ParseException;

use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Exception\InvalidConfigurationException;

class CsvFileReader extends AbstractFileReader
{
    /**
     * @var string
     */
    protected $delimiter = ',';

    /**
     * @var string
     */
    protected $enclosure = '"';

    /**
     * @var string
     */
    protected $escape = '\\';


    /**
     * {@inheritdoc}
     */
    public function read($context = null)
    {
        if ($this->isEof()) {
            return null;
        }

        $data = $this->getFile()->fgetcsv();
        if (false !== $data) {
            if (! $context instanceof ContextInterface) {
                $context = $this->getContext();
            }
            $context->incrementReadOffset();
            if (null === $data || [null] === $data) {
                if ($this->isEof()) {
                    return null;
                }

                return [];
            }
            $context->incrementReadCount();

            if ($this->firstLineIsHeader) {
                if (count($this->header) !== count($data)) {
                    throw new InvalidItemException(
                        sprintf(
                            'Expecting to get %d columns, actually got %d',
                            count($this->header),
                            count($data)
                        ),
                        $data
                    );
                }

                $data = array_combine($this->header, $data);
            }
        } else {
            throw new ParseException('An error occurred while reading the csv.');
        }

        return $data;
    }

    /**
     * @return bool
     */
    protected function isEof()
    {
        if ($this->getFile()->eof()) {
            $this->getFile()->rewind();
            $this->header = null;

            return true;
        }

        return false;
    }

    /**
     * @return \SplFileObject
     */
    protected function getFile()
    {
        if ($this->file instanceof \SplFileObject && $this->file->getFilename() != $this->fileInfo->getFilename()) {
            $this->file = null;
            $this->header = null;
        }
        if (!$this->file instanceof \SplFileObject) {
            $this->file = $this->fileInfo->openFile();
            $this->file->setFlags(
                \SplFileObject::READ_CSV |
                \SplFileObject::READ_AHEAD |
                \SplFileObject::DROP_NEW_LINE
            );
            $this->file->setCsvControl(
                $this->delimiter,
                $this->enclosure,
                $this->escape
            );
            if ($this->firstLineIsHeader && !$this->header) {
                $this->header = $this->file->fgetcsv();
            }
        }

        return $this->file;
    }

    /**
     * @param ContextInterface $context
     * @throws InvalidConfigurationException
     */
    protected function initializeFromContext(ContextInterface $context)
    {
        parent::initializeFromContext($context);

        if ($context->hasOption('delimiter')) {
            $this->delimiter = $context->getOption('delimiter');
        }

        if ($context->hasOption('enclosure')) {
            $this->enclosure = $context->getOption('enclosure');
        }

        if ($context->hasOption('escape')) {
            $this->escape = $context->getOption('escape');
        }

        if ($context->hasOption('firstLineIsHeader')) {
            $this->firstLineIsHeader = (bool)$context->getOption('firstLineIsHeader');
        }

        if ($context->hasOption('header')) {
            $this->header = $context->getOption('header');
        }
    }
}
