<?php

namespace Oro\Bundle\AddressBundle\Form\Type;

use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractType;

class AddressCollectionType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setNormalizers(
            array(
                'options' => function (Options $options, $options) {
                    if (!$options) {
                        $options = array();
                    }
                    $options['single_form'] = false;
                    return $options;
                }
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'oro_item_collection';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'oro_address_collection';
    }
}
