<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\ORM;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Fixtures\TestEntity;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\__CG__\ItemStubProxy;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ReflectionProperty;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class DoctrineHelperTest extends \PHPUnit\Framework\TestCase
{
    private const TEST_IDENTIFIER = 42;

    /** @var \PHPUnit\Framework\MockObject\MockObject|ManagerRegistry */
    private $registry;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $em;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $classMetadata;

    /** @var DoctrineHelper */
    private $doctrineHelper;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->em = $this->createMock(EntityManager::class);
        $this->classMetadata = $this->createMock(ClassMetadata::class);

        $this->doctrineHelper = new DoctrineHelper($this->registry);
    }

    public function testGetClassForEntity()
    {
        $entity = new ItemStub();
        $expectedClass = get_class($entity);
        $this->assertEquals($expectedClass, $this->doctrineHelper->getClass($entity));
        // test internal cache
        $this->assertEquals($expectedClass, $this->doctrineHelper->getClass($entity));
    }

    public function testGetClassForEntityProxy()
    {
        $entity = new ItemStubProxy();
        $expectedClass = 'ItemStubProxy';
        $this->assertEquals($expectedClass, $this->doctrineHelper->getClass($entity));
        // test internal cache
        $this->assertEquals($expectedClass, $this->doctrineHelper->getClass($entity));
    }

    public function testGetRealClassForEntityClass()
    {
        $class = ItemStub::class;
        $expectedClass = $class;
        $this->assertEquals($expectedClass, $this->doctrineHelper->getRealClass($class));
        // test internal cache
        $this->assertEquals($expectedClass, $this->doctrineHelper->getRealClass($class));
    }

    public function testGetRealClassForEntityProxyClass()
    {
        $class = ItemStubProxy::class;
        $expectedClass = 'ItemStubProxy';
        $this->assertEquals($expectedClass, $this->doctrineHelper->getRealClass($class));
        // test internal cache
        $this->assertEquals($expectedClass, $this->doctrineHelper->getRealClass($class));
    }

    public function testGetEntityClassForEntity()
    {
        $entity = new ItemStub();
        $expectedClass = get_class($entity);
        $this->assertEquals($expectedClass, $this->doctrineHelper->getEntityClass($entity));
        // test internal cache
        $this->assertEquals($expectedClass, $this->doctrineHelper->getEntityClass($entity));
    }

    public function testGetEntityClassForEntityProxy()
    {
        $entity = new ItemStubProxy();
        $expectedClass = 'ItemStubProxy';
        $this->assertEquals($expectedClass, $this->doctrineHelper->getEntityClass($entity));
        // test internal cache
        $this->assertEquals($expectedClass, $this->doctrineHelper->getEntityClass($entity));
    }

    public function testGetEntityClassForEntityClass()
    {
        $class = ItemStub::class;
        $expectedClass = $class;
        $this->assertEquals($expectedClass, $this->doctrineHelper->getEntityClass($class));
        // test internal cache
        $this->assertEquals($expectedClass, $this->doctrineHelper->getEntityClass($class));
    }

    public function testGetEntityClassForEntityProxyClass()
    {
        $class = ItemStubProxy::class;
        $expectedClass = 'ItemStubProxy';
        $this->assertEquals($expectedClass, $this->doctrineHelper->getEntityClass($class));
        // test internal cache
        $this->assertEquals($expectedClass, $this->doctrineHelper->getEntityClass($class));
    }

    public function testGetEntityClassForEntityType()
    {
        $class = 'OroEntityBundle:ItemStub';
        $expectedClass = ItemStub::class;
        $this->registry->expects($this->once())
            ->method('getAliasNamespace')
            ->will($this->returnValueMap([
                ['OroEntityBundle', 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub']
            ]));
        $this->assertEquals($expectedClass, $this->doctrineHelper->getEntityClass($class));
        // test internal cache
        $this->assertEquals($expectedClass, $this->doctrineHelper->getEntityClass($class));
    }

    public function testGetEntityIdentifierWithGetIdMethod()
    {
        $identifiers = ['id' => self::TEST_IDENTIFIER];
        $entity = new TestEntity($identifiers['id']);
        $this->registry->expects($this->never())
            ->method('getManagerForClass');

        $this->assertEquals(
            $identifiers,
            $this->doctrineHelper->getEntityIdentifier($entity)
        );
    }

    /**
     * @param object $entity
     * @param string $class
     * @param array $identifiers
     * @param bool $expected
     * @dataProvider testIsNewEntityDataProvider
     */
    public function testIsNewEntity($entity, $class, array $identifiers, $expected)
    {
        $this->classMetadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->will($this->returnCallback(function ($entity) use ($identifiers) {
                $res = [];
                foreach ($identifiers as $identifier) {
                    if (isset($entity->$identifier)) {
                        $res[$identifier] = $entity->$identifier;
                    }
                }

                return $res;
            }));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            $expected,
            $this->doctrineHelper->isNewEntity($entity)
        );
    }

    /**
     * @return array
     */
    public function testIsNewEntityDataProvider()
    {
        $entityWithTwoId = new ItemStub();
        $entityWithTwoId->id = 1;
        $entityWithTwoId->id2 = 2;

        $entityWithoutId = new ItemStub();

        return [
            'existing entity with 2 id fields' => [
                'entity' => $entityWithTwoId,
                'class'  => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
                'identifiers' => ['id', 'id2'],
                'expected' => false
            ],
            'existing entity with 1 id fields' => [
                'entity' => $entityWithTwoId,
                'class'  => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
                'identifiers' => ['id'],
                'expected' => false
            ],
            'existing entity without id fields' => [
                'entity' => $entityWithoutId,
                'class'  => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
                'identifiers' => ['id'],
                'expected' => true
            ],
        ];
    }

    /**
     * @param object $entity
     * @param string $class
     * @dataProvider getEntityIdentifierDataProvider
     */
    public function testGetEntityIdentifier($entity, $class)
    {
        $identifiers = ['id' => self::TEST_IDENTIFIER];

        $this->classMetadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->will($this->returnValue($identifiers));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            $identifiers,
            $this->doctrineHelper->getEntityIdentifier($entity)
        );
    }

    public function testGetEntityIdentifierNotManageableEntity()
    {
        $entity = $this->createMock(\stdClass::class);

        $this->expectException(\Oro\Bundle\EntityBundle\Exception\NotManageableEntityException::class);
        $this->expectExceptionMessage(sprintf('Entity class "%s" is not manageable', get_class($entity)));

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(get_class($entity))
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityIdentifier($entity);
    }

    /**
     * @return array
     */
    public function getEntityIdentifierDataProvider()
    {
        return [
            'existing entity' => [
                'entity' => new ItemStub(),
                'class'  => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
            ],
            'entity proxy'    => [
                'entity' => new ItemStubProxy(),
                'class'  => 'ItemStubProxy',
            ],
        ];
    }

    /**
     * @dataProvider getSingleEntityIdentifierDataProvider
     * @param integer $expected
     * @param array $identifiers
     * @param bool $throwException
     */
    public function testGetSingleEntityIdentifier($expected, array $identifiers, $throwException = true)
    {
        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->will($this->returnValue($identifiers));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            $expected,
            $this->doctrineHelper->getSingleEntityIdentifier($entity, $throwException)
        );
    }

    /**
     * @return array
     */
    public function getSingleEntityIdentifierDataProvider()
    {
        return [
            'valid identifier'                  => [
                'expected' => self::TEST_IDENTIFIER,
                'actual'   => ['id' => self::TEST_IDENTIFIER],
            ],
            'empty identifier'                  => [
                'expected' => null,
                'actual'   => [],
            ],
            'multiple identifier, no exception' => [
                'expected'       => null,
                'actual'         => ['first_id' => 1, 'second_id' => 2],
                'throwException' => false,
            ],
        ];
    }

    public function testGetSingleEntityIdentifierIncorrectIdentifier()
    {
        $this->expectException(\Oro\Bundle\EntityBundle\Exception\InvalidEntityException::class);
        $this->expectExceptionMessage('Can\'t get single identifier for "ItemStubProxy" entity.');

        $identifiers = ['key1' => 'value1', 'key2' => 'value2'];

        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->will($this->returnValue($identifiers));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->doctrineHelper->getSingleEntityIdentifier($entity);
    }

    /**
     * @param object $entity
     * @param string $class
     * @dataProvider getEntityIdentifierFieldNamesDataProvider
     */
    public function testGetEntityIdentifierFieldNames($entity, $class)
    {
        $identifiers = ['id' => self::TEST_IDENTIFIER];

        $this->classMetadata->expects($this->any())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            array_keys($identifiers),
            $this->doctrineHelper->getEntityIdentifierFieldNames($entity)
        );
    }

    public function testGetEntityIdentifierFieldNamesNotManageableEntity()
    {
        $entity = $this->createMock(\stdClass::class);

        $this->expectException(\Oro\Bundle\EntityBundle\Exception\NotManageableEntityException::class);
        $this->expectExceptionMessage(sprintf('Entity class "%s" is not manageable', get_class($entity)));

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(get_class($entity))
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityIdentifierFieldNames($entity);
    }

    /**
     * @return array
     */
    public function getEntityIdentifierFieldNamesDataProvider()
    {
        return [
            'existing entity' => [
                'entity' => new ItemStub(),
                'class'  => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
            ],
            'entity proxy'    => [
                'entity' => new ItemStubProxy(),
                'class'  => 'ItemStubProxy',
            ],
        ];
    }

    public function testGetEntityIdentifierFieldNamesForClass()
    {
        $class       = 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub';
        $identifiers = ['id' => self::TEST_IDENTIFIER];

        $this->classMetadata->expects($this->any())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            array_keys($identifiers),
            $this->doctrineHelper->getEntityIdentifierFieldNamesForClass($class)
        );
    }

    public function testGetEntityIdentifierFieldNamesForClassNotManageableEntity()
    {
        $class = \stdClass::class;

        $this->expectException(\Oro\Bundle\EntityBundle\Exception\NotManageableEntityException::class);
        $this->expectExceptionMessage(sprintf('Entity class "%s" is not manageable', $class));

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityIdentifierFieldNamesForClass($class);
    }

    /**
     * @dataProvider getSingleEntityIdentifierFieldNameDataProvider
     * @param string $expected
     * @param array $identifiers
     * @param bool $throwException
     */
    public function testGetSingleEntityIdentifierFieldName($expected, array $identifiers, $throwException = true)
    {
        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->any())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            $expected,
            $this->doctrineHelper->getSingleEntityIdentifierFieldName($entity, $throwException)
        );
    }

    /**
     * @return array
     */
    public function getSingleEntityIdentifierFieldNameDataProvider()
    {
        return [
            'valid identifier'                  => [
                'expected' => 'id',
                'actual'   => ['id' => self::TEST_IDENTIFIER],
            ],
            'empty identifier'                  => [
                'expected' => null,
                'actual'   => [],
            ],
            'multiple identifier, no exception' => [
                'expected'       => null,
                'actual'         => ['first_id' => 1, 'second_id' => 2],
                'throwException' => false,
            ],
        ];
    }

    public function testGetSingleEntityIdentifierFieldNameIncorrectIdentifier()
    {
        $this->expectException(\Oro\Bundle\EntityBundle\Exception\InvalidEntityException::class);
        $this->expectExceptionMessage('Can\'t get single identifier field name for "ItemStubProxy" entity.');

        $identifiers = ['key1' => 'value1', 'key2' => 'value2'];

        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->any())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->doctrineHelper->getSingleEntityIdentifierFieldName($entity);
    }

    /**
     * @dataProvider getSingleEntityIdentifierFieldTypeDataProvider
     * @param string $expected
     * @param array $identifiers
     * @param bool $throwException
     */
    public function testGetSingleEntityIdentifierFieldType($expected, array $identifiers, $throwException = true)
    {
        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->any())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));
        $this->classMetadata->expects($this->any())
            ->method('getTypeOfField')
            ->will(
                $this->returnCallback(
                    function ($fieldName) use ($identifiers) {
                        return $identifiers[$fieldName];
                    }
                )
            );

        $this->assertEquals(
            $expected,
            $this->doctrineHelper->getSingleEntityIdentifierFieldType($entity, $throwException)
        );
    }

    /**
     * @return array
     */
    public function getSingleEntityIdentifierFieldTypeDataProvider()
    {
        return [
            'valid identifier'                  => [
                'expected' => 'integer',
                'actual'   => ['id' => 'integer'],
            ],
            'empty identifier'                  => [
                'expected' => null,
                'actual'   => [],
                'exception' => false,
            ],
            'multiple identifier, no exception' => [
                'expected'       => null,
                'actual'         => ['first_id' => 'integer', 'second_id' => 'string'],
                'throwException' => false,
            ],
        ];
    }

    public function testGetSingleEntityIdentifierFieldTypeIncorrectIdentifier()
    {
        $this->expectException(\Oro\Bundle\EntityBundle\Exception\InvalidEntityException::class);
        $this->expectExceptionMessage('Can\'t get single identifier field type for "ItemStubProxy" entity.');

        $identifiers = ['key1' => 'integer', 'key2' => 'string'];

        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->once())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));
        $this->classMetadata->expects($this->never())
            ->method('getTypeOfField');

        $this->doctrineHelper->getSingleEntityIdentifierFieldType($entity);
    }

    public function testGetSingleEntityIdentifierFieldTypeEmptyIdentifier()
    {
        $this->expectException(\Oro\Bundle\EntityBundle\Exception\InvalidEntityException::class);
        $this->expectExceptionMessage('Can\'t get single identifier field type for "ItemStubProxy" entity.');

        $identifiers = [];

        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->once())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));
        $this->classMetadata->expects($this->never())
            ->method('getTypeOfField');

        $this->doctrineHelper->getSingleEntityIdentifierFieldType($entity);
    }

    public function testIsManageableEntity()
    {
        $entity = new ItemStubProxy();

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($this->doctrineHelper->getEntityClass($entity))
            ->will($this->returnValue($this->em));

        $this->assertTrue(
            $this->doctrineHelper->isManageableEntity($entity)
        );
    }

    public function testIsManageableEntityForNotManageableEntity()
    {
        $entity = new ItemStubProxy();

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($this->doctrineHelper->getEntityClass($entity))
            ->will($this->returnValue(null));

        $this->assertFalse(
            $this->doctrineHelper->isManageableEntity($entity)
        );
    }

    public function testIsManageableEntityClass()
    {
        $class = 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertTrue(
            $this->doctrineHelper->isManageableEntityClass($class)
        );
    }

    public function testIsManageableEntityClassForNotManageableEntity()
    {
        $class = 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->assertFalse(
            $this->doctrineHelper->isManageableEntityClass($class)
        );
    }

    /**
     * @dataProvider getEntityMetadataDataProvider
     * @param string|object $entityOrClass
     * @param string $class
     */
    public function testGetEntityMetadata($entityOrClass, $class)
    {
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $this->classMetadata,
            $this->doctrineHelper->getEntityMetadata($entityOrClass)
        );
    }

    /**
     * @return array
     */
    public function getEntityMetadataDataProvider()
    {
        return [
            ['ItemStubProxy', 'ItemStubProxy'],
            [new ItemStubProxy(), 'ItemStubProxy']
        ];
    }

    public function testGetEntityMetadataNotManageableEntity()
    {
        $this->expectException(\Oro\Bundle\EntityBundle\Exception\NotManageableEntityException::class);
        $this->expectExceptionMessage('Entity class "ItemStub" is not manageable');

        $class = 'ItemStub';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityMetadata($class);
    }

    public function testGetEntityMetadataNotManageableEntityWithoutThrowException()
    {
        $class = 'ItemStub';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->assertNull(
            $this->doctrineHelper->getEntityMetadata($class, false)
        );
    }

    public function testGetEntityMetadataForClass()
    {
        $class = 'ItemStub';

        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $this->classMetadata,
            $this->doctrineHelper->getEntityMetadataForClass($class)
        );
    }

    public function testGetEntityMetadataForClassNotManageableEntity()
    {
        $this->expectException(\Oro\Bundle\EntityBundle\Exception\NotManageableEntityException::class);
        $this->expectExceptionMessage('Entity class "ItemStub" is not manageable');

        $class = 'ItemStub';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityMetadataForClass($class);
    }

    public function testGetEntityMetadataForClassNotManageableEntityWithoutThrowException()
    {
        $class = 'ItemStub';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->assertNull(
            $this->doctrineHelper->getEntityMetadataForClass($class, false)
        );
    }

    /**
     * @dataProvider getEntityManagerDataProvider
     * @param string|object $entityOrClass
     */
    public function testGetEntityManager($entityOrClass)
    {
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($this->doctrineHelper->getEntityClass($entityOrClass))
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $this->em,
            $this->doctrineHelper->getEntityManager($entityOrClass)
        );
    }

    /**
     * @return array
     */
    public function getEntityManagerDataProvider()
    {
        return [
            ['ItemStubProxy'],
            [new ItemStubProxy()]
        ];
    }

    public function testGetEntityManagerNotManageableEntity()
    {
        $this->expectException(\Oro\Bundle\EntityBundle\Exception\NotManageableEntityException::class);
        $this->expectExceptionMessage('Entity class "ItemStub" is not manageable');

        $class = 'ItemStub';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityManager($class);
    }

    public function testGetEntityManagerNotManageableEntityWithoutThrowException()
    {
        $class = 'ItemStub';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->assertNull(
            $this->doctrineHelper->getEntityManager($class, false)
        );
    }

    public function testGetEntityManagerForClass()
    {
        $class = 'ItemStub';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $this->em,
            $this->doctrineHelper->getEntityManagerForClass($class)
        );
    }

    public function testGetEntityManagerForClassNotManageableEntity()
    {
        $this->expectException(\Oro\Bundle\EntityBundle\Exception\NotManageableEntityException::class);
        $this->expectExceptionMessage('Entity class "ItemStub" is not manageable');

        $class = 'ItemStub';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityManagerForClass($class);
    }

    public function testGetEntityManagerForClassNotManageableEntityWithoutThrowException()
    {
        $class = 'ItemStub';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->assertNull(
            $this->doctrineHelper->getEntityManagerForClass($class, false)
        );
    }

    public function testGetEntityRepositoryByEntity()
    {
        $entity      = new ItemStubProxy();
        $entityClass = $this->doctrineHelper->getEntityClass($entity);

        $repo = $this->createMock(EntityRepository::class);

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($entityClass)
            ->will($this->returnValue($this->em));
        $this->em->expects($this->once())
            ->method('getRepository')
            ->with($entityClass)
            ->will($this->returnValue($repo));

        $this->assertSame(
            $repo,
            $this->doctrineHelper->getEntityRepository($entity)
        );
    }

    public function testGetEntityRepositoryByClass()
    {
        $class = 'ItemStubProxy';

        $repo = $this->createMock(EntityRepository::class);

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));
        $this->em->expects($this->once())
            ->method('getRepository')
            ->with($class)
            ->will($this->returnValue($repo));

        $this->assertSame(
            $repo,
            $this->doctrineHelper->getEntityRepository($class)
        );
    }

    public function testGetEntityRepositoryNotManageableEntity()
    {
        $class = 'ItemStubProxy';

        $this->expectException(\Oro\Bundle\EntityBundle\Exception\NotManageableEntityException::class);
        $this->expectExceptionMessage(sprintf('Entity class "%s" is not manageable', $class));

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityRepository($class);
    }

    public function testGetEntityRepositoryForClass()
    {
        $class = 'ItemStub';

        $repo = $this->createMock(EntityRepository::class);

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));
        $this->em->expects($this->once())
            ->method('getRepository')
            ->with($class)
            ->will($this->returnValue($repo));

        $this->assertSame(
            $repo,
            $this->doctrineHelper->getEntityRepositoryForClass($class)
        );
    }

    public function testGetEntityRepositoryForClassNotManageableEntity()
    {
        $class = 'ItemStub';

        $this->expectException(\Oro\Bundle\EntityBundle\Exception\NotManageableEntityException::class);
        $this->expectExceptionMessage(sprintf('Entity class "%s" is not manageable', $class));

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityRepositoryForClass($class);
    }

    public function testCreateQueryBuilderWithoutIndexBy()
    {
        $class = 'ItemStub';
        $alias = 'itemAlias';
        $qb = $this->createMock(QueryBuilder::class);

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->willReturn($this->em);
        $this->em->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($qb);
        $qb->expects($this->once())
            ->method('from')
            ->with($class, $alias, null)
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('select')
            ->with($alias)
            ->willReturnSelf();

        $this->assertSame(
            $qb,
            $this->doctrineHelper->createQueryBuilder($class, $alias)
        );
    }

    public function testCreateQueryBuilderWithIndexBy()
    {
        $class = 'ItemStub';
        $alias = 'itemAlias';
        $indexBy = 'itemIndexBy';
        $qb = $this->createMock(QueryBuilder::class);

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->willReturn($this->em);
        $this->em->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($qb);
        $qb->expects($this->once())
            ->method('from')
            ->with($class, $alias, $indexBy)
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('select')
            ->with($alias)
            ->willReturnSelf();

        $this->assertSame(
            $qb,
            $this->doctrineHelper->createQueryBuilder($class, $alias, $indexBy)
        );
    }

    public function testGetEntityReference()
    {
        $expectedResult = $this->createMock(\stdClass::class);
        $entityClass    = 'MockEntity';
        $entityId       = 100;

        $this->em->expects($this->once())
            ->method('getReference')
            ->with($entityClass, $entityId)
            ->will($this->returnValue($expectedResult));
        $this->registry->expects($this->any())
            ->method('getManagerForClass')
            ->with($entityClass)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            $expectedResult,
            $this->doctrineHelper->getEntityReference($entityClass, $entityId)
        );
    }

    public function testGetEntity()
    {
        $expectedResult = new TestEntity();
        $entityClass    = 'MockEntity';
        $entityId       = 100;

        $this->registry->expects($this->any())
            ->method('getManagerForClass')
            ->with($entityClass)
            ->willReturn($this->em);
        $this->em->expects($this->once())
            ->method('find')
            ->with($entityClass, $entityId)
            ->willReturn($expectedResult);

        $this->assertSame(
            $expectedResult,
            $this->doctrineHelper->getEntity($entityClass, $entityId)
        );
    }

    public function testCreateEntityInstance()
    {
        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->once())
            ->method('newInstance')
            ->will($this->returnValue($entity));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $entity,
            $this->doctrineHelper->createEntityInstance($class)
        );
    }

    public function testRefreshIncludingUnitializedRelations()
    {
        $itemsToRefresh = [
            new ItemStub(['id' => 0]),
            new ItemStub(['id' => 1]),
        ];

        $entity = new ItemStub();
        $entity->cascadeRefreshPersistentInitializedCollection = new PersistentCollection(
            $this->em,
            'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
            new ArrayCollection([
                $itemsToRefresh[0],
            ])
        );
        $entity->cascadeRefreshPersistentUninitializedCollection = new PersistentCollection(
            $this->em,
            'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
            new ArrayCollection([
                $itemsToRefresh[1],
            ])
        );
        $entity->cascadeRefreshPersistentUninitializedCollection->setInitialized(false);
        $entity->persistentUninitializedCollection = new PersistentCollection(
            $this->em,
            'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
            new ArrayCollection([
                $itemsToRefresh[1],
            ])
        );
        $entity->persistentUninitializedCollection->setInitialized(false);

        $entityMetadata = new ClassMetadata('Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub');
        $entityMetadata->reflFields = [
            'cascadeRefreshPersistentInitializedCollection' => new ReflectionProperty(
                'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
                'cascadeRefreshPersistentInitializedCollection',
                [spl_object_hash($entity) => $entity->cascadeRefreshPersistentInitializedCollection]
            ),
            'cascadeRefreshPersistentUninitializedCollection' => new ReflectionProperty(
                'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
                'cascadeRefreshPersistentInitializedCollection',
                [spl_object_hash($entity) => $entity->cascadeRefreshPersistentUninitializedCollection]
            ),
            'persistentUninitializedCollection' => new ReflectionProperty(
                'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
                'persistentUninitializedCollection',
                [spl_object_hash($entity) => $entity->persistentUninitializedCollection]
            ),
        ];
        $entityMetadata->associationMappings = [
            [
                'fieldName' => 'cascadeRefreshPersistentInitializedCollection',
                'isCascadeRefresh' => true,
            ],
            [
                'fieldName' => 'cascadeRefreshPersistentUninitializedCollection',
                'isCascadeRefresh' => true,
            ],
            [
                'fieldName' => 'persistentUninitializedCollection',
                'isCascadeRefresh' => false,
            ],
        ];

        $this->registry->expects($this->any())
            ->method('getManagerForClass')
            ->will($this->returnValueMap([
                ['Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub', $this->em],
            ]));

        $this->em->expects($this->any())
            ->method('getClassMetadata')
            ->will($this->returnValueMap([
                ['Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub', $entityMetadata],
            ]));

        $this->em->expects($this->exactly(2))
            ->method('refresh')
            ->withConsecutive(
                [$entity],
                [$itemsToRefresh[1]]
            );

        $this->doctrineHelper->refreshIncludingUnitializedRelations($entity);
    }
}
