<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\Fallback;

use Oro\Bundle\ConfigBundle\Config\ConfigBag;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\ConfigBundle\Provider\SystemConfigurationFormProvider;
use Oro\Bundle\EntityBundle\Entity\EntityFieldFallbackValue;
use Oro\Bundle\EntityBundle\Exception\Fallback\FallbackFieldConfigurationMissingException;
use Oro\Bundle\EntityBundle\Exception\Fallback\FallbackProviderNotFoundException;
use Oro\Bundle\EntityBundle\Exception\Fallback\InvalidFallbackKeyException;
use Oro\Bundle\EntityBundle\Exception\Fallback\InvalidFallbackTypeException;
use Oro\Bundle\EntityBundle\Fallback\EntityFallbackResolver;
use Oro\Bundle\EntityBundle\Fallback\Provider\EntityFallbackProviderInterface;
use Oro\Bundle\EntityBundle\Fallback\Provider\SystemConfigFallbackProvider;
use Oro\Bundle\EntityBundle\Tests\Unit\Fallback\Stub\FallbackContainingEntity;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;

class EntityFallbackResolverTest extends \PHPUnit_Framework_TestCase
{
    const TEST_FALLBACK = 'testFallback';

    /** @var ConfigBag|\PHPUnit_Framework_MockObject_MockObject */
    protected $configBag;
    /** @var ConfigProvider|\PHPUnit_Framework_MockObject_MockObject */
    protected $entityConfigProvider;
    /** @var SystemConfigurationFormProvider|\PHPUnit_Framework_MockObject_MockObject */
    protected $formProvider;
    /** @var ConfigManager|\PHPUnit_Framework_MockObject_MockObject */
    protected $configManager;
    /** @var ConfigInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $configInterface;
    /** @var EntityFallbackResolver */
    protected $resolver;

    public function setUp()
    {
        $this->entityConfigProvider = $this->getMockBuilder(ConfigProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->formProvider = $this->getMockBuilder(SystemConfigurationFormProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configManager = $this->getMockBuilder(ConfigManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configBag = $this->getMockBuilder(ConfigBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configInterface = $this->createMock(ConfigInterface::class);
        $this->resolver = new EntityFallbackResolver(
            $this->entityConfigProvider,
            $this->formProvider,
            $this->configManager,
            $this->configBag
        );
    }

    public function testGetTypeReturnsDataType()
    {
        $this->setDefaultConfigInterfaceMock();
        $this->configInterface->expects($this->once())
            ->method('getValues')
            ->willReturn($this->getEntityConfiguration());
        $formDescription = ['data_type' => 'testDataType'];
        $this->configBag->expects($this->once())
            ->method('getFieldsRoot')
            ->willReturn($formDescription);


        $type = $this->resolver->getType(new \stdClass(), 'testProperty');
        $this->assertEquals($formDescription['data_type'], $type);
    }

    public function testGetTypeFromEntityFieldConfig()
    {
        $this->setDefaultConfigInterfaceMock();
        $entityConfig = $this->getEntityConfiguration();
        $this->configInterface->expects($this->exactly(2))
            ->method('getValues')
            ->willReturn($entityConfig);
        $this->configBag->expects($this->once())
            ->method('getFieldsRoot')
            ->willReturn([]);
        $type = $this->resolver->getType(new \stdClass(), 'testProperty');
        $this->assertEquals($entityConfig[EntityFieldFallbackValue::FALLBACK_TYPE], $type);
    }

    public function testGetTypeThrowsExceptionOnInvalidType()
    {
        $this->setDefaultConfigInterfaceMock();
        $entityConfig = $this->getEntityConfiguration();
        $entityConfig[EntityFieldFallbackValue::FALLBACK_TYPE] = 'invalidType';
        $this->configInterface->expects($this->exactly(2))
            ->method('getValues')
            ->willReturn($entityConfig);
        $this->configBag->expects($this->once())
            ->method('getFieldsRoot')
            ->willReturn([]);

        $this->expectException(InvalidFallbackTypeException::class);
        $this->resolver->getType(new \stdClass(), 'testProperty');
    }

    public function testGetSystemConfigFormReturnsEmptyArrayIfNoConfigName()
    {
        $this->setDefaultConfigInterfaceMock();
        $entityConfig = $this->getEntityConfiguration();
        $entityConfig[EntityFieldFallbackValue::FALLBACK_LIST] = [];
        $this->configInterface->expects($this->any())
            ->method('getValues')
            ->willReturn($entityConfig);

        $this->assertEmpty($this->resolver->getSystemConfigFormDescription(new \stdClass(), 'testProperty'));
    }

    public function testGetSystemConfigFormReturnsEmptyArrayIfNoFormDescription()
    {
        $this->setDefaultConfigInterfaceMock();
        $entityConfig = $this->getEntityConfiguration();
        $this->configInterface->expects($this->any())
            ->method('getValues')
            ->willReturn($entityConfig);
        $this->configBag->expects($this->any())
            ->method('getFieldsRoot')
            ->willReturn([]);

        $this->assertEmpty($this->resolver->getSystemConfigFormDescription(new \stdClass(), 'testProperty'));
    }

    public function testGetSystemConfigFormReturnsCorrectArray()
    {
        $this->setDefaultConfigInterfaceMock();
        $entityConfig = $this->getEntityConfiguration();
        $this->configInterface->expects($this->any())
            ->method('getValues')
            ->willReturn($entityConfig);
        $formDescr = ['data_type' => 'testType'];
        $this->configBag->expects($this->any())
            ->method('getFieldsRoot')
            ->willReturn($formDescr);
        $result = $this->resolver->getSystemConfigFormDescription(new \stdClass(), 'testProperty');
        $this->assertSame($formDescr, $result);
    }

    public function testIsFallbackSupported()
    {
        $provider = $this->getMockBuilder(SystemConfigFallbackProvider::class)->disableOriginalConstructor()->getMock();
        $this->resolver->addFallbackProvider($provider, 'systemConfig');
        $provider->expects($this->once())
            ->method('isFallbackSupported')
            ->willReturn(true);

        $this->assertTrue($this->resolver->isFallbackSupported(new \stdClass(), 'testProperty', 'systemConfig'));
    }

    public function testGetFallbackConfigReturnsFullConfig()
    {
        $this->setDefaultConfigInterfaceMock();
        $entityConfig = $this->getEntityConfiguration();
        $this->configInterface->expects($this->any())
            ->method('getValues')
            ->willReturn($entityConfig);
        $result = $this->resolver->getFallbackConfig(new \stdClass(), 'testProperty');
        $this->assertSame($entityConfig, $result);
    }

    public function testGetFallbackConfigReturnsConfigByConfigName()
    {
        $this->setDefaultConfigInterfaceMock();
        $entityConfig = $this->getEntityConfiguration();
        $this->configInterface->expects($this->any())
            ->method('getValues')
            ->willReturn($entityConfig);
        $result = $this->resolver->getFallbackConfig(
            new \stdClass(),
            'testProperty',
            EntityFieldFallbackValue::FALLBACK_LIST
        );
        $this->assertSame($entityConfig[EntityFieldFallbackValue::FALLBACK_LIST], $result);
    }

    public function testGetFallbackConfigThrowsExceptionIfNoConfigWithName()
    {
        $this->setDefaultConfigInterfaceMock();
        $entityConfig = $this->getEntityConfiguration();
        $this->configInterface->expects($this->any())
            ->method('getValues')
            ->willReturn($entityConfig);
        $this->expectException(FallbackFieldConfigurationMissingException::class);
        $this->resolver->getFallbackConfig(
            new \stdClass(),
            'testProperty',
            'nonExistentConfig'
        );
    }

    public function testGetFallbackProviderThrowsException()
    {
        $this->expectException(FallbackProviderNotFoundException::class);
        $this->resolver->getFallbackProvider('nonExistent');
    }

    public function testGetFallbackProviderReturnsProvider()
    {
        $provider = $this->getMockBuilder(SystemConfigFallbackProvider::class)->disableOriginalConstructor()->getMock();
        $this->resolver->addFallbackProvider($provider, 'systemConfig');
        $result = $this->resolver->getFallbackProvider('systemConfig');
        $this->assertSame($provider, $result);
    }

    public function testGetFallbackValueReturnsNonFallbackValue()
    {
        $entity = new FallbackContainingEntity('test');
        $this->setDefaultConfigInterfaceMock();
        $entityConfig = $this->getEntityConfiguration();
        $this->configInterface->expects($this->any())
            ->method('getValues')
            ->willReturn($entityConfig);
        $provider = $this->createMock(EntityFallbackProviderInterface::class);
        $this->resolver->addFallbackProvider($provider, SystemConfigFallbackProvider::FALLBACK_ID);
        $this->setUpTypeResolution('string');

        $this->assertEquals('test', $this->resolver->getFallbackValue($entity, 'testProperty'));
    }

    public function testGetFallbackFromFallbackList()
    {
        // set entity with null field values
        $entity = new FallbackContainingEntity();
        // set fallback entity with a value
        $entity2 = new FallbackContainingEntity();
        $fallbackValue = new EntityFieldFallbackValue();
        $expectedValue = ['testVALUE'];
        $fallbackValue->setArrayValue($expectedValue);
        $entity2->testProperty2 = $fallbackValue;

        $entityConfig = $this->getEntityConfiguration();
        $entityConfig[EntityFieldFallbackValue::FALLBACK_LIST][self::TEST_FALLBACK] = [
            EntityFallbackResolver::FALLBACK_FIELD_NAME => 'testProperty2',
        ];
        $entityConfigMock = $this->createMock(ConfigInterface::class);
        $entityConfigMock->expects($this->any())
            ->method('getValues')
            ->willReturn($entityConfig);

        $entity2Config = $this->getEntityConfiguration();
        $entity2Config[EntityFieldFallbackValue::FALLBACK_LIST]['testFallback2'] = ['fieldName' => 'testProperty2'];

        $entity2ConfigMock = $this->createMock(ConfigInterface::class);
        $entity2ConfigMock->expects($this->any())
            ->method('getValues')
            ->willReturn($entity2Config);
        $this->entityConfigProvider->expects($this->any())
            ->method('getConfig')
            ->will(
                $this->returnValueMap(
                    [
                        [get_class($entity), 'testProperty', $entityConfigMock],
                        [get_class($entity2), 'testProperty2', $entity2ConfigMock],
                    ]
                )
            );
        $this->configBag->expects($this->any())
            ->method('getFieldsRoot')
            ->willReturn(['data_type' => 'array']);

        // first fallback in the list
        $systemProvider = $this->createMock(EntityFallbackProviderInterface::class);
        $this->resolver->addFallbackProvider($systemProvider, SystemConfigFallbackProvider::FALLBACK_ID);

        // second fallback in the list
        $testProvider = $this->createMock(EntityFallbackProviderInterface::class);
        $this->resolver->addFallbackProvider($testProvider, self::TEST_FALLBACK);
        $testProvider->expects($this->once())->method('getFallbackHolderEntity')
            ->willReturn($entity2);

        $value = $this->resolver->getFallbackValue($entity, 'testProperty');
        $this->assertEquals($expectedValue, $value);
    }

    public function testGetFallbackValueReturnsOwnValue()
    {
        $this->setDefaultConfigInterfaceMock();
        $fallbackValue = new EntityFieldFallbackValue();
        $fallbackValue->setScalarValue('someValue');
        $entity = new FallbackContainingEntity($fallbackValue);

        $this->setUpTypeResolution('string');
        $this->assertEquals('someValue', $this->resolver->getFallbackValue($entity, 'testProperty'));
    }

    public function testGetFallbackValueReturnsOwnBoolean()
    {
        $this->setDefaultConfigInterfaceMock();
        $fallbackValue = new EntityFieldFallbackValue();
        $fallbackValue->setScalarValue('someValue');
        $entity = new FallbackContainingEntity($fallbackValue);

        $this->setUpTypeResolution('boolean');
        $this->assertTrue($this->resolver->getFallbackValue($entity, 'testProperty'));
    }

    public function testGetFallbackValueReturnsOwnInt()
    {
        $this->setDefaultConfigInterfaceMock();
        $fallbackValue = new EntityFieldFallbackValue();
        $fallbackValue->setScalarValue('someValue');
        $entity = new FallbackContainingEntity($fallbackValue);

        $this->setUpTypeResolution('integer');
        $this->assertEquals(0, $this->resolver->getFallbackValue($entity, 'testProperty'));
    }

    public function testGetFallbackValueReturnsOwnArray()
    {
        $this->setDefaultConfigInterfaceMock();
        $fallbackValue = new EntityFieldFallbackValue();
        $fallbackValue->setArrayValue(['test']);
        $entity = new FallbackContainingEntity($fallbackValue);

        $this->setUpTypeResolution('array');
        $this->assertEquals(['test'], $this->resolver->getFallbackValue($entity, 'testProperty'));
    }

    public function testGetFallbackValueThrowsInvalidKeyException()
    {
        $this->expectException(InvalidFallbackKeyException::class);
        $fallbackValue = new EntityFieldFallbackValue();
        $fallbackValue->setFallback('nonExistentProvider');
        $entity = new FallbackContainingEntity($fallbackValue);
        $entityConfig = $this->getEntityConfiguration();
        $entityConfigMock = $this->getMockBuilder(ConfigInterface::class)->getMock();
        $entityConfigMock->expects($this->any())
            ->method('getValues')
            ->willReturn($entityConfig);
        $this->entityConfigProvider->expects($this->any())
            ->method('getConfig')
            ->willReturn($entityConfigMock);

        $this->resolver->getFallbackValue($entity, 'testProperty');
    }

    public function testGetFallbackValueReturnsDirectValue()
    {
        $fallbackValue = new EntityFieldFallbackValue();
        $fallbackValue->setFallback(self::TEST_FALLBACK);
        $entity = new FallbackContainingEntity($fallbackValue);
        $entityConfig = $this->getEntityConfiguration();
        $listKey = EntityFieldFallbackValue::FALLBACK_LIST;
        $entityConfig[$listKey][self::TEST_FALLBACK] = ['fieldName' => 'testProperty'];
        $entityConfig[EntityFieldFallbackValue::FALLBACK_TYPE] = 'string';
        $entityConfigMock = $this->getMockBuilder(ConfigInterface::class)->getMock();
        $entityConfigMock->expects($this->any())
            ->method('getValues')
            ->willReturn($entityConfig);
        $this->entityConfigProvider->expects($this->any())
            ->method('getConfig')
            ->willReturn($entityConfigMock);
        $testProvider = $this->getMockBuilder(EntityFallbackProviderInterface::class)->getMock();
        $testProvider->expects($this->once())
            ->method('getFallbackHolderEntity')
            ->willReturn('directValue');
        $this->resolver->addFallbackProvider($testProvider, self::TEST_FALLBACK);

        $result = $this->resolver->getFallbackValue($entity, 'testProperty');
        $this->assertEquals('directValue', $result);
    }

    public function testGetFallbackValueReturnsFallbackValue()
    {
        $fallbackValue = new EntityFieldFallbackValue();
        $fallbackValue->setFallback('testFallback');
        $entity = new FallbackContainingEntity($fallbackValue);
        $entity2 = new FallbackContainingEntity(null, 'expectedValue');

        $entityConfig = $this->getEntityConfiguration();
        $listKey = EntityFieldFallbackValue::FALLBACK_LIST;
        $entityConfig[$listKey][self::TEST_FALLBACK] = ['fieldName' => 'testProperty2'];
        $entityConfigMock = $this->getMockBuilder(ConfigInterface::class)->getMock();
        $entityConfigMock->expects($this->any())
            ->method('getValues')
            ->willReturn($entityConfig);

        $entity2Config = $this->getEntityConfiguration();
        $entity2Config[$listKey][self::TEST_FALLBACK] = ['fieldName' => 'testProperty2'];
        $entity2ConfigMock = $this->getMockBuilder(ConfigInterface::class)->getMock();
        $entity2ConfigMock->expects($this->any())
            ->method('getValues')
            ->willReturn($entity2Config);
        $testProvider = $this->getMockBuilder(EntityFallbackProviderInterface::class)->getMock();
        $testProvider->expects($this->once())
            ->method('getFallbackHolderEntity')
            ->willReturn($entity2);
        $this->resolver->addFallbackProvider($testProvider, self::TEST_FALLBACK);


        $this->entityConfigProvider->expects($this->any())
            ->method('getConfig')
            ->will(
                $this->returnValueMap(
                    [
                        [get_class($entity), 'testProperty', $entityConfigMock],
                        [get_class($entity2), 'testProperty2', $entity2ConfigMock],
                    ]
                )
            );
        $this->setUpTypeResolution('string');

        $this->assertEquals('expectedValue', $this->resolver->getFallbackValue($entity, 'testProperty'));
    }

    /**
     * @param string $type
     * @param string $expectedFieldName
     * @param bool $throwException
     * @dataProvider getRequiredFallbackFieldByTypeProvider
     */
    public function testGetRequiredFallbackFieldByType($type, $expectedFieldName, $throwException = false)
    {
        if ($throwException) {
            $this->expectException(InvalidFallbackTypeException::class);
        }

        $result = $this->resolver->getRequiredFallbackFieldByType($type);
        if (!$throwException) {
            $this->assertEquals($expectedFieldName, $result);
        }
    }

    public function getRequiredFallbackFieldByTypeProvider()
    {
        return [
            [EntityFallbackResolver::TYPE_BOOLEAN, EntityFieldFallbackValue::FALLBACK_SCALAR_FIELD],
            [EntityFallbackResolver::TYPE_INTEGER, EntityFieldFallbackValue::FALLBACK_SCALAR_FIELD],
            [EntityFallbackResolver::TYPE_DECIMAL, EntityFieldFallbackValue::FALLBACK_SCALAR_FIELD],
            [EntityFallbackResolver::TYPE_STRING, EntityFieldFallbackValue::FALLBACK_SCALAR_FIELD],
            [EntityFallbackResolver::TYPE_ARRAY, EntityFieldFallbackValue::FALLBACK_ARRAY_FIELD],
            ['invalidType', null, true],
        ];
    }

    protected function setUpTypeResolution($type = 'boolean')
    {
        $this->configInterface->expects($this->any())
            ->method('getValues')
            ->willReturn($this->getEntityConfiguration());
        $this->configBag->expects($this->any())
            ->method('getFieldsRoot')
            ->willReturn(['data_type' => $type]);
    }

    protected function getEntityConfiguration()
    {
        return [
            EntityFieldFallbackValue::FALLBACK_LIST => [
                SystemConfigFallbackProvider::FALLBACK_ID => ['configName' => 'test_config_name'],
            ],
            EntityFieldFallbackValue::FALLBACK_TYPE => 'boolean',
        ];
    }

    protected function setDefaultConfigInterfaceMock()
    {
        $this->entityConfigProvider->expects($this->any())
            ->method('getConfig')
            ->willReturn($this->configInterface);
    }
}
