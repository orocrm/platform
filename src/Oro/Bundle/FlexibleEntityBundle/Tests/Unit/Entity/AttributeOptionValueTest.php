<?php
namespace Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Entity;

use Oro\Bundle\FlexibleEntityBundle\Entity\Attribute;

use Oro\Bundle\FlexibleEntityBundle\Entity\AttributeOption;

use Oro\Bundle\FlexibleEntityBundle\Entity\AttributeOptionValue;

/**
 * Test related class
 *
 *
 */
class AttributeOptionValueTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @staticvar integer
     */
    protected static $id = 12;

    /**
     * @staticvar string
     */
    protected static $locale = 'en';

    /**
     * @staticvar string
     */
    protected static $value = 'testAttOptValue';

    /**
     * @staticvar string
     */
    protected static $attClass = 'Oro\Bundle\FlexibleEntityBundle\Entity\Attribute';

    /**
     * @staticvar string
     */
    protected static $attOptClass = 'Oro\Bundle\FlexibleEntityBundle\Entity\AttributeOption';

    /**
     * @staticvar string
     */
    protected static $attOptValueClass = 'Oro\Bundle\FlexibleEntityBundle\Entity\AttributeOptionValue';

    /**
     * Test related getter/setter method
     */
    public function testId()
    {
        $attOptValue = new AttributeOptionValue();

        // assert default value is null
        $this->assertNull($attOptValue->getId());

        // assert get/set
        $obj = $attOptValue->setId(self::$id);
        $this->assertInstanceOf(self::$attOptValueClass, $obj);
        $this->assertEquals(self::$id, $attOptValue->getId());
    }

    /**
     * Test related getter/setter method
     */
    public function testGetLocale()
    {
        $attOptValue = new AttributeOptionValue();

        // assert default value is null
        $this->assertNull($attOptValue->getLocale());

        // assert get/set
        $obj = $attOptValue->setLocale(self::$locale);
        $this->assertInstanceOf(self::$attOptValueClass, $obj);
        $this->assertEquals(self::$locale, $attOptValue->getLocale());
    }

    /**
     * Test related getter/setter method
     */
    public function testValue()
    {
        $attOptValue = new AttributeOptionValue();

        // assert default value is null
        $this->assertNull($attOptValue->getValue());

        // assert get/set
        $obj = $attOptValue->setValue(self::$value);
        $this->assertInstanceOf(self::$attOptValueClass, $obj);
        $this->assertEquals(self::$value, $attOptValue->getValue());
    }

    /**
     * Test related getter/setter method
     */
    public function testOption()
    {
        // initialize entities
        $attOpt = new AttributeOption();
        $attOptValue = new AttributeOptionValue();

        // assert get/set
        $obj = $attOptValue->setOption($attOpt);
        $this->assertInstanceOf(self::$attOptValueClass, $obj);
        $this->assertEquals($attOpt, $attOptValue->getOption());
    }
}
