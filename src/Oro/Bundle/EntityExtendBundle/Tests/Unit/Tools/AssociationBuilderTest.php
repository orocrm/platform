<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Tools;

use Doctrine\ORM\Mapping\MappingException as ORMMappingException;
use Doctrine\Common\Persistence\Mapping\MappingException as PersistenceMappingException;

use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityExtendBundle\Tools\AssociationBuilder;
use Oro\Bundle\EntityExtendBundle\Tests\Util\ReflectionUtil;

class AssociationBuilderTest extends \PHPUnit_Framework_TestCase
{
    const SOURCE_CLASS = 'Test\SourceEntity';
    const TARGET_CLASS = 'Test\TargetEntity';

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $configManager;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $relationBuilder;

    public function setUp()
    {
        $this->configManager   = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Config\ConfigManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->relationBuilder = $this->getMockBuilder('Oro\Bundle\EntityExtendBundle\Tools\RelationBuilder')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testCreateManyToManyRelation()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|AssociationBuilder $builder */
        $builder = $this->getMock(
            'Oro\Bundle\EntityExtendBundle\Tools\AssociationBuilder',
            ['getPrimaryKeyColumnNames'],
            [$this->configManager, $this->relationBuilder]
        );

        $targetEntityConfig = new Config(new EntityConfigId('entity', self::TARGET_CLASS));

        $entityConfigProvider = $this->getConfigProviderMock();
        $entityConfigProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::TARGET_CLASS)
            ->will($this->returnValue($targetEntityConfig));

        $this->configManager->expects($this->any())
            ->method('getProvider')
            ->will(
                $this->returnValueMap(
                    [
                        ['entity', $entityConfigProvider]
                    ]
                )
            );

        $builder->expects($this->once())
            ->method('getPrimaryKeyColumnNames')
            ->with(self::TARGET_CLASS)
            ->will($this->returnValue(['id']));

        $this->relationBuilder->expects($this->once())
            ->method('addFieldConfig')
            ->with(
                self::SOURCE_CLASS,
                'target_entity_98c95332',
                'manyToMany',
                [
                    'extend' => [
                        'owner'           => 'System',
                        'state'           => 'New',
                        'extend'          => true,
                        'without_default' => true,
                        'relation_key'    => 'manyToMany|Test\SourceEntity|Test\TargetEntity|target_entity_98c95332',
                        'target_entity'   => self::TARGET_CLASS,
                        'target_grid'     => ['id'],
                        'target_title'    => ['id'],
                        'target_detailed' => ['id'],
                    ],
                    'entity' => [
                        'label'       => 'test.targetentity.target_entity_98c95332.label',
                        'description' => 'test.targetentity.target_entity_98c95332.description',
                    ],
                    'view'   => [
                        'is_displayable' => true
                    ],
                    'form'   => [
                        'is_enabled' => true
                    ]
                ]
            );

        $this->relationBuilder->expects($this->once())
            ->method('addManyToManyRelation')
            ->with(
                self::TARGET_CLASS,
                self::SOURCE_CLASS,
                'target_entity_98c95332',
                'manyToMany|Test\SourceEntity|Test\TargetEntity|target_entity_98c95332'
            );

        $builder->createManyToManyAssociation(self::SOURCE_CLASS, self::TARGET_CLASS, null);
    }

    public function testCreateManyToOneRelation()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|AssociationBuilder $builder */
        $builder = $this->getMock(
            'Oro\Bundle\EntityExtendBundle\Tools\AssociationBuilder',
            ['getPrimaryKeyColumnNames'],
            [$this->configManager, $this->relationBuilder]
        );

        $targetEntityConfig = new Config(new EntityConfigId('entity', self::TARGET_CLASS));

        $entityConfigProvider = $this->getConfigProviderMock();
        $entityConfigProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::TARGET_CLASS)
            ->will($this->returnValue($targetEntityConfig));

        $this->configManager->expects($this->any())
            ->method('getProvider')
            ->will(
                $this->returnValueMap(
                    [
                        ['entity', $entityConfigProvider]
                    ]
                )
            );

        $builder->expects($this->once())
            ->method('getPrimaryKeyColumnNames')
            ->with(self::TARGET_CLASS)
            ->will($this->returnValue(['id']));

        $this->relationBuilder->expects($this->once())
            ->method('addFieldConfig')
            ->with(
                self::SOURCE_CLASS,
                'target_entity_98c95332',
                'manyToOne',
                [
                    'extend' => [
                        'owner'         => 'System',
                        'state'         => 'New',
                        'extend'        => true,
                        'target_entity' => self::TARGET_CLASS,
                        'target_field'  => 'id',
                        'relation_key'  => 'manyToOne|Test\SourceEntity|Test\TargetEntity|target_entity_98c95332',
                    ],
                    'entity' => [
                        'label'       => 'test.targetentity.target_entity_98c95332.label',
                        'description' => 'test.targetentity.target_entity_98c95332.description',
                    ],
                    'view'   => [
                        'is_displayable' => false
                    ],
                    'form'   => [
                        'is_enabled' => false
                    ]
                ]
            );

        $this->relationBuilder->expects($this->once())
            ->method('addManyToOneRelation')
            ->with(
                self::TARGET_CLASS,
                self::SOURCE_CLASS,
                'target_entity_98c95332',
                'manyToOne|Test\SourceEntity|Test\TargetEntity|target_entity_98c95332'
            );

        $builder->createManyToOneAssociation(self::SOURCE_CLASS, self::TARGET_CLASS, null);
    }

    public function testPrimaryKeyColumnNames()
    {
        $entityClass = 'Test\Entity';

        $em       = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $metadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();

        $em->expects($this->once())
            ->method('getClassMetadata')
            ->with($entityClass)
            ->will($this->returnValue($metadata));

        $metadata->expects($this->once())
            ->method('getIdentifierColumnNames')
            ->will($this->returnValue(['id', 'name']));

        $this->configManager->expects($this->once())
            ->method('getEntityManager')
            ->will($this->returnValue($em));

        $builder     = new AssociationBuilder($this->configManager, $this->relationBuilder);
        $columnNames = ReflectionUtil::callProtectedMethod(
            $builder,
            'getPrimaryKeyColumnNames',
            [$entityClass]
        );

        $this->assertCount(2, $columnNames);
        $this->assertSame(['id', 'name'], $columnNames);
    }

    public function testPrimaryKeyColumnNamesWithReflectionException()
    {
        $this->configManager->expects($this->once())
            ->method('getEntityManager')
            ->will($this->throwException(new \ReflectionException('test')));

        $builder     = new AssociationBuilder($this->configManager, $this->relationBuilder);
        $columnNames = ReflectionUtil::callProtectedMethod(
            $builder,
            'getPrimaryKeyColumnNames',
            ['Test']
        );

        $this->assertCount(1, $columnNames);
        $this->assertSame(['id'], $columnNames);
    }

    public function testPrimaryKeyColumnNamesWithORMMappingException()
    {
        $this->configManager->expects($this->once())
            ->method('getEntityManager')
            ->will($this->throwException(new ORMMappingException('test')));

        $builder     = new AssociationBuilder($this->configManager, $this->relationBuilder);
        $columnNames = ReflectionUtil::callProtectedMethod(
            $builder,
            'getPrimaryKeyColumnNames',
            ['Test']
        );

        $this->assertCount(1, $columnNames);
        $this->assertSame(['id'], $columnNames);
    }

    public function testPrimaryKeyColumnNamesWithPersistenceMappingException()
    {
        $this->configManager->expects($this->once())
            ->method('getEntityManager')
            ->will($this->throwException(new PersistenceMappingException('test')));

        $builder     = new AssociationBuilder($this->configManager, $this->relationBuilder);
        $columnNames = ReflectionUtil::callProtectedMethod(
            $builder,
            'getPrimaryKeyColumnNames',
            ['Test']
        );

        $this->assertCount(1, $columnNames);
        $this->assertSame(['id'], $columnNames);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getConfigProviderMock()
    {
        return $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
