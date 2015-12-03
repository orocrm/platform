<?php

namespace Oro\Bundle\BatchBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for step element configuration
 *
 */
class StepElementConfigurationType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $factory = $builder->getFormFactory();

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($factory) {
                $form   = $event->getForm();
                $stepElement = $event->getData();

                foreach ($stepElement->getConfigurationFields() as $field => $config) {
                    $config = array_merge(
                        array(
                            'type'    => 'text',
                            'options' => array(),
                        ),
                        $config
                    );
                    $options = array_merge(
                        array(
                            'auto_initialize' => false,
                            'required'        => false,
                            'label'           => sprintf(
                                'oro_batch.%s.%s.label',
                                $stepElement->getName(),
                                $field
                            ),
                            'help' => sprintf(
                                'oro_batch.%s.%s.help',
                                $stepElement->getName(),
                                $field
                            )
                        ),
                        $config['options']
                    );

                    $form->add($factory->createNamed($field, $config['type'], null, $options));
                }
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'Oro\\Bundle\\BatchBundle\\Item\\AbstractConfigurableStepElement',
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'oro_batch_step_element_configuration';
    }
}
