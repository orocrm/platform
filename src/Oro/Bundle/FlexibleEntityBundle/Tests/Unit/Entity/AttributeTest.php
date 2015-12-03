<?php
namespace Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Entity;

use Oro\Bundle\FlexibleEntityBundle\AttributeType\AbstractAttributeType;
use Oro\Bundle\FlexibleEntityBundle\Entity\Attribute;
use Oro\Bundle\FlexibleEntityBundle\Entity\AttributeOption;
use Oro\Bundle\FlexibleEntityBundle\Entity\AttributeOptionValue;

/**
 * Test related class
 *
 *
 */
class AttributeTest extends \PHPUnit_Framework_TestCase
{
    protected $attribute;
    protected $attributeCode  = 'sku';

    /**
     * Set up unit test
     */
    public function setUp()
    {
        // create attribute
        $this->attribute = new Attribute();
    }

    /**
     * Test related method
     */
    public function testGetId()
    {
        $myid = 123;
        $this->attribute->setId(123);
        $this->assertEquals($this->attribute->getId(), 123);
    }

    /**
     * Test related method
     */
    public function testGetCode()
    {
        $attribute = new Attribute();
        $attribute->setCode($this->attributeCode);
        $this->assertEquals($attribute->getCode(), $this->attributeCode);
    }

    /**
     * Test related method
     */
    public function testGetEntityType()
    {
        $entityType = 'Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Entity\Demo\Flexible';
        $this->attribute->setEntityType($entityType);
        $this->assertEquals($this->attribute->getEntityType(), $entityType);
    }

    /**
     * Test related method
     */
    public function testGetBackendStorage()
    {
        $storage = AbstractAttributeType::BACKEND_STORAGE_ATTRIBUTE_VALUE;
        $this->attribute->setBackendStorage($storage);
        $this->assertEquals($this->attribute->getBackendStorage(), $storage);
    }

    /**
     * Test related method
     */
    public function testGetBackendType()
    {
        $type = AbstractAttributeType::BACKEND_TYPE_VARCHAR;
        $this->attribute->setBackendType($type);
        $this->assertEquals($this->attribute->getBackendType(), $type);
    }

    /**
     * Test related method
     */
    public function testUpdated()
    {
        $date = new \DateTime();
        $this->attribute->setUpdated($date);
        $this->assertEquals($this->attribute->getUpdated(), $date);
    }

    /**
     * Test related method
     */
    public function testCreated()
    {
        $date = new \DateTime();
        $this->attribute->setCreated($date);
        $this->assertEquals($this->attribute->getCreated(), $date);
    }

    /**
     * Test related method
     */
    public function testGetRequired()
    {
        // false by default
        $this->assertFalse($this->attribute->getRequired());
        $this->attribute->setRequired(true);
        $this->assertTrue($this->attribute->getRequired());
    }

    /**
     * Test related method
     */
    public function testGetUnique()
    {
        // false by default
        $this->assertFalse($this->attribute->getUnique());
        $this->attribute->setUnique(true);
        $this->assertTrue($this->attribute->getUnique());
    }

    /**
     * Test related method
     */
    public function testTranslatable()
    {
        // false by default
        $this->assertFalse($this->attribute->getTranslatable());
        $this->attribute->setTranslatable(true);
        $this->assertTrue($this->attribute->getTranslatable());
    }

    /**
     * Test related method
     */
    public function testSearchable()
    {
        // false by default
        $this->assertFalse($this->attribute->getSearchable());
        $this->attribute->setSearchable(true);
        $this->assertTrue($this->attribute->getSearchable());
    }

    /**
     * Test related method
     */
    public function testScopable()
    {
        // false by default
        $this->assertFalse($this->attribute->getScopable());
        $this->attribute->setScopable(true);
        $this->assertTrue($this->attribute->getScopable());
    }

    /**
     * Test related method
     */
    public function testDefaultValue()
    {
        // null by default
        $this->assertNull($this->attribute->getDefaultValue());
        $myvalue = 'my default value';
        $this->attribute->setDefaultValue($myvalue);
        $this->assertEquals($this->attribute->getDefaultValue(), $myvalue);
    }

    /**
     * Test related method
     */
    public function testConvertDefaultValueToTimestamp()
    {
        $date = new \DateTime('now');
        $this->attribute->setDefaultValue($date);
        $this->attribute->convertDefaultValueToTimestamp();
        $this->assertEquals($this->attribute->getDefaultValue(), $date->format('U'));
    }

    /**
     * Test related method
     */
    public function testConvertDefaultValueToDatetime()
    {
        $date = new \DateTime('now');
        $this->attribute->setDefaultValue($date->format('U'));
        $this->attribute->setAttributeType('oro_flexibleentity_date');
        $this->attribute->convertDefaultValueToDatetime();
        $this->assertEquals($this->attribute->getDefaultValue()->format('U'), $date->format('U'));
    }

    /**
     * Test related method
     */
    public function testConvertDefaultValueToInteger()
    {
        $this->attribute->convertDefaultValueToInteger();
        $this->assertNull($this->attribute->getDefaultValue());

        $this->attribute->setDefaultValue(true);
        $this->attribute->setAttributeType('oro_flexibleentity_integer');
        $this->attribute->convertDefaultValueToInteger();
        $this->assertEquals($this->attribute->getDefaultValue(), 1);
    }

    /**
     * Test related method
     */
    public function testConvertDefaultValueToBoolean()
    {
        $this->attribute->convertDefaultValueToInteger();
        $this->assertNull($this->attribute->getDefaultValue());

        $this->attribute->setDefaultValue(1);
        $this->attribute->setAttributeType('oro_flexibleentity_boolean');
        $this->attribute->convertDefaultValueToBoolean();
        $this->assertEquals($this->attribute->getDefaultValue(), true);
    }

    /**
     * Test related method
     */
    public function testGetOptions()
    {
        // option
        $option = new AttributeOption();
        // option value
        $optionValue = new AttributeOptionValue();
        $option->addOptionValue($optionValue);
        $this->attribute->addOption($option);
        $this->assertEquals($this->attribute->getOptions()->count(), 1);
        $this->attribute->removeOption($option);
        $this->assertEquals($this->attribute->getOptions()->count(), 0);
    }

    /**
     * Test related method
     */
    public function testGetSetSortOrder()
    {
        $this->assertEquals(0, $this->attribute->getSortOrder());

        $this->attribute->setSortOrder(20);
        $this->assertEquals(20, $this->attribute->getSortOrder());
    }
}
