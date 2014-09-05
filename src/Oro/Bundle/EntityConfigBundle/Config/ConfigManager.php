<?php

namespace Oro\Bundle\EntityConfigBundle\Config;

use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\EntityManager;

use Metadata\MetadataFactory;

use Symfony\Component\EventDispatcher\EventDispatcher;

use Oro\Bundle\EntityConfigBundle\Exception\LogicException;
use Oro\Bundle\EntityConfigBundle\Exception\RuntimeException;

use Oro\Bundle\EntityConfigBundle\Audit\AuditManager;
use Oro\Bundle\EntityConfigBundle\DependencyInjection\Utils\ServiceLink;

use Oro\Bundle\EntityConfigBundle\Metadata\EntityMetadata;
use Oro\Bundle\EntityConfigBundle\Metadata\FieldMetadata;
use Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer;

use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderBag;

use Oro\Bundle\EntityConfigBundle\Entity\EntityConfigModel;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Entity\AbstractConfigModel;

use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\ConfigIdInterface;

use Oro\Bundle\EntityConfigBundle\Event\EntityConfigEvent;
use Oro\Bundle\EntityConfigBundle\Event\FieldConfigEvent;
use Oro\Bundle\EntityConfigBundle\Event\PersistConfigEvent;
use Oro\Bundle\EntityConfigBundle\Event\RenameFieldEvent;
use Oro\Bundle\EntityConfigBundle\Event\Events;

use Oro\Bundle\EntityConfigBundle\Tools\ConfigHelper;

use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;

/**
 * @SuppressWarnings(PHPMD)
 */
class ConfigManager
{
    /**
     * @var MetadataFactory
     */
    protected $metadataFactory;

    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var ConfigCache
     */
    protected $cache;

    /**
     * @var AuditManager
     */
    protected $auditManager;

    /**
     * @var ConfigModelManager
     */
    protected $modelManager;

    /**
     * @var ServiceLink
     */
    protected $providerBag;

    /**
     * key = a string returned by $this->buildConfigKey
     *
     * @var ConfigInterface[]
     */
    protected $localCache;

    /**
     * key = a string returned by $this->buildConfigKey
     *
     * @var ConfigInterface[]
     */
    protected $persistConfigs;

    /**
     * key = a string returned by $this->buildConfigKey
     *
     * @var ConfigInterface[]
     */
    protected $originalConfigs;

    /**
     * key = a string returned by $this->buildConfigKey
     * value = an array
     *
     * @var array
     */
    protected $configChangeSets;

    /**
     * @param MetadataFactory    $metadataFactory
     * @param EventDispatcher    $eventDispatcher
     * @param ServiceLink        $providerBagLink
     * @param ConfigModelManager $modelManager
     * @param AuditManager       $auditManager
     */
    public function __construct(
        MetadataFactory $metadataFactory,
        EventDispatcher $eventDispatcher,
        ServiceLink $providerBagLink,
        ConfigModelManager $modelManager,
        AuditManager $auditManager
    ) {
        $this->metadataFactory = $metadataFactory;
        $this->eventDispatcher = $eventDispatcher;

        $this->providerBag      = $providerBagLink;
        $this->localCache       = [];
        $this->persistConfigs   = [];
        $this->originalConfigs  = [];
        $this->configChangeSets = [];

        $this->modelManager = $modelManager;
        $this->auditManager = $auditManager;
    }

    /**
     * @param ConfigCache $cache
     */
    public function setCache(ConfigCache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->modelManager->getEntityManager();
    }

    /**
     * @return ConfigProviderBag
     */
    public function getProviderBag()
    {
        return $this->providerBag->getService();
    }

    /**
     * @return ConfigProvider[]|ArrayCollection
     */
    public function getProviders()
    {
        return $this->getProviderBag()->getProviders();
    }

    /**
     * @param $scope
     * @return ConfigProvider
     */
    public function getProvider($scope)
    {
        return $this->getProviderBag()->getProvider($scope);
    }

    /**
     * @return EventDispatcher
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * @param $className
     * @return EntityMetadata|null
     */
    public function getEntityMetadata($className)
    {
        return class_exists($className)
            ? $this->metadataFactory->getMetadataForClass($className)
            : null;
    }

    /**
     * @param $className
     * @param $fieldName
     * @return null|FieldMetadata
     */
    public function getFieldMetadata($className, $fieldName)
    {
        $metadata = $this->getEntityMetadata($className);

        return $metadata && isset($metadata->propertyMetadata[$fieldName])
            ? $metadata->propertyMetadata[$fieldName]
            : null;
    }

    /**
     * @param string $className
     * @param string $fieldName
     * @return bool
     */
    public function hasConfig($className, $fieldName = null)
    {
        if (!$this->modelManager->checkDatabase()) {
            return false;
        }

        $result = $this->cache->getConfigurable($className, $fieldName);
        if (null === $result) {
            $model  = $fieldName
                ? $this->modelManager->findFieldModel($className, $fieldName)
                : $this->modelManager->findEntityModel($className);
            $result = (null !== $model);

            $this->cache->setConfigurable($result, $className, $fieldName);
        }

        return $result;
    }

    /**
     * @param ConfigIdInterface $configId
     * @throws RuntimeException
     * @throws LogicException
     * @return ConfigInterface
     */
    public function getConfig(ConfigIdInterface $configId)
    {
        if ($configId instanceof EntityConfigId && !$configId->getClassName()) {
            $config = new Config($configId);
            $config->setValues(
                $this->getEntityDefaultValues($this->getProvider($configId->getScope()))
            );

            return $config;
        }

        $configKey = $this->buildConfigKey($configId);
        if (isset($this->localCache[$configKey])) {
            return $this->localCache[$configKey];
        }

        if (!$this->modelManager->checkDatabase()) {
            throw $this->createDatabaseNotSyncedException();
        }

        if (!$this->hasConfig($configId->getClassName())) {
            throw new RuntimeException(sprintf('Entity "%s" is not configurable', $configId->getClassName()));
        }

        $config = null !== $this->cache
            ? $this->cache->loadConfigFromCache($configId)
            : null;

        if (!$config) {
            $model = $configId instanceof FieldConfigId
                ? $this->modelManager->getFieldModel($configId->getClassName(), $configId->getFieldName())
                : $this->modelManager->getEntityModel($configId->getClassName());

            $config = new Config($configId);
            $config->setValues($model->toArray($configId->getScope()));

            if (null !== $this->cache) {
                $this->cache->putConfigInCache($config);
            }
        }

        // local cache
        $this->localCache[$configKey] = $config;
        // for calculate change set
        $this->originalConfigs[$configKey] = clone $config;

        return $config;
    }

    /**
     * @param string $scope
     * @param string $className
     * @param bool   $withHidden Set true if you need all configurable entities,
     *                           including entities marked as mode="hidden"
     * @return array
     */
    public function getIds($scope, $className = null, $withHidden = false)
    {
        if (!$this->modelManager->checkDatabase()) {
            return [];
        }

        if ($withHidden) {
            $models = $this->modelManager->getModels($className);
        } else {
            $models = array_filter(
                $this->modelManager->getModels($className),
                function (AbstractConfigModel $model) {
                    return $model->getMode() != ConfigModelManager::MODE_HIDDEN;
                }
            );
        }

        return array_map(
            function ($model) use ($scope) {
                return $this->getConfigIdByModel($model, $scope);
            },
            $models
        );
    }

    /**
     * @param      $scope
     * @param      $className
     * @param null $fieldName
     * @return ConfigIdInterface
     * @throws LogicException if a database is not synced
     * @throws RuntimeException if a model was not found
     */
    public function getId($scope, $className, $fieldName = null)
    {
        if ($fieldName) {
            // at first try to find a model in the local cache
            $configId  = new FieldConfigId($scope, $className, $fieldName);
            $configKey = $this->buildConfigKey($configId);
            if (isset($this->localCache[$configKey])) {
                // if found, use field config id from the local cache
                $configId = $this->localCache[$configKey]->getId();
            } else {
                // next try to find a model in the cache
                $config = null !== $this->cache
                    ? $this->cache->loadConfigFromCache($configId)
                    : null;
                if ($config) {
                    // if found, use field config id from the cache
                    $configId = $config->getId();
                    $this->localCache[$configKey] = $config;
                } else {
                    // if a cached model was not found use the model manager to get field config id
                    if (!$this->modelManager->checkDatabase()) {
                        throw $this->createDatabaseNotSyncedException();
                    }
                    $fieldModel = $this->modelManager->getFieldModel($className, $fieldName);
                    $configId   = new FieldConfigId(
                        $scope,
                        $className,
                        $fieldModel->getFieldName(),
                        $fieldModel->getType()
                    );
                }
            }

            return $configId;
        } else {
            return new EntityConfigId($scope, $className);
        }
    }

    /**
     * Clears entity config cache
     *
     * @param ConfigIdInterface|null $configId
     */
    public function clearCache(ConfigIdInterface $configId = null)
    {
        if ($configId) {
            if ($this->cache) {
                $this->cache->removeConfigFromCache($configId);
            }
            unset($this->localCache[$this->buildConfigKey($configId)]);
        } else {
            if ($this->cache) {
                $this->cache->removeAll();
            }
            $this->localCache = [];
        }
    }

    /**
     * Clears a cache of configurable entity flags
     */
    public function clearConfigurableCache()
    {
        $this->modelManager->clearCheckDatabase();
        if ($this->cache) {
            $this->cache->removeAllConfigurable();
        }
    }

    /**
     * @param ConfigInterface $config
     */
    public function persist(ConfigInterface $config)
    {
        $this->persistConfigs[$this->buildConfigKey($config->getId())] = $config;
    }

    /**
     * @param ConfigInterface $config
     * @return ConfigInterface
     */
    public function merge(ConfigInterface $config)
    {
        $configKey = $this->buildConfigKey($config->getId());
        if (isset($this->persistConfigs[$configKey])) {
            $persistValues = $this->persistConfigs[$configKey]->all();
            if (!empty($persistValues)) {
                $config->setValues(array_merge($persistValues, $config->all()));
            }
        }
        $this->persistConfigs[$configKey] = $config;

        return $config;
    }

    /**
     * Discards all unsaved changes and clears all related caches.
     */
    public function clear()
    {
        $this->clearCache();
        $this->clearConfigurableCache();

        $this->modelManager->clearCache();
        $this->getEntityManager()->clear();
    }

    public function flush()
    {
        $models = [];
        $this->prepareFlush($models);

        $this->auditManager->log();

        foreach ($models as $model) {
            $this->getEntityManager()->persist($model);
        }

        $this->getEntityManager()->flush();

        if ($this->cache) {
            $this->cache->removeAllConfigurable();
        }

        $this->persistConfigs   = [];
        $this->configChangeSets = [];
    }

    /**
     * @param array $models
     */
    protected function prepareFlush(&$models)
    {
        foreach ($this->persistConfigs as $config) {
            $this->calculateConfigChangeSet($config);

            $this->eventDispatcher->dispatch(Events::PRE_PERSIST_CONFIG, new PersistConfigEvent($config, $this));

            $configId = $config->getId();
            $configKey = $configId instanceof FieldConfigId
                ? sprintf('%s_%s', $configId->getClassName(), $configId->getFieldName())
                : $configId->getClassName();
            if (isset($models[$configKey])) {
                $model = $models[$configKey];
            } else {
                $model = $configId instanceof FieldConfigId
                    ? $this->modelManager->getFieldModel($configId->getClassName(), $configId->getFieldName())
                    : $this->modelManager->getEntityModel($configId->getClassName());

                $models[$configKey] = $model;
            }

            $indexedValues = $this->getProvider($config->getId()->getScope())
                ->getPropertyConfig()
                ->getIndexedValues($config->getId());
            $model->fromArray($config->getId()->getScope(), $config->all(), $indexedValues);

            if ($this->cache) {
                $this->cache->removeConfigFromCache($config->getId());
            }
        }

        if (count($this->persistConfigs) != count($this->configChangeSets)) {
            $this->prepareFlush($models);
        }
    }


    /**
     * @param ConfigInterface $config
     * @SuppressWarnings(PHPMD)
     */
    public function calculateConfigChangeSet(ConfigInterface $config)
    {
        $originConfigValue = [];
        $configKey         = $this->buildConfigKey($config->getId());
        if (isset($this->originalConfigs[$configKey])) {
            $originConfigValue = $this->originalConfigs[$configKey]->all();
        }

        foreach ($config->all() as $key => $value) {
            if (!isset($originConfigValue[$key])) {
                $originConfigValue[$key] = null;
            }
        }

        $diffNew = array_udiff_assoc(
            $config->all(),
            $originConfigValue,
            function ($a, $b) {
                return ($a == $b) ? 0 : 1;
            }
        );

        $diffOld = array_udiff_assoc(
            $originConfigValue,
            $config->all(),
            function ($a, $b) {
                return ($a == $b) ? 0 : 1;
            }
        );

        $diff = [];
        foreach ($diffNew as $key => $value) {
            $oldValue   = isset($diffOld[$key]) ? $diffOld[$key] : null;
            $diff[$key] = [$oldValue, $value];
        }


        if (!isset($this->configChangeSets[$configKey])) {
            $this->configChangeSets[$configKey] = [];
        }

        if (count($diff)) {
            $changeSet                          = array_merge($this->configChangeSets[$configKey], $diff);
            $this->configChangeSets[$configKey] = $changeSet;
        }
    }

    /**
     * @return ConfigInterface[]
     */
    public function getUpdateConfig()
    {
        return array_values($this->persistConfigs);
    }

    /**
     * @param ConfigInterface $config
     * @return array
     */
    public function getConfigChangeSet(ConfigInterface $config)
    {
        $configKey = $this->buildConfigKey($config->getId());

        return isset($this->configChangeSets[$configKey])
            ? $this->configChangeSets[$configKey]
            : [];
    }

    /**
     * Checks if the configuration model for the given class exists
     *
     * @param string $className
     * @return bool
     */
    public function hasConfigEntityModel($className)
    {
        return null !== $this->modelManager->findEntityModel($className);
    }

    /**
     * Checks if the configuration model for the given field exist
     *
     * @param string $className
     * @param string $fieldName
     * @return bool
     */
    public function hasConfigFieldModel($className, $fieldName)
    {
        return null !== $this->modelManager->findFieldModel($className, $fieldName);
    }

    /**
     * Gets a config model for the given entity
     *
     * @param string $className
     * @return EntityConfigModel|null
     */
    public function getConfigEntityModel($className)
    {
        return $this->modelManager->findEntityModel($className);
    }

    /**
     * Gets a config model for the given entity field
     *
     * @param string $className
     * @param string $fieldName
     * @return FieldConfigModel|null
     */
    public function getConfigFieldModel($className, $fieldName)
    {
        return $this->modelManager->findFieldModel($className, $fieldName);
    }

    /**
     * @param string|null $className
     * @param string|null $mode
     * @return EntityConfigModel
     */
    public function createConfigEntityModel($className = null, $mode = ConfigModelManager::MODE_DEFAULT)
    {
        if (empty($className)) {
            $entityModel = $this->modelManager->createEntityModel($className, $mode);
        } else {
            $entityModel = $this->modelManager->findEntityModel($className);
            if (null === $entityModel) {
                $entityModel = $this->modelManager->createEntityModel($className, $mode);
                $metadata    = $this->getEntityMetadata($className);
                foreach ($this->getProviders() as $provider) {
                    $configId = new EntityConfigId($provider->getScope(), $className);
                    $config   = $this->createConfig(
                        $configId,
                        $this->getEntityDefaultValues($provider, $className, $metadata)
                    );

                    $configKey = $this->buildConfigKey($config->getId());

                    // local cache
                    $this->localCache[$configKey] = $config;
                    // for calculate change set
                    $this->originalConfigs[$configKey] = clone $config;
                }

                $this->eventDispatcher->dispatch(
                    Events::NEW_ENTITY_CONFIG,
                    new EntityConfigEvent($className, $this)
                );
            }
        }

        return $entityModel;
    }

    /**
     * @param string $className
     * @param string $fieldName
     * @param string $fieldType
     * @param string $mode
     * @return FieldConfigModel
     */
    public function createConfigFieldModel(
        $className,
        $fieldName,
        $fieldType,
        $mode = ConfigModelManager::MODE_DEFAULT
    ) {
        $fieldModel = $this->modelManager->findFieldModel($className, $fieldName);
        if (null === $fieldModel) {
            $fieldModel = $this->modelManager->createFieldModel($className, $fieldName, $fieldType, $mode);
            $metadata   = $this->getFieldMetadata($className, $fieldName);
            foreach ($this->getProviders() as $provider) {
                $configId = new FieldConfigId($provider->getScope(), $className, $fieldName, $fieldType);
                $config   = $this->createConfig(
                    $configId,
                    $this->getFieldDefaultValues($provider, $className, $fieldName, $fieldType, $metadata)
                );

                $configKey = $this->buildConfigKey($config->getId());

                // local cache
                $this->localCache[$configKey] = $config;
                // for calculate change set
                $this->originalConfigs[$configKey] = clone $config;
            }

            $this->eventDispatcher->dispatch(
                Events::NEW_FIELD_CONFIG,
                new FieldConfigEvent($className, $fieldName, $this)
            );
        }

        return $fieldModel;
    }

    /**
     * @param string $className
     * @param bool   $force - if TRUE overwrite existing value from annotation
     *
     * @TODO: need handling for removed values
     */
    public function updateConfigEntityModel($className, $force = false)
    {
        // existing values for a custom entity must not be overridden
        if ($force && $this->isCustom($className)) {
            $force = false;
        }

        $metadata = $this->getEntityMetadata($className);
        foreach ($this->getProviders() as $provider) {
            $config        = $provider->getConfig($className);
            $defaultValues = $this->getEntityDefaultValues($provider, $className, $metadata);
            $hasChanges    = $this->updateConfigValues($config, $defaultValues, $force);
            if ($hasChanges) {
                $provider->persist($config);
            }
        }
        $this->eventDispatcher->dispatch(
            Events::UPDATE_ENTITY_CONFIG,
            new EntityConfigEvent($className, $this)
        );
    }

    /**
     * @param string $className
     * @param string $fieldName
     * @param bool   $force - if TRUE overwrite existing value from annotation
     *
     * @TODO: need handling for removed values
     */
    public function updateConfigFieldModel($className, $fieldName, $force = false)
    {
        // existing values for a custom field must not be overridden
        if ($force && $this->isCustom($className, $fieldName)) {
            $force = false;
        }

        $metadata = $this->getFieldMetadata($className, $fieldName);
        foreach ($this->getProviders() as $provider) {
            $config = $provider->getConfig($className, $fieldName);
            /** @var FieldConfigId $configId */
            $configId      = $config->getId();
            $defaultValues = $this->getFieldDefaultValues(
                $provider,
                $className,
                $fieldName,
                $configId->getFieldType(),
                $metadata
            );
            $hasChanges    = $this->updateConfigValues($config, $defaultValues, $force);
            if ($hasChanges) {
                $provider->persist($config);
            }
        }
        $this->eventDispatcher->dispatch(
            Events::UPDATE_FIELD_CONFIG,
            new FieldConfigEvent($className, $fieldName, $this)
        );
    }

    /**
     * Changes a type of a field
     *
     * @param string $className
     * @param string $fieldName
     * @param string $newFieldName
     * @return bool TRUE if the name was changed; otherwise, FALSE
     */
    public function changeFieldName($className, $fieldName, $newFieldName)
    {
        $result = $this->modelManager->changeFieldName($className, $fieldName, $newFieldName);
        if ($result) {
            $this->eventDispatcher->dispatch(
                Events::RENAME_FIELD,
                new RenameFieldEvent($className, $fieldName, $newFieldName, $this)
            );
            $providers = $this->getProviders();
            foreach ($providers as $provider) {
                /** @var FieldConfigId $newConfigId */
                $newConfigId = $this->getId($provider->getScope(), $className, $newFieldName);
                $newConfigKey = $this->buildConfigKey($newConfigId);
                $configId = new FieldConfigId(
                    $newConfigId->getScope(),
                    $newConfigId->getClassName(),
                    $fieldName,
                    $newConfigId->getFieldType()
                );
                $configKey = $this->buildConfigKey($configId);
                if (isset($this->localCache[$configKey])) {
                    $this->localCache[$newConfigKey] = $this->changeConfigFieldName(
                        $this->localCache[$configKey],
                        $newFieldName
                    );
                    unset($this->localCache[$configKey]);
                }
                if (isset($this->persistConfigs[$configKey])) {
                    $this->persistConfigs[$newConfigKey] = $this->changeConfigFieldName(
                        $this->persistConfigs[$configKey],
                        $newFieldName
                    );
                    unset($this->persistConfigs[$configKey]);
                }
                if (isset($this->originalConfigs[$configKey])) {
                    $this->originalConfigs[$newConfigKey] = $this->changeConfigFieldName(
                        $this->originalConfigs[$configKey],
                        $newFieldName
                    );
                    unset($this->originalConfigs[$configKey]);
                }
                if (isset($this->configChangeSets[$configKey])) {
                    $this->configChangeSets[$newConfigKey] = $this->configChangeSets[$configKey];
                    unset($this->configChangeSets[$configKey]);
                }
            }
        };

        return $result;
    }

    /**
     * Changes a type of a field
     *
     * @param string $className
     * @param string $fieldName
     * @param string $fieldType
     * @return bool TRUE if the type was changed; otherwise, FALSE
     */
    public function changeFieldType($className, $fieldName, $fieldType)
    {
        return $this->modelManager->changeFieldType($className, $fieldName, $fieldType);
    }

    /**
     * Gets config id for the given model
     *
     * @param EntityConfigModel|FieldConfigModel $model
     * @param string                             $scope
     * @return ConfigIdInterface
     */
    public function getConfigIdByModel($model, $scope)
    {
        if ($model instanceof FieldConfigModel) {
            return new FieldConfigId(
                $scope,
                $model->getEntity()->getClassName(),
                $model->getFieldName(),
                $model->getType()
            );
        } else {
            return new EntityConfigId($scope, $model->getClassName());
        }
    }

    /**
     * In case of FieldConfigId replaces OLD field name with given NEW one
     *
     * @param ConfigInterface $config
     * @param $newFieldName
     * @return Config|ConfigInterface
     */
    protected function changeConfigFieldName(ConfigInterface $config, $newFieldName)
    {
        $configId = $config->getId();
        if ($configId instanceof FieldConfigId) {
            $newConfigId = new FieldConfigId(
                $configId->getScope(),
                $configId->getClassName(),
                $newFieldName,
                $configId->getFieldType()
            );
            $newConfig = new Config($newConfigId);
            $newConfig->setValues($config->all());
            $config = $newConfig;
        }

        return $config;
    }

    /**
     * Creates an instance if Config class which stores configuration data for an object
     * which is represented by the given id.
     * The returned object is initialized with data specified $values argument.
     *
     * @param  ConfigIdInterface $configId
     * @param  array             $values An associative array contains configuration properties
     *                                   key = property name
     *                                   value = property value
     * @return Config
     */
    protected function createConfig(ConfigIdInterface $configId, array $values)
    {
        $config = new Config($configId);

        foreach ($values as $key => $value) {
            $config->set($key, $value);
        }

        $this->merge($config);

        return $config;
    }

    /**
     * Extracts entity default values from an annotation and config file
     *
     * @param ConfigProvider      $provider
     * @param string|null         $className
     * @param EntityMetadata|null $metadata
     * @return array
     */
    protected function getEntityDefaultValues(ConfigProvider $provider, $className = null, $metadata = null)
    {
        $defaultValues = [];

        $scope = $provider->getScope();

        // try to get default values from an annotation
        if ($metadata && isset($metadata->defaultValues[$scope])) {
            $defaultValues = $metadata->defaultValues[$scope];
        }

        // combine them with default values from a config file
        $defaultValues = array_merge(
            $provider->getPropertyConfig()->getDefaultValues(PropertyConfigContainer::TYPE_ENTITY),
            $defaultValues
        );

        // process translatable values
        if ($className) {
            $translatablePropertyNames = $provider->getPropertyConfig()
                ->getTranslatableValues(PropertyConfigContainer::TYPE_ENTITY);
            foreach ($translatablePropertyNames as $propertyName) {
                if (empty($defaultValues[$propertyName])) {
                    $defaultValues[$propertyName] =
                        ConfigHelper::getTranslationKey($scope, $propertyName, $className);
                }
            }
        }

        return $defaultValues;
    }

    /**
     * Extracts field default values from an annotation and config file
     *
     * @param ConfigProvider     $provider
     * @param string             $className
     * @param string             $fieldName
     * @param string             $fieldType
     * @param FieldMetadata|null $metadata
     * @return array
     */
    protected function getFieldDefaultValues(
        ConfigProvider $provider,
        $className,
        $fieldName,
        $fieldType,
        $metadata = null
    ) {
        $defaultValues = [];

        $scope = $provider->getScope();

        // try to get default values from an annotation
        if ($metadata && isset($metadata->defaultValues[$scope])) {
            $defaultValues = $metadata->defaultValues[$scope];
        }

        // combine them with default values from a config file
        $defaultValues = array_merge(
            $provider->getPropertyConfig()->getDefaultValues(PropertyConfigContainer::TYPE_FIELD, $fieldType),
            $defaultValues
        );

        // process translatable values
        $translatablePropertyNames = $provider->getPropertyConfig()
            ->getTranslatableValues(PropertyConfigContainer::TYPE_FIELD);
        foreach ($translatablePropertyNames as $propertyName) {
            if (empty($defaultValues[$propertyName])) {
                $defaultValues[$propertyName] =
                    ConfigHelper::getTranslationKey($scope, $propertyName, $className, $fieldName);
            }
        }

        return $defaultValues;
    }

    /**
     * Updates values of the given config based on the given default values and $force flag
     *
     * @param ConfigInterface $config
     * @param array           $defaultValues
     * @param bool            $force
     * @return bool  TRUE if at least one config value was updated; otherwise, FALSE
     */
    protected function updateConfigValues(ConfigInterface $config, array $defaultValues, $force)
    {
        $hasChanges = false;
        foreach ($defaultValues as $code => $value) {
            if (!$config->has($code) || $force) {
                $config->set($code, $value);
                $hasChanges = true;
            }
        }

        return $hasChanges;
    }

    /**
     * Returns a string unique identifies each config item
     *
     * @param ConfigIdInterface $configId
     * @return string
     */
    protected function buildConfigKey(ConfigIdInterface $configId)
    {
        return $configId instanceof FieldConfigId
            ? sprintf('%s_%s_%s', $configId->getScope(), $configId->getClassName(), $configId->getFieldName())
            : sprintf('%s_%s', $configId->getScope(), $configId->getClassName());
    }

    /**
     * Checks whether an entity or entity field is custom or system
     * Custom means that "extend::owner" equals "Custom"
     *
     * @param string      $className
     * @param string|null $fieldName
     * @return bool
     */
    protected function isCustom($className, $fieldName = null)
    {
        $result         = false;
        $extendProvider = $this->getProvider('extend');
        if ($extendProvider && $extendProvider->hasConfig($className, $fieldName)) {
            $result = $extendProvider->getConfig($className, $fieldName)
                ->is('owner', ExtendScope::OWNER_CUSTOM);
        }

        return $result;
    }

    /**
     * @return LogicException
     */
    protected function createDatabaseNotSyncedException()
    {
        return new LogicException(
            'Database is not synced, if you use ConfigManager, when a db schema may be hasn\'t synced.'
            . ' check it by ConfigManager::modelManager::checkDatabase'
        );
    }
}
