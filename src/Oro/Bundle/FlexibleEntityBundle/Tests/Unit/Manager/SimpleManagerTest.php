<?php

namespace Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Manager;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\FlexibleEntityBundle\Manager\SimpleManager;
use Oro\Bundle\FlexibleEntityBundle\Tests\Unit\AbstractOrmTest;

/**
 * Test related class
 */
class SimpleManagerTest extends AbstractOrmTest
{
    /**
     * @var string
     */
    protected $entityName;

    /**
     * Set up unit test
     */
    public function setUp()
    {
        parent::setUp();
        // prepare simple entity manager (use default entity manager)
        $this->entityName = 'Oro\Bundle\FlexibleEntityBundle\Tests\Unit\\Entity\Demo\Simple';
        $this->manager = new SimpleManager($this->entityName, $this->container->get('doctrine.orm.entity_manager'));
    }

    /**
     * test related method
     */
    public function testConstructWithCustomEntityManager()
    {
        $myManager = new SimpleManager($this->entityName, $this->entityManager);
        $this->assertNotNull($myManager->getStorageManager());
        $this->assertEquals($myManager->getStorageManager(), $this->entityManager);
    }

    /**
     * test related method
     */
    public function testGetStorageManager()
    {
        $this->assertNotNull($this->manager->getStorageManager());
        $this->assertTrue($this->manager->getStorageManager() instanceof EntityManager);
    }

    /**
     * test related method
     */
    public function testGetEntityName()
    {
        $this->assertEquals($this->manager->getEntityName(), $this->entityName);
    }

    /**
     * test related method
     */
    public function testGetEntityRepository()
    {
        $this->assertTrue($this->manager->getEntityRepository() instanceof \Doctrine\ORM\EntityRepository);
    }

    /**
     * test related method
     */
    public function testCreateEntity()
    {
        $this->assertTrue($this->manager->createEntity() instanceof $this->entityName);
    }
}
