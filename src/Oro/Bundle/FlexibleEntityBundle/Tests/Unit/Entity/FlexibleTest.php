<?php
namespace Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Entity;

use Oro\Bundle\FlexibleEntityBundle\Entity\Attribute;

use Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Entity\Demo\Flexible;
use Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Entity\Demo\FlexibleValue;

/**
 * Test related demo class, aims to cover abstract one
 *
 *
 */
class FlexibleTest extends \PHPUnit_Framework_TestCase
{
    protected $flexible;

    protected $attributeCode;

    protected $attribute;

    /**
     * Set up unit test
     */
    public function setUp()
    {
        // create attribute
        $this->attributeCode = 'short_description';
        $this->attribute = new Attribute();
        $this->attribute->setCode($this->attributeCode);
        $this->attribute->setBackendType('varchar');
        // create flexible
        $this->flexible = new Flexible();
        $this->flexible->setValueClass('Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Entity\Demo\FlexibleValue');
        $this->flexible->setAllAttributes(array($this->attributeCode => $this->attribute));
    }

    /**
     * Test related method
     */
    public function testMyField()
    {
        $myfield = 'my field';
        $this->flexible->setMyfield($myfield);
        $this->assertEquals($this->flexible->getMyfield(), $myfield);
    }

    /**
     * Test related method
     */
    public function testGetLocale()
    {
        $code = 'fr';
        $this->flexible->setLocale($code);
        $this->assertEquals($this->flexible->getLocale(), $code);
    }

    /**
     * Test related method
     */
    public function testGetScope()
    {
        $code = 'mobile';
        $this->flexible->setScope($code);
        $this->assertEquals($this->flexible->getScope(), $code);
    }

    /**
     * Test related method
     */
    public function testGetId()
    {
        $this->assertNull($this->flexible->getId());
    }

    /**
     * Test related method
     */
    public function testUpdated()
    {
        $date = new \DateTime();
        $this->flexible->setUpdated($date);
        $this->assertEquals($this->flexible->getUpdated(), $date);
    }

    /**
     * Test related method
     */
    public function testCreated()
    {
        $date = new \DateTime();
        $this->flexible->setCreated($date);
        $this->assertEquals($this->flexible->getCreated(), $date);
    }

    /**
     * Test related method
     */
    public function testValues()
    {
        // create value
        $data = 'my test value';
        $value = new FlexibleValue();
        $value->setAttribute($this->attribute);
        $value->setData($data);

        // get / add / remove values
        $this->assertEquals($this->flexible->getValues()->count(), 0);
        $this->flexible->addValue($value);
        $this->assertEquals($this->flexible->getValues()->count(), 1);
        $this->assertEquals($this->flexible->getValue($this->attributeCode), $value);
        $this->assertEquals($this->flexible->short_description, $data);
        $this->flexible->removeValue($value);
        $this->assertEquals($this->flexible->getValues()->count(), 0);

        // test magic method
        $this->flexible->setShortDescription('my value');
        $this->assertEquals($this->flexible->getShortDescription(), 'my value');
    }
}
