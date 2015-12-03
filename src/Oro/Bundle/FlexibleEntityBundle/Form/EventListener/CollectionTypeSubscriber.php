<?php

namespace Oro\Bundle\FlexibleEntityBundle\Form\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\FlexibleEntityBundle\Entity\Collection;
use Oro\Bundle\FlexibleEntityBundle\Entity\Mapping\AbstractEntityFlexibleValue;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractFlexibleValue;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;

use Symfony\Component\Form\FormEvents;

/**
 * Collection type subscriber
 */
class CollectionTypeSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::POST_SUBMIT     => 'postBind',
            FormEvents::PRE_SET_DATA  => 'preSet',
        );
    }

    /**
     * Pre set empty collection elements
     *
     * @param FormEvent $event
     */
    public function preSet(FormEvent $event)
    {
        $data = $event->getData();

        if ($data instanceof AbstractEntityFlexibleValue) {
            /** @var ArrayCollection $collection */
            $collection = $data->getCollections();
            if ($collection->isEmpty()) {
                $collection->add(new Collection());
            }
        }
    }

    /**
     * Removes empty collection elements
     *
     * @param FormEvent $event
     */
    public function postBind(FormEvent $event)
    {
        $data = $event->getData();


        if ($data instanceof AbstractEntityFlexibleValue) {
            /** @var ArrayCollection $collection */
            $collection = $data->getCollections();
            foreach ($collection as $item) {
                if ($item == null || $item->__toString() == '') {
                    $collection->removeElement($item);
                }
            }
        }
    }
}
