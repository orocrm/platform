<?php

namespace Oro\Bundle\FlexibleEntityBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;

use Oro\Bundle\FlexibleEntityBundle\AttributeType\AbstractAttributeType;

use Oro\Bundle\FlexibleEntityBundle\AttributeType\AttributeTypeFactory;
use Oro\Bundle\FlexibleEntityBundle\Entity\Repository\FlexibleEntityRepository;
use Oro\Bundle\FlexibleEntityBundle\Event\FilterAttributeEvent;
use Oro\Bundle\FlexibleEntityBundle\Event\FilterFlexibleEvent;
use Oro\Bundle\FlexibleEntityBundle\Event\FilterFlexibleValueEvent;
use Oro\Bundle\FlexibleEntityBundle\Exception\FlexibleConfigurationException;
use Oro\Bundle\FlexibleEntityBundle\FlexibleEntityEvents;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttribute;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttributeOption;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttributeOptionValue;
use Oro\Bundle\FlexibleEntityBundle\Model\Behavior\ScopableInterface;
use Oro\Bundle\FlexibleEntityBundle\Model\Behavior\TranslatableInterface;
use Oro\Bundle\FlexibleEntityBundle\Model\FlexibleInterface;
use Oro\Bundle\FlexibleEntityBundle\Model\FlexibleValueInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Flexible object manager, allow to use flexible entity in storage agnostic way
 */
class FlexibleManager implements TranslatableInterface, ScopableInterface
{
    /**
     * @var string
     */
    protected $flexibleName;

    /**
     * Flexible entity config
     * @var array
     */
    protected $flexibleConfig;

    /**
     * @var ObjectManager $storageManager
     */
    protected $storageManager;

    /**
     * @var FlexibleEntityRepository
     */
    protected $flexibleRepository;

    /**
     * @var EventDispatcherInterface $eventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var AttributeTypeFactory $attributeTypeFactory
     */
    protected $attributeTypeFactory;

    /**
     * @var array $attributeTypes
     */
    protected $attributeTypes;

    /**
     * Locale code (from config or choose by user)
     * @var string
     */
    protected $locale;

    /**
     * Scope code (from config or choose by user)
     * @var string
     */
    protected $scope;

    /**
     * Constructor
     *
     * @param string                   $flexibleName         Entity name
     * @param array                    $flexibleConfig       Global flexible entities configuration array
     * @param ObjectManager            $storageManager       Storage manager
     * @param EventDispatcherInterface $eventDispatcher      Event dispatcher
     * @param AttributeTypeFactory     $attributeTypeFactory Attribute type factory
     */
    public function __construct(
        $flexibleName,
        $flexibleConfig,
        ObjectManager $storageManager,
        EventDispatcherInterface $eventDispatcher,
        AttributeTypeFactory $attributeTypeFactory
    ) {
        $this->flexibleName         = $flexibleName;
        $this->flexibleConfig       = $flexibleConfig['entities_config'][$flexibleName];
        $this->storageManager       = $storageManager;
        $this->eventDispatcher      = $eventDispatcher;
        $this->attributeTypeFactory = $attributeTypeFactory;
        $this->attributeTypes       = array();

        $this->flexibleRepository   = $storageManager->getRepository($this->flexibleName);
        $this->flexibleRepository->setFlexibleConfig($this->flexibleConfig);
    }

    /**
     * Get flexible entity config
     *
     * @return array
     */
    public function getFlexibleConfig()
    {
        return $this->flexibleConfig;
    }

    /**
     * Get flexible init mode
     *
     * @return array
     */
    public function getFlexibleInitMode()
    {
        return $this->flexibleConfig['flexible_init_mode'];
    }

    /**
     * Return asked locale code or default one
     *
     * @return string
     */
    public function getLocale()
    {
        if (!$this->locale) {
            // use default locale
            $this->locale = $this->flexibleConfig['default_locale'];
        }

        return $this->locale;
    }

    /**
     * Set locale code, to force it
     *
     * @param string $code
     *
     * @return FlexibleManager
     */
    public function setLocale($code)
    {
        $this->locale = $code;
        $this->flexibleRepository->setLocale($code);

        return $this;
    }

    /**
     * Return asked scope code or default one
     *
     * @return string
     */
    public function getScope()
    {
        if (!$this->scope) {
            // use default scope
            $this->scope = $this->flexibleConfig['default_scope'];
        }

        return $this->scope;
    }

    /**
     * Set scope code, to force it
     *
     * @param string $code
     *
     * @return FlexibleManager
     */
    public function setScope($code)
    {
        $this->scope = $code;
        $this->flexibleRepository->setScope($code);

        return $this;
    }


    /**
     * Get object manager
     *
     * @return ObjectManager
     */
    public function getStorageManager()
    {
        return $this->storageManager;
    }

    /**
     * Return implementation class that can be use to instanciate
     *
     * @return string
     */
    public function getFlexibleName()
    {
        return $this->flexibleName;
    }

    /**
     * Return class name that can be used to get the repository or instance
     *
     * @return string
     */
    public function getAttributeName()
    {
        return $this->flexibleConfig['attribute_class'];
    }

    /**
     * Return class name that can be used to get the repository or instance
     *
     * @return string
     */
    public function getAttributeOptionName()
    {
        return $this->flexibleConfig['attribute_option_class'];
    }

    /**
     * Return class name that can be used to get the repository or instance
     *
     * @return string
     */
    public function getAttributeOptionValueName()
    {
        return $this->flexibleConfig['attribute_option_value_class'];
    }

    /**
     * Return class name that can be used to get the repository or instance
     *
     * @return string
     */
    public function getFlexibleValueName()
    {
        return $this->flexibleConfig['flexible_value_class'];
    }

    /**
     * Return related repository
     *
     * @return FlexibleEntityRepository
     */
    public function getFlexibleRepository()
    {
        return $this->flexibleRepository;
    }

    /**
     * Return related repository
     *
     * @return ObjectRepository
     */
    public function getAttributeRepository()
    {
        return $this->storageManager->getRepository($this->getAttributeName());
    }

    /**
     * Return related repository
     *
     * @return ObjectRepository
     */
    public function getAttributeOptionRepository()
    {
        return $this->storageManager->getRepository($this->getAttributeOptionName());
    }

    /**
     * Return related repository
     *
     * @return ObjectRepository
     */
    public function getAttributeOptionValueRepository()
    {
        return $this->storageManager->getRepository($this->getAttributeOptionValueName());
    }

    /**
     * Return related repository
     *
     * @return ObjectRepository
     */
    public function getFlexibleValueRepository()
    {
        return $this->storageManager->getRepository($this->getFlexibleValueName());
    }

    /**
     * Return a new attribute instance
     *
     * @param string $type attribute type
     *
     * @return AbstractAttribute
     *
     * @throws FlexibleConfigurationException If type is not useable for the flexible entity
     */
    public function createAttribute($type = null)
    {
        $class = $this->getAttributeName();
        $attribute = new $class();
        $attribute->setEntityType($this->getFlexibleName());

        $attribute->setBackendStorage(AbstractAttributeType::BACKEND_STORAGE_ATTRIBUTE_VALUE);
        if ($type) {
            if (!in_array($type, $this->getAttributeTypes())) {
                throw new FlexibleConfigurationException(
                    sprintf(
                        'Attribute "%s" type is not useable for the flexible entity "%s"',
                        $type,
                        $this->flexibleName
                    )
                );
            }
            $attributeType = $this->getAttributeTypeFactory()->get($type);
            $attribute->setBackendType($attributeType->getBackendType());
            $attribute->setAttributeType($attributeType->getName());
        }

        $event = new FilterAttributeEvent($this, $attribute);
        $this->eventDispatcher->dispatch(FlexibleEntityEvents::CREATE_ATTRIBUTE, $event);

        return $attribute;
    }

    /**
     * Return a new instance
     *
     * @return AbstractAttributeOption
     */
    public function createAttributeOption()
    {
        $class = $this->getAttributeOptionName();
        $object = new $class();
        $object->setLocale($this->getLocale());

        return $object;
    }

    /**
     * Return a new instance
     *
     * @return AbstractAttributeOptionValue
     */
    public function createAttributeOptionValue()
    {
        $class = $this->getAttributeOptionValueName();
        $object = new $class();
        $object->setLocale($this->getLocale());

        return $object;
    }

    /**
     * Return a new instance
     *
     * @return FlexibleInterface
     */
    public function createFlexible()
    {
        $class = $this->getFlexibleName();
        $attributeClass = $this->getAttributeName();
        $valueClass = $this->getFlexibleValueName();

        $flexible = new $class();
        $flexible->setLocale($this->getLocale());
        $flexible->setScope($this->getScope());

        $codeToAttributeData = $this->getStorageManager()->getRepository($attributeClass)->getCodeToAttributes($class);
        $flexible->setAllAttributes($codeToAttributeData);
        $flexible->setValueClass($valueClass);

        $event = new FilterFlexibleEvent($this, $flexible);
        $this->eventDispatcher->dispatch(FlexibleEntityEvents::CREATE_FLEXIBLE, $event);

        return $flexible;
    }

    /**
     * Return a new instance
     *
     * @return FlexibleValueInterface
     */
    public function createFlexibleValue()
    {
        $class = $this->getFlexibleValueName();
        $value = new $class();
        // dispatch event
        $event = new FilterFlexibleValueEvent($this, $value);
        $this->eventDispatcher->dispatch(FlexibleEntityEvents::CREATE_VALUE, $event);

        return $value;
    }

    /**
     * Get attribute type factory
     *
     * @return AttributeTypeFactory
     */
    public function getAttributeTypeFactory()
    {
        return $this->attributeTypeFactory;
    }

    /**
     * Add useable attribute type for this flexible entity
     *
     * @param string $type
     *
     * @return FlexibleManager
     */
    public function addAttributeType($type)
    {
        $this->attributeTypes[]= $type;

        return $this;
    }

    /**
     * Set the useable attribute types for this flexible entity
     *
     * @param array $types
     *
     * @return FlexibleManager
     */
    public function setAttributeTypes($types)
    {
        $this->attributeTypes = $types;

        return $this;
    }

    /**
     * Get attribute types aliases
     *
     * @return array
     */
    public function getAttributeTypes()
    {
        return $this->attributeTypes;
    }
}
