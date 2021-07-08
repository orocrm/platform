<?php

namespace Oro\Bundle\EmailBundle\Exception;

use Oro\Bundle\EmailBundle\Model\EmailTemplateCriteria;

/**
 * This exception is thrown when email template's could not be compiled using twig.
 */
class EmailTemplateCompilationException extends EmailTemplateException
{
    /**
     * @param EmailTemplateCriteria $criteria
     */
    public function __construct(EmailTemplateCriteria $criteria)
    {
        $message = sprintf(
            'Compilation error during rendering template "%s". Please look at the log file for more details.',
            $criteria->getName()
        );

        if ($criteria->getEntityName()) {
            $message = sprintf('%s for "%s" entity', $message, $criteria->getEntityName());
        }

        parent::__construct($message);
    }
}
