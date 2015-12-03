<?php

namespace Oro\Bundle\FlexibleEntityBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;

/**
 * Email type
 */
class EmailType extends CollectionItemAbstract
{
    const TYPE_CORPORATE = 1;
    const TYPE_PERSONAL  = 2;


    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('id', 'hidden');
        $builder->add(
            'data',
            'email'
        );
        $builder->add(
            'type',
            'choice',
            array(
                'empty_value'   => 'Choose email type...',
                'empty_data'    => null,
                'choice_list'   => new ChoiceList(
                    array_keys(self::getTypesArray()),
                    array_values(self::getTypesArray())
                ),
                'attr' => array ('class' => 'oro-multiselect')
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTypesArray()
    {
        return array(
            self::TYPE_CORPORATE => 'Сorporate email',
            self::TYPE_PERSONAL  => 'Personal email'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'oro_flexibleentity_email';
    }
}
