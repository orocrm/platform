<?php

namespace Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Manager;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\FlexibleEntityBundle\Manager\FlexibleManager;
use Oro\Bundle\FlexibleEntityBundle\Tests\Unit\AbstractFlexibleManagerTest;

/**
 * Test related class
 */
class FlexibleManagerTest extends AbstractFlexibleManagerTest
{
    /**
     * test related method
     */
    public function testConstructWithCustomEntityManager()
    {
        $myManager = new FlexibleManager(
            $this->flexibleClassName,
            $this->container->getParameter('oro_flexibleentity.flexible_config'),
            $this->entityManager,
            $this->container->get('event_dispatcher'),
            $this->attributeTypeFactory
        );
        $this->assertNotNull($myManager->getStorageManager());
        $this->assertEquals($myManager->getStorageManager(), $this->entityManager);
    }

    /**
     * test related method
     */
    public function testGetStorageManager()
    {
        $this->assertNotNull($this->manager->getStorageManager());
        $this->assertInstanceOf('Doctrine\ORM\EntityManager', $this->manager->getStorageManager());
    }

    /**
     * test related method
     */
    public function testGetFlexibleConfig()
    {
        $this->assertNotNull($this->manager->getFlexibleConfig());
        $this->assertNotEmpty($this->manager->getFlexibleConfig());
        $this->assertEquals(
            $this->manager->getFlexibleConfig(),
            $this->flexibleConfig['entities_config'][$this->flexibleClassName]
        );
    }

    /**
     * Test related method
     */
    public function testGetLocale()
    {
        // get default locale
        $this->assertEquals($this->manager->getLocale(), $this->defaultLocale);
        // forced locale
        $code = 'fr';
        $this->manager->setLocale($code);
        $this->assertEquals($this->manager->getLocale(), $code);
    }

    /**
     * Test related method
     */
    public function testGetScope()
    {
        // get default scope
        $this->assertEquals($this->manager->getScope(), $this->defaultScope);
        // forced scope
        $code = 'ecommerce';
        $this->manager->setScope($code);
        $this->assertEquals($this->manager->getScope(), $code);
    }

    /**
     * Test related method
     */
    public function testGetAttributeName()
    {
        $this->assertEquals($this->manager->getAttributeName(), $this->attributeClassName);
    }

    /**
     * Test related method
     */
    public function testGetAttributeOptionName()
    {
        $this->assertEquals($this->manager->getAttributeOptionName(), $this->attributeOptionClassName);
    }

    /**
     * Test related method
     */
    public function testGetAttributeOptionValueName()
    {
        $this->assertEquals($this->manager->getAttributeOptionValueName(), $this->attributeOptionValueClassName);
    }

    /**
     * Test related method
     */
    public function testGetFlexibleValueName()
    {
        $this->assertEquals($this->manager->getFlexibleValueName(), $this->flexibleValueClassName);
    }

    /**
     * Test related method
     */
    public function testGetFlexibleRepository()
    {
        $this->assertInstanceOf('Doctrine\ORM\EntityRepository', $this->manager->getFlexibleRepository());
    }

    /**
     * Test flexible repository properties $flexibleConfig, $scope and $locale are updated in flexible manager.
     */
    public function testFlexibleRepositoryConfigLocaleAndScopeSynchronized()
    {
        $this->assertEquals(
            $this->manager->getFlexibleConfig(),
            $this->manager->getFlexibleRepository()->getFlexibleConfig()
        );

        $this->manager->setLocale('de');

        $this->assertEquals(
            $this->manager->getLocale(),
            $this->manager->getFlexibleRepository()->getLocale()
        );

        $this->manager->setScope('ecommerce');

        $this->assertEquals(
            $this->manager->getScope(),
            $this->manager->getFlexibleRepository()->getScope()
        );
    }

    /**
     * Test related method
     */
    public function testGetAttributeRepository()
    {
        $this->assertInstanceOf('Doctrine\ORM\EntityRepository', $this->manager->getAttributeRepository());
    }

    /**
     * Test related method
     */
    public function testGetAttributeOptionRepository()
    {
        $this->assertInstanceOf('Doctrine\ORM\EntityRepository', $this->manager->getAttributeOptionRepository());
    }

    /**
     * Test related method
     */
    public function testGetAttributeOptionValueRepository()
    {
        $this->assertInstanceOf('Doctrine\ORM\EntityRepository', $this->manager->getAttributeOptionValueRepository());
    }

    /**
     * Test related method
     */
    public function testGetFlexibleValueRepository()
    {
        $this->assertInstanceOf('Doctrine\ORM\EntityRepository', $this->manager->getFlexibleValueRepository());
    }

    /**
     * Test related method
     */
    public function testCreateAttribute()
    {
        $this->assertInstanceOf($this->attributeClassName, $this->manager->createAttribute('oro_flexibleentity_text'));
    }

    /**
     * Test related method
     */
    public function testCreateAttributeOption()
    {
        $this->assertInstanceOf($this->attributeOptionClassName, $this->manager->createAttributeOption());
    }

    /**
     * Test related method
     */
    public function testCreateAttributeOptionValue()
    {
        $this->assertInstanceOf($this->attributeOptionValueClassName, $this->manager->createAttributeOptionValue());
    }

    /**
     * Test related method
     */
    public function testCreateFlexible()
    {
        $this->markTestSkipped('Issue with post load event mock');
        $this->assertInstanceOf($this->flexibleClassName, $this->manager->createFlexible());
    }

    /**
     * Test related method
     */
    public function testCreateFlexibleValue()
    {
        $this->assertInstanceOf($this->flexibleValueClassName, $this->manager->createFlexibleValue());
    }
}
