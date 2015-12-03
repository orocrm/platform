<?php

namespace Oro\Bundle\FlexibleEntityBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Oro\Bundle\FlexibleEntityBundle\Entity\Mapping\AbstractEntityFlexible;

/**
 * Aims to inject available attributes into a flexible entity
 */
class AddAttributesListener implements EventSubscriber
{
    /**
     * Specifies the list of events to listen
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return array(
            'postLoad'
        );
    }

    /**
     * After load
     * @param LifecycleEventArgs $args
     */
    public function postLoad(LifecycleEventArgs $args)
    {
        $flexible = $args->getEntity();
        $em       = $args->getEntityManager();

        if ($flexible instanceof AbstractEntityFlexible) {

            $metadata               = $em->getMetadataFactory()->getLoadedMetadata();
            $entityClass            = ClassUtils::getRealClass(get_class($flexible));
            $flexibleMetadata       = $metadata[$entityClass];
            $flexibleAssociations   = $flexibleMetadata->getAssociationMappings();
            $toValueAssociation     = $flexibleAssociations['values'];
            $valueClass             = $toValueAssociation['targetEntity'];

            $valueMetadata          = $metadata[$valueClass];
            $valueAssociations      = $valueMetadata->getAssociationMappings();
            $toAttributeAssociation = $valueAssociations['attribute'];
            $attributeClass         = $toAttributeAssociation['targetEntity'];

            $codeToAttributeData = $em->getRepository($attributeClass)->getCodeToAttributes($entityClass);
            $flexible->setAllAttributes($codeToAttributeData);
            $flexible->setValueClass($valueClass);
        }
    }
}
