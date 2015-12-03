<?php

namespace Oro\Bundle\FlexibleEntityBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractFlexible;
use Oro\Bundle\FlexibleEntityBundle\Model\Behavior\TimestampableInterface;

/**
 * Aims to add timestambable behavior
 */
class TimestampableListener implements EventSubscriber
{
    /**
     * Specifies the list of events to listen
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return array(
            'prePersist',
            'preUpdate'
        );
    }

    /**
     * Before insert
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if ($entity instanceof TimestampableInterface) {
            $entity->setCreated(new \DateTime('now', new \DateTimeZone('UTC')));
            $entity->setUpdated(new \DateTime('now', new \DateTimeZone('UTC')));
        }
    }

    /**
     * Before update
     * @param LifecycleEventArgs $args
     */
    public function preUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof \Oro\Bundle\FlexibleEntityBundle\Entity\Mapping\AbstractEntityFlexibleValue) {
            $flexible = $entity->getEntity();
            if ($flexible !== null) {
                $this->updateFlexibleFields($args->getEntityManager(), $flexible, array('updated'));
            }
        }

        if ($entity instanceof \Oro\Bundle\FlexibleEntityBundle\Model\Behavior\TimestampableInterface) {
            $entity->setUpdated(new \DateTime('now', new \DateTimeZone('UTC')));
        }
    }

    /**
     * Update flexible fields when a value is updated
     *
     * @param ObjectManager $om
     * @param Flexible      $flexible
     * @param array         $fields
     */
    protected function updateFlexibleFields(ObjectManager $om, AbstractFlexible $flexible, $fields)
    {
        $meta = $om->getClassMetadata(get_class($flexible));
        $uow  = $om->getUnitOfWork();
        $now  = new \DateTime('now', new \DateTimeZone('UTC'));
        $changes = array();
        foreach ($fields as $field) {
            $changes[$field]= array(null, $now);
        }
        $uow->scheduleExtraUpdate($flexible, $changes);
    }
}
