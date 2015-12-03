<?php

namespace Oro\Bundle\OrganizationBundle\Form\Extension;

use Oro\Bundle\OrganizationBundle\Entity\BusinessUnit;
use Oro\Bundle\OrganizationBundle\Entity\Manager\BusinessUnitManager;
use Oro\Bundle\OrganizationBundle\Event\RecordOwnerDataListener;
use Oro\Bundle\OrganizationBundle\Form\Type\BusinessUnitType;
use Oro\Bundle\OrganizationBundle\Form\Type\OwnershipType;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadataProvider;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class FormTypeExtension extends AbstractTypeExtension
{
    /**
     * @var SecurityContextInterface
     */
    protected $securityContext;

    /**
     * @var OwnershipMetadataProvider
     */
    protected $ownershipMetadataProvider;

    /**
     * @var BusinessUnitManager
     */
    protected $manager;

    /**
     * @var SecurityFacade
     */
    protected $securityFacade;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var string
     */
    protected $fieldName;

    protected $fieldLabel = 'Owner';

    protected $assignIsGranted;

    /**
     * Constructor
     *
     * @param SecurityContextInterface $securityContext
     * @param OwnershipMetadataProvider $ownershipMetadataProvider
     * @param BusinessUnitManager $manager
     * @param SecurityFacade $securityFacade
     * @param TranslatorInterface $translator
     */
    public function __construct(
        SecurityContextInterface $securityContext,
        OwnershipMetadataProvider $ownershipMetadataProvider,
        BusinessUnitManager $manager,
        SecurityFacade $securityFacade,
        TranslatorInterface $translator
    ) {
        $this->securityContext = $securityContext;
        $this->ownershipMetadataProvider = $ownershipMetadataProvider;
        $this->manager = $manager;
        $this->securityFacade = $securityFacade;
        $this->translator = $translator;
        $this->fieldName = RecordOwnerDataListener::OWNER_FIELD_NAME;
    }

    /**
     * Returns the name of the type being extended.
     *
     * @return string The name of the type being extended
     */
    public function getExtendedType()
    {
        return 'form';
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     * @throws \LogicException when getOwner method isn't implemented for entity with ownership type
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $dataClassName = $builder->getForm()->getConfig()->getDataClass();
        if (!$dataClassName) {
            return;
        }
        if (!$this->securityContext->getToken()) {
            return;
        }
        $user = $this->securityContext->getToken()->getUser();
        if (!$user || is_string($user)) {
            return;
        }
        $metadata = $this->ownershipMetadataProvider->getMetadata($dataClassName);
        if ($metadata->hasOwner()) {
            if (!method_exists($dataClassName, 'getOwner')) {
                throw new \LogicException(
                    sprintf('Method getOwner must be implemented for %s entity.', $dataClassName)
                );
            }

            /**
             * TODO: Implement object-based assign check after access levels are supported
             */
            $this->assignIsGranted = $this->securityFacade->isGranted('ASSIGN', 'entity:' . $dataClassName);

            /**
             * Adding listener to hide owner field for update pages
             * if assign permission is not granted
             */
            $builder->addEventListener(
                FormEvents::POST_SET_DATA,
                array($this, 'postSetData')
            );

            if ($metadata->isUserOwned() && $this->assignIsGranted) {
                $this->addUserOwnerField($builder);
            } elseif ($metadata->isBusinessUnitOwned()) {
                $this->addBusinessUnitOwnerField($builder, $user, $dataClassName);
            } elseif ($metadata->isOrganizationOwned()) {
                $this->addOrganizationOwnerField($builder, $user);
            }
        }
    }

    /**
     * Process form after data is set and remove/disable owner field depending on permissions
     *
     * @param FormEvent $event
     */
    public function postSetData(FormEvent $event)
    {
        $form = $event->getForm();
        if ($form->getParent()) {
            return;
        }
        $entity = $event->getData();

        if (is_object($entity)
            && $entity->getId()
            && $form->has($this->fieldName)
            && !$this->assignIsGranted
        ) {
            $owner = $form->get($this->fieldName)->getData();
            $form->remove($this->fieldName);
            $form->add(
                $this->fieldName,
                'text',
                array(
                    'disabled' => true,
                    'data' => $owner ? $owner->getName() : '',
                    'mapped' => false,
                    'required' => false,
                    'label' => $this->fieldLabel
                )
            );
        }
    }

    /**
     * @param FormBuilderInterface $builder
     */
    protected function addUserOwnerField(FormBuilderInterface $builder)
    {
        /**
         * Showing user owner box for entities with owner type USER if assign permission is
         * granted.
         */
        if ($this->assignIsGranted) {
            $builder->add(
                $this->fieldName,
                'oro_user_select',
                array(
                    'required' => true,
                    'constraints' => array(new NotBlank())
                )
            );
        }
    }

    /**
     * @param FormBuilderInterface $builder
     * @param User $user
     * @param string $className
     */
    protected function addBusinessUnitOwnerField(FormBuilderInterface $builder, User $user, $className)
    {
        /**
         * Owner field is required for all entities except business unit
         */
        $validation = array('required' => false);
        /**
         * TODO: Replace this check with class names check without instances
         */
        if (!new $className instanceof BusinessUnit) {
            $validation = array(
                'constraints' => array(new NotBlank()),
                'required' => true,
            );
        } else {
            $this->fieldLabel = 'Parent';
        }

        if ($this->assignIsGranted) {
            /**
             * If assign permission is granted, showing all available business units
             */
            $businessUnits = $this->getTreeOptions($this->manager->getBusinessUnitsTree());
            $builder->add(
                $this->fieldName,
                'oro_business_unit_tree_select',
                array_merge(
                    array(
                        'empty_value' => $this->translator->trans('oro.business_unit.form.choose_business_user'),
                        'choices' => $businessUnits,
                        'mapped' => true,
                        'attr' => array('is_safe' => true),
                        'label' => $this->fieldLabel
                    ),
                    $validation
                )
            );
        } else {
            $businessUnits = $user->getBusinessUnits();
            if (count($businessUnits)) {
                $builder->add(
                    $this->fieldName,
                    'entity',
                    array_merge(
                        array(
                            'class' => 'OroOrganizationBundle:BusinessUnit',
                            'property' => 'name',
                            'choices' => $businessUnits,
                            'mapped' => true,
                            'label' => $this->fieldLabel
                        ),
                        $validation
                    )
                );
            }
        }
    }

    /**
     * @param FormBuilderInterface $builder
     * @param User $user
     */
    protected function addOrganizationOwnerField(FormBuilderInterface $builder, User $user)
    {
        $fieldOptions = array(
            'class' => 'OroOrganizationBundle:Organization',
            'property' => 'name',
            'mapped' => true,
            'required' => true,
            'constraints' => array(new NotBlank())
        );
        if (!$this->assignIsGranted) {
            $organizations = array();
            $bu = $user->getBusinessUnits();
            /** @var $businessUnit BusinessUnit */
            foreach ($bu as $businessUnit) {
                $organizations[] = $businessUnit->getOrganization();
            }
            $fieldOptions['choices'] = $organizations;
        }
        $builder->add($this->fieldName, 'entity', $fieldOptions);
    }

    /**
     * Prepare choice options for a hierarchical select
     *
     * @param $options
     * @param int $level
     * @return array
     */
    protected function getTreeOptions($options, $level = 0)
    {
        $choices = array();
        $blanks = str_repeat("&nbsp;&nbsp;&nbsp;", $level);
        foreach ($options as $option) {
            $choices += array($option['id'] => $blanks . $option['name']);
            if (isset($option['children'])) {
                $choices += $this->getTreeOptions($option['children'], $level + 1);
            }
        }

        return $choices;
    }
}
