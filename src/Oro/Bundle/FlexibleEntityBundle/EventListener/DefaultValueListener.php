<?php

namespace Oro\Bundle\FlexibleEntityBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Oro\Bundle\FlexibleEntityBundle\Model\FlexibleValueInterface;

/**
 * Aims to add  default value data from attribute if defined
 */
class DefaultValueListener implements EventSubscriber
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
     *
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $this->defineDefaultValue($args);
    }

    /**
     * Before update
     *
     * @param LifecycleEventArgs $args
     */
    public function preUpdate(LifecycleEventArgs $args)
    {
        $this->defineDefaultValue($args);
    }

    /**
     * If Value is empty or null and has
     * @param LifecycleEventArgs $args
     */
    protected function defineDefaultValue(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if ($entity instanceof FlexibleValueInterface) {
            // check that value has no data and attribute has defined default value
            if (!$entity->hasData() and !is_null($entity->getAttribute()->getDefaultValue())) {
                $entity->setData($entity->getAttribute()->getDefaultValue());
            }
        }
    }
}
