<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Config;

use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\ConfigModelManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\DependencyInjection\Utils\ServiceLink;
use Oro\Bundle\EntityConfigBundle\Entity\EntityConfigModel;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Event\Events;
use Oro\Bundle\EntityConfigBundle\Event\EntityConfigEvent;
use Oro\Bundle\EntityConfigBundle\Event\FieldConfigEvent;
use Oro\Bundle\EntityConfigBundle\Metadata\EntityMetadata;
use Oro\Bundle\EntityConfigBundle\Metadata\FieldMetadata;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderBag;
use Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ConfigManagerTest extends \PHPUnit_Framework_TestCase
{
    const ENTITY_CLASS = 'Oro\Bundle\EntityConfigBundle\Tests\Unit\Fixture\DemoEntity';

    /** @var ConfigManager */
    protected $configManager;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $container;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $metadataFactory;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $eventDispatcher;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $configProvider;

    /** @var ConfigProviderBag */
    protected $configProviderBag;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $modelManager;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $auditManager;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $configCache;

    protected function setUp()
    {
        $this->configProvider = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $this->configProvider->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue('entity'));

        $this->configProviderBag = new ConfigProviderBag();
        $this->configProviderBag->addProvider($this->configProvider);
        $this->container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
        $this->container->expects($this->any())
            ->method('has')
            ->will(
                $this->returnCallback(
                    function ($id) {
                        switch ($id) {
                            case 'ConfigProviderBag':
                                return true;
                            default:
                                return false;
                        }
                    }
                )
            );
        $configProviderBag = $this->configProviderBag;
        $this->container->expects($this->any())
            ->method('get')
            ->will(
                $this->returnCallback(
                    function ($id) use (&$configProviderBag) {
                        switch ($id) {
                            case 'ConfigProviderBag':
                                return $configProviderBag;
                            default:
                                return null;
                        }
                    }
                )
            );

        $this->metadataFactory = $this->getMockBuilder('Metadata\MetadataFactory')
            ->disableOriginalConstructor()
            ->getMock();
        $this->eventDispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();
        $this->modelManager    = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Config\ConfigModelManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->auditManager    = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Audit\AuditManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->configCache     = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Config\ConfigCache')
            ->disableOriginalConstructor()
            ->getMock();

        $this->configManager = new ConfigManager(
            $this->metadataFactory,
            $this->eventDispatcher,
            new ServiceLink($this->container, 'ConfigProviderBag'),
            $this->modelManager,
            $this->auditManager
        );

        $this->configManager->setCache($this->configCache);
    }

    public function testGetProviderBag()
    {
        $this->assertTrue($this->configManager->getProviderBag() === $this->configProviderBag);
    }

    public function testGetProviders()
    {
        $providers = $this->configManager->getProviders();
        $this->assertCount(1, $providers);
        $this->assertSame($this->configProvider, $providers['entity']);
    }

    public function testGetProvider()
    {
        $this->assertSame($this->configProvider, $this->configManager->getProvider('entity'));
    }

    public function testGetEventDispatcher()
    {
        $this->assertSame($this->eventDispatcher, $this->configManager->getEventDispatcher());
    }

    public function testGetEntityMetadata()
    {
        $this->assertNull($this->configManager->getEntityMetadata('SomeUndefinedClass'));

        $metadata = new EntityMetadata(self::ENTITY_CLASS);
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($metadata));
        $this->assertSame($metadata, $this->configManager->getEntityMetadata(self::ENTITY_CLASS));
    }

    public function testGetFieldMetadata()
    {
        $this->assertNull($this->configManager->getFieldMetadata('SomeUndefinedClass', 'entity'));

        $metadata        = new EntityMetadata(self::ENTITY_CLASS);
        $idFieldMetadata = new FieldMetadata(self::ENTITY_CLASS, 'id');
        $metadata->addPropertyMetadata($idFieldMetadata);
        $this->metadataFactory->expects($this->exactly(2))
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($metadata));

        $this->assertNull(
            $this->configManager->getFieldMetadata(self::ENTITY_CLASS, 'undefinedField')
        );
        $this->assertSame(
            $metadata->propertyMetadata['id'],
            $this->configManager->getFieldMetadata(self::ENTITY_CLASS, 'id')
        );
    }

    /**
     * @dataProvider hasConfigProvider
     */
    public function testHasConfig(
        $expectedResult,
        $checkDatabaseResult,
        $cachedResult,
        $findModelResult,
        $className,
        $fieldName
    ) {
        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->will($this->returnValue($checkDatabaseResult));
        if ($checkDatabaseResult) {
            $this->configCache->expects($this->once())
                ->method('getConfigurable')
                ->with($className, $fieldName)
                ->will($this->returnValue($cachedResult));
            if (null === $cachedResult) {
                $this->configCache->expects($this->once())
                    ->method('setConfigurable')
                    ->with($expectedResult, $className, $fieldName);
                if ($fieldName) {
                    $this->modelManager->expects($this->once())
                        ->method('findFieldModel')
                        ->with($className, $fieldName)
                        ->will($this->returnValue($findModelResult));
                } else {
                    $this->modelManager->expects($this->once())
                        ->method('findEntityModel')
                        ->with($className)
                        ->will($this->returnValue($findModelResult));
                }
            }
        }

        $result = $this->configManager->hasConfig($className, $fieldName);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @expectedException \Oro\Bundle\EntityConfigBundle\Exception\LogicException
     */
    public function testGetConfigNoDatabase()
    {
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->will($this->returnValue(false));
        $this->configManager->getConfig($configId);
    }

    /**
     * @expectedException \Oro\Bundle\EntityConfigBundle\Exception\RuntimeException
     */
    public function testGetConfigForNotConfigurable()
    {
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->will($this->returnValue(true));
        $this->configCache->expects($this->once())
            ->method('getConfigurable')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue(false));
        $this->configManager->getConfig($configId);
    }

    public function testGetConfigForNewEntity()
    {
        $configId = new EntityConfigId('entity');
        $this->modelManager->expects($this->never())
            ->method('checkDatabase');
        $propertyConfigContainer =
            $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer')
                ->disableOriginalConstructor()
                ->getMock();
        $propertyConfigContainer->expects($this->once())
            ->method('getDefaultValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->will($this->returnValue(['translatable' => 'labelVal', 'other' => 'otherVal']));
        $propertyConfigContainer->expects($this->never())
            ->method('getTranslatableValues');
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->will($this->returnValue($propertyConfigContainer));

        $config = $this->configManager->getConfig($configId);

        $expectedConfig = new Config($configId);
        $expectedConfig->set('translatable', 'labelVal');
        $expectedConfig->set('other', 'otherVal');

        $this->assertEquals($expectedConfig, $config);
    }

    /**
     * @dataProvider getConfigCacheProvider
     */
    public function testGetConfigCache($configId, $cachedConfig)
    {
        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->will($this->returnValue(true));
        $this->configCache->expects($this->once())
            ->method('getConfigurable')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue(true));
        $this->configCache->expects($this->once())
            ->method('loadConfigFromCache')
            ->with($this->identicalTo($configId))
            ->will($this->returnValue($cachedConfig));
        $this->modelManager->expects($this->never())
            ->method('getEntityModel');
        $this->modelManager->expects($this->never())
            ->method('getFieldModel');

        $result = $this->configManager->getConfig($configId);
        $this->assertSame($cachedConfig, $result);

        // test local cache
        $result = $this->configManager->getConfig($configId);
        $this->assertSame($cachedConfig, $result);
    }

    /**
     * @dataProvider getConfigNotCachedProvider
     */
    public function testGetConfigNotCached($configId, $getModelResult, $expectedConfig)
    {
        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->will($this->returnValue(true));
        $this->configCache->expects($this->once())
            ->method('getConfigurable')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue(true));
        $this->configCache->expects($this->once())
            ->method('loadConfigFromCache')
            ->with($this->identicalTo($configId))
            ->will($this->returnValue(null));
        $this->configCache->expects($this->once())
            ->method('putConfigInCache')
            ->with($this->equalTo($expectedConfig));
        if ($configId instanceof FieldConfigId) {
            $this->modelManager->expects($this->never())
                ->method('getEntityModel');
            $this->modelManager->expects($this->once())
                ->method('getFieldModel')
                ->with($configId->getClassName(), $configId->getFieldName())
                ->will($this->returnValue($getModelResult));
        } else {
            $this->modelManager->expects($this->once())
                ->method('getEntityModel')
                ->with($configId->getClassName())
                ->will($this->returnValue($getModelResult));
            $this->modelManager->expects($this->never())
                ->method('getFieldModel');
        }

        $result = $this->configManager->getConfig($configId);
        $this->assertEquals($expectedConfig, $result);
    }

    public function testGetIdsNoDatabase()
    {
        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->will($this->returnValue(false));
        $result = $this->configManager->getIds('entity');
        $this->assertEquals([], $result);
    }

    /**
     * @dataProvider getIdsProvider
     */
    public function testGetIds($scope, $className, $withHidden, $expectedIds)
    {
        $models      = [
            $this->createEntityConfigModel('EntityClass1'),
            $this->createEntityConfigModel('EntityClass2'),
        ];
        $entityModel = $this->createEntityConfigModel('EntityClass1');
        $fieldModels = [
            $this->createFieldConfigModel($entityModel, 'f1', 'int'),
            $this->createFieldConfigModel($entityModel, 'f2', 'int'),
        ];

        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->will($this->returnValue(true));
        $this->modelManager->expects($this->once())
            ->method('getModels')
            ->with($className, $withHidden)
            ->will($this->returnValue($className ? $fieldModels : $models));

        $result = $this->configManager->getIds($scope, $className, $withHidden);
        $this->assertEquals($expectedIds, array_values($result));
    }

    /**
     * @dataProvider getConfigsProvider
     */
    public function testGetConfigs($scope, $className, $withHidden, $expectedConfigs)
    {
        $models      = [
            $this->createEntityConfigModel('EntityClass1'),
            $this->createEntityConfigModel('EntityClass2'),
        ];
        $entityModel = $this->createEntityConfigModel('EntityClass1');
        $fieldModels = [
            $this->createFieldConfigModel($entityModel, 'f1', 'int'),
            $this->createFieldConfigModel($entityModel, 'f2', 'int'),
        ];

        $this->modelManager->expects($this->any())
            ->method('checkDatabase')
            ->will($this->returnValue(true));
        $this->configCache->expects($this->any())
            ->method('getConfigurable')
            ->will($this->returnValue(true));
        $this->modelManager->expects($this->once())
            ->method('getModels')
            ->with($className, $withHidden)
            ->will($this->returnValue($className ? $fieldModels : $models));
        if ($className) {
            $this->modelManager->expects($this->any())
                ->method('getFieldModel')
                ->will(
                    $this->returnValueMap(
                        [
                            [$className, 'f1', $fieldModels[0]],
                            [$className, 'f2', $fieldModels[1]],
                        ]
                    )
                );
        } else {
            $this->modelManager->expects($this->any())
                ->method('getEntityModel')
                ->will(
                    $this->returnValueMap(
                        [
                            ['EntityClass1', $models[0]],
                            ['EntityClass2', $models[1]],
                        ]
                    )
                );
        }

        $result = $this->configManager->getConfigs($scope, $className, $withHidden);
        $this->assertEquals($expectedConfigs, array_values($result));
    }

    public function testClearCache()
    {
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $this->configCache->expects($this->once())
            ->method('removeConfigFromCache')
            ->with($this->equalTo($configId));
        $this->configManager->clearCache($configId);
    }

    public function testClearCacheAll()
    {
        $this->configCache->expects($this->once())
            ->method('removeAll');
        $this->configManager->clearCache();
    }

    public function testClearConfigurableCache()
    {
        $this->configCache->expects($this->once())
            ->method('removeAllConfigurable');
        $this->modelManager->expects($this->once())
            ->method('clearCheckDatabase');
        $this->configManager->clearConfigurableCache();
    }

    public function testHasConfigEntityModelWithNoModel()
    {
        $this->modelManager->expects($this->once())
            ->method('findEntityModel')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue(null));
        $result = $this->configManager->hasConfigEntityModel(self::ENTITY_CLASS);
        $this->assertFalse($result);
    }

    public function testHasConfigEntityModel()
    {
        $this->modelManager->expects($this->once())
            ->method('findEntityModel')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($this->createEntityConfigModel(self::ENTITY_CLASS)));
        $result = $this->configManager->hasConfigEntityModel(self::ENTITY_CLASS);
        $this->assertTrue($result);
    }

    public function testHasConfigFieldModelWithNoModel()
    {
        $this->modelManager->expects($this->once())
            ->method('findFieldModel')
            ->with(self::ENTITY_CLASS, 'id')
            ->will($this->returnValue(null));
        $result = $this->configManager->hasConfigFieldModel(self::ENTITY_CLASS, 'id');
        $this->assertFalse($result);
    }

    public function testHasConfigFieldModel()
    {
        $this->modelManager->expects($this->once())
            ->method('findFieldModel')
            ->with(self::ENTITY_CLASS, 'id')
            ->will(
                $this->returnValue(
                    $this->createFieldConfigModel(
                        $this->createEntityConfigModel(self::ENTITY_CLASS),
                        'id',
                        'int'
                    )
                )
            );
        $result = $this->configManager->hasConfigFieldModel(self::ENTITY_CLASS, 'id');
        $this->assertTrue($result);
    }

    public function testGetConfigEntityModel()
    {
        $model = $this->createEntityConfigModel(self::ENTITY_CLASS);
        $this->modelManager->expects($this->once())
            ->method('findEntityModel')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($model));
        $result = $this->configManager->getConfigEntityModel(self::ENTITY_CLASS);
        $this->assertSame($model, $result);
    }

    public function testGetConfigFieldModel()
    {
        $model = $this->createFieldConfigModel(
            $this->createEntityConfigModel(self::ENTITY_CLASS),
            'id',
            'int'
        );
        $this->modelManager->expects($this->once())
            ->method('findFieldModel')
            ->with(self::ENTITY_CLASS, 'id')
            ->will($this->returnValue($model));
        $result = $this->configManager->getConfigFieldModel(self::ENTITY_CLASS, 'id');
        $this->assertSame($model, $result);
    }

    /**
     * @dataProvider emptyNameProvider
     */
    public function testCreateConfigEntityModelForEmptyClassName($className)
    {
        $model = $this->createEntityConfigModel($className);
        $this->modelManager->expects($this->never())
            ->method('findEntityModel');
        $this->modelManager->expects($this->once())
            ->method('createEntityModel')
            ->with($className, ConfigModelManager::MODE_DEFAULT)
            ->will($this->returnValue($model));
        $result = $this->configManager->createConfigEntityModel($className);
        $this->assertSame($model, $result);
    }

    public function testCreateConfigEntityModelForExistingModel()
    {
        $model = $this->createEntityConfigModel(self::ENTITY_CLASS);
        $this->modelManager->expects($this->once())
            ->method('findEntityModel')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($model));
        $this->modelManager->expects($this->never())
            ->method('createEntityModel');
        $result = $this->configManager->createConfigEntityModel(self::ENTITY_CLASS);
        $this->assertSame($model, $result);
    }

    public function testCreateConfigEntityModel()
    {
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $model = $this->createEntityConfigModel(self::ENTITY_CLASS);
        $this->modelManager->expects($this->once())
            ->method('findEntityModel')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue(null));
        $this->modelManager->expects($this->once())
            ->method('createEntityModel')
            ->with(self::ENTITY_CLASS, ConfigModelManager::MODE_DEFAULT)
            ->will($this->returnValue($model));
        $metadata                        = new EntityMetadata(self::ENTITY_CLASS);
        $metadata->defaultValues['entity'] = ['translatable' => 'labelVal', 'other' => 'otherVal'];
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($metadata));
        $propertyConfigContainer =
            $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer')
                ->disableOriginalConstructor()
                ->getMock();
        $propertyConfigContainer->expects($this->once())
            ->method('getDefaultValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->will($this->returnValue(['translatable10' => 'labelVal10', 'other10' => 'otherVal10']));
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->will($this->returnValue(['translatable', 'translatable10', 'auto_generated']));
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->will($this->returnValue($propertyConfigContainer));
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                Events::NEW_ENTITY_CONFIG,
                new EntityConfigEvent(self::ENTITY_CLASS, $this->configManager)
            );

        $config   = new Config($configId);
        $config->set('other', 'otherVal');
        $config->set('translatable', 'labelVal');
        $config->set('other10', 'otherVal10');
        $config->set('translatable10', 'labelVal10');
        $config->set('auto_generated', 'oro.entityconfig.tests.unit.fixture.demoentity.entity_auto_generated');

        $result = $this->configManager->createConfigEntityModel(self::ENTITY_CLASS);

        $this->assertEquals($model, $result);
        $this->assertEquals(
            [$config],
            $this->configManager->getUpdateConfig()
        );

        // test that a config for a created model is stored in a local cache
        $result = $this->configManager->getConfig($configId);
        $this->assertEquals($config, $result);
    }

    public function testCreateConfigFieldModelForExistingModel()
    {
        $model = $this->createFieldConfigModel(
            $this->createEntityConfigModel(self::ENTITY_CLASS),
            'id',
            'int'
        );
        $this->modelManager->expects($this->once())
            ->method('findFieldModel')
            ->with(self::ENTITY_CLASS, 'id')
            ->will($this->returnValue($model));
        $this->modelManager->expects($this->never())
            ->method('createFieldModel');
        $result = $this->configManager->createConfigFieldModel(self::ENTITY_CLASS, 'id', 'int');
        $this->assertSame($model, $result);
    }

    public function testCreateConfigFieldModel()
    {
        $configId = new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int');
        $model = $this->createFieldConfigModel(
            $this->createEntityConfigModel(self::ENTITY_CLASS),
            'id',
            'int'
        );
        $this->modelManager->expects($this->once())
            ->method('findFieldModel')
            ->with(self::ENTITY_CLASS, 'id')
            ->will($this->returnValue(null));
        $this->modelManager->expects($this->once())
            ->method('createFieldModel')
            ->with(self::ENTITY_CLASS, 'id', 'int', ConfigModelManager::MODE_DEFAULT)
            ->will($this->returnValue($model));
        $metadata        = new EntityMetadata(self::ENTITY_CLASS);
        $idFieldMetadata = new FieldMetadata(self::ENTITY_CLASS, 'id');
        $metadata->addPropertyMetadata($idFieldMetadata);
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($metadata));
        $idFieldMetadata->defaultValues['entity'] = ['translatable' => 'labelVal', 'other' => 'otherVal'];
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($metadata));
        $propertyConfigContainer =
            $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer')
                ->disableOriginalConstructor()
                ->getMock();
        $propertyConfigContainer->expects($this->once())
            ->method('getDefaultValues')
            ->with(PropertyConfigContainer::TYPE_FIELD, 'int')
            ->will($this->returnValue(['translatable10' => 'labelVal10', 'other10' => 'otherVal10']));
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_FIELD)
            ->will($this->returnValue(['translatable', 'translatable10', 'auto_generated']));
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->will($this->returnValue($propertyConfigContainer));
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                Events::NEW_FIELD_CONFIG,
                new FieldConfigEvent(self::ENTITY_CLASS, 'id', $this->configManager)
            );

        $config   = new Config($configId);

        $config->set('other10', 'otherVal10');
        $config->set('translatable10', 'labelVal10');
        $config->set('other', 'otherVal');
        $config->set('translatable', 'labelVal');
        $config->set('auto_generated', 'oro.entityconfig.tests.unit.fixture.demoentity.id.auto_generated');

        $result = $this->configManager->createConfigFieldModel(self::ENTITY_CLASS, 'id', 'int');

        $this->assertEquals($model, $result);
        $this->assertEquals(
            [$config],
            $this->configManager->getUpdateConfig()
        );

        // test that a config for a created model is stored in a local cache
        $result = $this->configManager->getConfig($configId);
        $this->assertEquals($config, $result);
    }

    public function testUpdateConfigEntityModelWithNoForce()
    {
        $configId                        = new EntityConfigId('entity', self::ENTITY_CLASS);
        $metadata                        = new EntityMetadata(self::ENTITY_CLASS);
        $metadata->defaultValues['entity'] = [
            'translatable1' => 'labelVal1',
            'other1'        => 'otherVal1',
            'translatable2' => 'labelVal2',
            'other2'        => 'otherVal2',
        ];
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($metadata));
        $propertyConfigContainer =
            $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer')
                ->disableOriginalConstructor()
                ->getMock();
        $propertyConfigContainer->expects($this->once())
            ->method('getDefaultValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->will($this->returnValue(['translatable10' => 'labelVal10', 'other10' => 'otherVal10']));
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->will($this->returnValue(['translatable1', 'translatable2', 'translatable10', 'auto_generated']));
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->will($this->returnValue($propertyConfigContainer));
        $config = new Config($configId);
        $config->set('translatable2', 'labelVal2_old');
        $config->set('other2', 'otherVal2_old');
        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($config));

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(Events::UPDATE_ENTITY_CONFIG);

        $expectedConfig = new Config($configId);
        $expectedConfig->set('translatable2', 'labelVal2_old');
        $expectedConfig->set('other2', 'otherVal2_old');
        $expectedConfig->set('translatable10', 'labelVal10');
        $expectedConfig->set('other10', 'otherVal10');
        $expectedConfig->set('translatable1', 'labelVal1');
        $expectedConfig->set('other1', 'otherVal1');
        $expectedConfig->set('auto_generated', 'oro.entityconfig.tests.unit.fixture.demoentity.entity_auto_generated');

        $actualConfig = null;
        $this->configProvider->expects($this->once())
            ->method('persist')
            ->will(
                $this->returnCallback(
                    function ($c) use (&$actualConfig) {
                        $actualConfig = $c;
                    }
                )
            );

        $this->configManager->updateConfigEntityModel(self::ENTITY_CLASS);
        $this->assertEquals($expectedConfig, $actualConfig);
    }

    public function testUpdateConfigEntityModelWithForce()
    {
        $configId                        = new EntityConfigId('entity', self::ENTITY_CLASS);
        $metadata                        = new EntityMetadata(self::ENTITY_CLASS);
        $metadata->defaultValues['entity'] = [
            'translatable1' => 'labelVal1',
            'other1'        => 'otherVal1',
            'translatable2' => 'labelVal2',
            'other2'        => 'otherVal2',
        ];
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($metadata));
        $propertyConfigContainer =
            $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer')
                ->disableOriginalConstructor()
                ->getMock();
        $propertyConfigContainer->expects($this->once())
            ->method('getDefaultValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->will($this->returnValue(['translatable10' => 'labelVal10', 'other10' => 'otherVal10']));
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->will($this->returnValue(['translatable1', 'translatable2', 'translatable10', 'auto_generated']));
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->will($this->returnValue($propertyConfigContainer));
        $config = new Config($configId);
        $config->set('translatable2', 'labelVal2_old');
        $config->set('other2', 'otherVal2_old');
        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($config));

        $expectedConfig = new Config($configId);
        $expectedConfig->set('translatable2', 'labelVal2');
        $expectedConfig->set('other2', 'otherVal2');
        $expectedConfig->set('translatable10', 'labelVal10');
        $expectedConfig->set('other10', 'otherVal10');
        $expectedConfig->set('translatable1', 'labelVal1');
        $expectedConfig->set('other1', 'otherVal1');
        $expectedConfig->set('auto_generated', 'oro.entityconfig.tests.unit.fixture.demoentity.entity_auto_generated');

        $actualConfig = null;
        $this->configProvider->expects($this->once())
            ->method('persist')
            ->will(
                $this->returnCallback(
                    function ($c) use (&$actualConfig) {
                        $actualConfig = $c;
                    }
                )
            );

        $this->configManager->updateConfigEntityModel(self::ENTITY_CLASS, true);
        $this->assertEquals($expectedConfig, $actualConfig);
    }

    public function testUpdateConfigEntityModelWithForceForCustomEntity()
    {
        $configId                        = new EntityConfigId('entity', self::ENTITY_CLASS);
        $metadata                        = new EntityMetadata(self::ENTITY_CLASS);
        $metadata->defaultValues['entity'] = [
            'translatable1' => 'labelVal1',
            'other1'        => 'otherVal1',
            'translatable2' => 'labelVal2',
            'other2'        => 'otherVal2',
        ];
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($metadata));
        $propertyConfigContainer =
            $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer')
                ->disableOriginalConstructor()
                ->getMock();
        $propertyConfigContainer->expects($this->once())
            ->method('getDefaultValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->will($this->returnValue(['translatable10' => 'labelVal10', 'other10' => 'otherVal10']));
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->will($this->returnValue(['translatable1', 'translatable2', 'translatable10', 'auto_generated']));
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->will($this->returnValue($propertyConfigContainer));
        $config = new Config($configId);
        $config->set('translatable2', 'labelVal2_old');
        $config->set('other2', 'otherVal2_old');
        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($config));

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(Events::UPDATE_ENTITY_CONFIG);

        $extendConfig = new Config(new EntityConfigId('extend', self::ENTITY_CLASS));
        $extendConfig->set('owner', ExtendScope::OWNER_CUSTOM);
        $extendConfigProvider = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $extendConfigProvider->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue('extend'));
        $this->configProviderBag->addProvider($extendConfigProvider);
        $extendConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue(true));
        $extendConfigProvider->expects($this->exactly(2))
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($extendConfig));
        $extendPropertyConfigContainer =
            $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer')
                ->disableOriginalConstructor()
                ->getMock();
        $extendPropertyConfigContainer->expects($this->once())
            ->method('getDefaultValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->will($this->returnValue(['owner' => ExtendScope::OWNER_SYSTEM]));
        $extendPropertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_ENTITY)
            ->will($this->returnValue([]));
        $extendConfigProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->will($this->returnValue($extendPropertyConfigContainer));
        $extendConfigProvider->expects($this->never())
            ->method('persist');

        $expectedConfig = new Config($configId);
        $expectedConfig->set('translatable2', 'labelVal2_old');
        $expectedConfig->set('other2', 'otherVal2_old');
        $expectedConfig->set('translatable10', 'labelVal10');
        $expectedConfig->set('other10', 'otherVal10');
        $expectedConfig->set('translatable1', 'labelVal1');
        $expectedConfig->set('other1', 'otherVal1');
        $expectedConfig->set('auto_generated', 'oro.entityconfig.tests.unit.fixture.demoentity.entity_auto_generated');
        $actualConfig = null;
        $this->configProvider->expects($this->once())
            ->method('persist')
            ->will(
                $this->returnCallback(
                    function ($c) use (&$actualConfig) {
                        $actualConfig = $c;
                    }
                )
            );

        $this->configManager->updateConfigEntityModel(self::ENTITY_CLASS, true);
        $this->assertEquals($expectedConfig, $actualConfig);
    }

    public function testUpdateConfigFieldModelWithNoForce()
    {
        $configId        = new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int');
        $metadata        = new EntityMetadata(self::ENTITY_CLASS);
        $idFieldMetadata = new FieldMetadata(self::ENTITY_CLASS, 'id');
        $metadata->addPropertyMetadata($idFieldMetadata);
        $idFieldMetadata->defaultValues['entity'] = [
            'translatable1' => 'labelVal1',
            'other1'        => 'otherVal1',
            'translatable2' => 'labelVal2',
            'other2'        => 'otherVal2',
        ];
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($metadata));
        $propertyConfigContainer =
            $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer')
                ->disableOriginalConstructor()
                ->getMock();
        $propertyConfigContainer->expects($this->once())
            ->method('getDefaultValues')
            ->with(PropertyConfigContainer::TYPE_FIELD, 'int')
            ->will($this->returnValue(['translatable10' => 'labelVal10', 'other10' => 'otherVal10']));
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_FIELD)
            ->will($this->returnValue(['translatable1', 'translatable2', 'translatable10', 'auto_generated']));
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->will($this->returnValue($propertyConfigContainer));
        $config = new Config($configId);
        $config->set('translatable2', 'labelVal2_old');
        $config->set('other2', 'otherVal2_old');
        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($config));

        $expectedConfig = new Config($configId);
        $expectedConfig->set('translatable2', 'labelVal2_old');
        $expectedConfig->set('other2', 'otherVal2_old');
        $expectedConfig->set('translatable10', 'labelVal10');
        $expectedConfig->set('other10', 'otherVal10');
        $expectedConfig->set('translatable1', 'labelVal1');
        $expectedConfig->set('other1', 'otherVal1');
        $expectedConfig->set('auto_generated', 'oro.entityconfig.tests.unit.fixture.demoentity.id.auto_generated');

        $actualConfig = null;
        $this->configProvider->expects($this->once())
            ->method('persist')
            ->will(
                $this->returnCallback(
                    function ($c) use (&$actualConfig) {
                        $actualConfig = $c;
                    }
                )
            );

        $this->configManager->updateConfigFieldModel(self::ENTITY_CLASS, 'id');
        $this->assertEquals($expectedConfig, $actualConfig);
    }

    public function testUpdateConfigFieldModelWithForce()
    {
        $configId        = new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int');
        $metadata        = new EntityMetadata(self::ENTITY_CLASS);
        $idFieldMetadata = new FieldMetadata(self::ENTITY_CLASS, 'id');
        $metadata->addPropertyMetadata($idFieldMetadata);
        $idFieldMetadata->defaultValues['entity'] = [
            'translatable1' => 'labelVal1',
            'other1'        => 'otherVal1',
            'translatable2' => 'labelVal2',
            'other2'        => 'otherVal2',
        ];
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($metadata));
        $propertyConfigContainer =
            $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer')
                ->disableOriginalConstructor()
                ->getMock();
        $propertyConfigContainer->expects($this->once())
            ->method('getDefaultValues')
            ->with(PropertyConfigContainer::TYPE_FIELD, 'int')
            ->will($this->returnValue(['translatable10' => 'labelVal10', 'other10' => 'otherVal10']));
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_FIELD)
            ->will($this->returnValue(['translatable1', 'translatable2', 'translatable10', 'auto_generated']));
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->will($this->returnValue($propertyConfigContainer));
        $config = new Config($configId);
        $config->set('translatable2', 'labelVal2_old');
        $config->set('other2', 'otherVal2_old');
        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($config));

        $expectedConfig = new Config($configId);
        $expectedConfig->set('translatable2', 'labelVal2');
        $expectedConfig->set('other2', 'otherVal2');
        $expectedConfig->set('translatable10', 'labelVal10');
        $expectedConfig->set('other10', 'otherVal10');
        $expectedConfig->set('translatable1', 'labelVal1');
        $expectedConfig->set('other1', 'otherVal1');
        $expectedConfig->set('auto_generated', 'oro.entityconfig.tests.unit.fixture.demoentity.id.auto_generated');

        $actualConfig = null;
        $this->configProvider->expects($this->once())
            ->method('persist')
            ->will(
                $this->returnCallback(
                    function ($c) use (&$actualConfig) {
                        $actualConfig = $c;
                    }
                )
            );

        $this->configManager->updateConfigFieldModel(self::ENTITY_CLASS, 'id', true);
        $this->assertEquals($expectedConfig, $actualConfig);
    }

    public function testUpdateConfigFieldModelWithForceForCustomField()
    {
        $configId        = new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int');
        $metadata        = new EntityMetadata(self::ENTITY_CLASS);
        $idFieldMetadata = new FieldMetadata(self::ENTITY_CLASS, 'id');
        $metadata->addPropertyMetadata($idFieldMetadata);
        $idFieldMetadata->defaultValues['entity'] = [
            'translatable1' => 'labelVal1',
            'other1'        => 'otherVal1',
            'translatable2' => 'labelVal2',
            'other2'        => 'otherVal2',
        ];
        $this->metadataFactory->expects($this->once())
            ->method('getMetadataForClass')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($metadata));
        $propertyConfigContainer =
            $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer')
                ->disableOriginalConstructor()
                ->getMock();
        $propertyConfigContainer->expects($this->once())
            ->method('getDefaultValues')
            ->with(PropertyConfigContainer::TYPE_FIELD, 'int')
            ->will($this->returnValue(['translatable10' => 'labelVal10', 'other10' => 'otherVal10']));
        $propertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_FIELD)
            ->will($this->returnValue(['translatable1', 'translatable2', 'translatable10', 'auto_generated']));
        $this->configProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->will($this->returnValue($propertyConfigContainer));
        $config = new Config($configId);
        $config->set('translatable2', 'labelVal2_old');
        $config->set('other2', 'otherVal2_old');
        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY_CLASS)
            ->will($this->returnValue($config));

        $extendConfig = new Config(new FieldConfigId('extend', self::ENTITY_CLASS, 'id'));
        $extendConfig->set('owner', ExtendScope::OWNER_CUSTOM);
        $extendConfigProvider = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $extendConfigProvider->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue('extend'));
        $this->configProviderBag->addProvider($extendConfigProvider);
        $extendConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with(self::ENTITY_CLASS, 'id')
            ->will($this->returnValue(true));
        $extendConfigProvider->expects($this->exactly(2))
            ->method('getConfig')
            ->with(self::ENTITY_CLASS, 'id')
            ->will($this->returnValue($extendConfig));
        $extendPropertyConfigContainer =
            $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer')
                ->disableOriginalConstructor()
                ->getMock();
        $extendPropertyConfigContainer->expects($this->once())
            ->method('getDefaultValues')
            ->with(PropertyConfigContainer::TYPE_FIELD)
            ->will($this->returnValue(['owner' => ExtendScope::OWNER_SYSTEM]));
        $extendPropertyConfigContainer->expects($this->once())
            ->method('getTranslatableValues')
            ->with(PropertyConfigContainer::TYPE_FIELD)
            ->will($this->returnValue([]));
        $extendConfigProvider->expects($this->any())
            ->method('getPropertyConfig')
            ->will($this->returnValue($extendPropertyConfigContainer));
        $extendConfigProvider->expects($this->never())
            ->method('persist');

        $expectedConfig = new Config($configId);
        $expectedConfig->set('translatable2', 'labelVal2_old');
        $expectedConfig->set('other2', 'otherVal2_old');
        $expectedConfig->set('translatable10', 'labelVal10');
        $expectedConfig->set('other10', 'otherVal10');
        $expectedConfig->set('translatable1', 'labelVal1');
        $expectedConfig->set('other1', 'otherVal1');
        $expectedConfig->set('auto_generated', 'oro.entityconfig.tests.unit.fixture.demoentity.id.auto_generated');

        $actualConfig = null;
        $this->configProvider->expects($this->once())
            ->method('persist')
            ->will(
                $this->returnCallback(
                    function ($c) use (&$actualConfig) {
                        $actualConfig = $c;
                    }
                )
            );

        $this->configManager->updateConfigFieldModel(self::ENTITY_CLASS, 'id', true);
        $this->assertEquals($expectedConfig, $actualConfig);
    }

    public function testPersistAndMerge()
    {
        $configId = new EntityConfigId('entity', self::ENTITY_CLASS);
        $config1  = new Config($configId);
        $config1->set('val1', '1');
        $config1->set('val2', '2');
        $config2 = new Config($configId);
        $config2->set('val2', '2_new');
        $config2->set('val3', '3');

        $expectedConfig = new Config($configId);
        $expectedConfig->set('val1', '1');
        $expectedConfig->set('val2', '2_new');
        $expectedConfig->set('val3', '3');

        $this->configManager->persist($config1);
        $this->configManager->merge($config2);
        $toBePersistedConfigs = $this->configManager->getUpdateConfig();

        $this->assertEquals([$expectedConfig], $toBePersistedConfigs);
    }

    protected function createEntityConfigModel(
        $className,
        $mode = ConfigModelManager::MODE_DEFAULT
    ) {
        $result = new EntityConfigModel($className);
        $result->setMode($mode);

        return $result;
    }

    protected function createFieldConfigModel(
        EntityConfigModel $entityConfigModel,
        $fieldName,
        $fieldType,
        $mode = ConfigModelManager::MODE_DEFAULT
    ) {
        $result = new FieldConfigModel($fieldName, $fieldType);
        $result->setEntity($entityConfigModel);
        $result->setMode($mode);

        return $result;
    }

    public function hasConfigProvider()
    {
        return [
            'no database'          => [false, false, null, null, self::ENTITY_CLASS, null],
            'no database (field)'  => [false, false, null, null, self::ENTITY_CLASS, 'id'],
            'cached false'         => [false, true, false, null, self::ENTITY_CLASS, null],
            'cached false (field)' => [false, true, false, null, self::ENTITY_CLASS, 'id'],
            'cached true'          => [true, true, true, null, self::ENTITY_CLASS, null],
            'cached true (field)'  => [true, true, true, null, self::ENTITY_CLASS, 'id'],
            'no model'             => [false, true, null, null, self::ENTITY_CLASS, null],
            'no model (field)'     => [false, true, null, null, self::ENTITY_CLASS, 'id'],
            'has model'            => [
                true,
                true,
                null,
                $this->createEntityConfigModel(self::ENTITY_CLASS),
                self::ENTITY_CLASS,
                null
            ],
            'has model (field)'    => [
                true,
                true,
                null,
                $this->createFieldConfigModel(
                    $this->createEntityConfigModel(self::ENTITY_CLASS),
                    'id',
                    'int'
                ),
                self::ENTITY_CLASS,
                'id'
            ],
        ];
    }

    public function getConfigCacheProvider()
    {
        return [
            [
                new EntityConfigId('entity', self::ENTITY_CLASS),
                new Config(new EntityConfigId('entity', self::ENTITY_CLASS))
            ],
            [
                new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int'),
                new Config(new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int'))
            ],
        ];
    }

    public function getConfigNotCachedProvider()
    {
        return [
            [
                new EntityConfigId('entity', self::ENTITY_CLASS),
                $this->createEntityConfigModel(self::ENTITY_CLASS),
                new Config(new EntityConfigId('entity', self::ENTITY_CLASS))
            ],
            [
                new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int'),
                $this->createFieldConfigModel(
                    $this->createEntityConfigModel(self::ENTITY_CLASS),
                    'id',
                    'int'
                ),
                new Config(new FieldConfigId('entity', self::ENTITY_CLASS, 'id', 'int'))
            ],
        ];
    }

    public function getIdsProvider()
    {
        return [
            [
                'entity',
                null,
                true,
                [
                    new EntityConfigId('entity', 'EntityClass1'),
                    new EntityConfigId('entity', 'EntityClass2'),
                ]
            ],
            [
                'entity',
                null,
                false,
                [
                    new EntityConfigId('entity', 'EntityClass1'),
                    new EntityConfigId('entity', 'EntityClass2'),
                ]
            ],
            [
                'entity',
                'EntityClass1',
                true,
                [
                    new FieldConfigId('entity', 'EntityClass1', 'f1', 'int'),
                    new FieldConfigId('entity', 'EntityClass1', 'f2', 'int'),
                ]
            ],
            [
                'entity',
                'EntityClass1',
                false,
                [
                    new FieldConfigId('entity', 'EntityClass1', 'f1', 'int'),
                    new FieldConfigId('entity', 'EntityClass1', 'f2', 'int'),
                ]
            ],
        ];
    }

    public function getConfigsProvider()
    {
        return [
            [
                'entity',
                null,
                true,
                [
                    new Config(new EntityConfigId('entity', 'EntityClass1')),
                    new Config(new EntityConfigId('entity', 'EntityClass2')),
                ]
            ],
            [
                'entity',
                null,
                false,
                [
                    new Config(new EntityConfigId('entity', 'EntityClass1')),
                    new Config(new EntityConfigId('entity', 'EntityClass2')),
                ]
            ],
            [
                'entity',
                'EntityClass1',
                true,
                [
                    new Config(new FieldConfigId('entity', 'EntityClass1', 'f1', 'int')),
                    new Config(new FieldConfigId('entity', 'EntityClass1', 'f2', 'int')),
                ]
            ],
            [
                'entity',
                'EntityClass1',
                false,
                [
                    new Config(new FieldConfigId('entity', 'EntityClass1', 'f1', 'int')),
                    new Config(new FieldConfigId('entity', 'EntityClass1', 'f2', 'int')),
                ]
            ],
        ];
    }

    public function emptyNameProvider()
    {
        return [
            [null],
            [''],
        ];
    }
}
