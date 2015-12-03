<?php

namespace Oro\Bundle\FlexibleEntityBundle\EventListener;

use Doctrine\Common\EventSubscriber;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Oro\Bundle\FlexibleEntityBundle\Entity\Repository\AttributeRepository;

use Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttribute;

/**
 * This listener is used to listen to insert or delete
 * event from Doctrine to purge attribute list cache
 */
class AttributeCacheListener implements EventSubscriber
{
    /**
     * {@inheritDoc}
     */
    public function getSubscribedEvents()
    {
        return array('onFlush');
    }

    public function onFlush(OnFlushEventArgs $eventArgs)
    {
        $entityManager = $eventArgs->getEntityManager();
        $cacheDriver = $entityManager->getConfiguration()->getResultCacheImpl();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof AbstractAttribute) {
                $cacheDriver->delete(AttributeRepository::getAttributesListCacheId($entity->getEntityType()));
                return;
            }
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof AbstractAttribute) {
                $cacheDriver->delete(AttributeRepository::getAttributesListCacheId($entity->getEntityType()));
                return;
            }
        }
    }
}
