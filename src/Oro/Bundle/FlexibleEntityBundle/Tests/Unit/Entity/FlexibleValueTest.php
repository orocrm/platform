<?php

namespace Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Entity;

use Doctrine\Common\Collections\ArrayCollection;

use Oro\Bundle\FlexibleEntityBundle\AttributeType\AbstractAttributeType;

use Oro\Bundle\FlexibleEntityBundle\Entity\Attribute;
use Oro\Bundle\FlexibleEntityBundle\Entity\AttributeOption;
use Oro\Bundle\FlexibleEntityBundle\Entity\Media;
use Oro\Bundle\FlexibleEntityBundle\Entity\Metric;
use Oro\Bundle\FlexibleEntityBundle\Entity\Price;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttribute;
use Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Entity\Demo\Flexible;
use Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Entity\Demo\FlexibleValue;

/**
 * Test related demo class, aims to cover abstract one
 */
class FlexibleValueTest extends \PHPUnit_Framework_TestCase
{
    protected $flexible;

    protected $attribute;

    protected $value;

    /**
     * Set up unit test
     */
    public function setUp()
    {
        // create flexible
        $this->flexible = new Flexible();
        // create attribute
        $this->attribute = new Attribute();
        $this->attribute->setCode('mycode');
        $this->attribute->setTranslatable(true);
        $this->attribute->setScopable(true);
        $this->attribute->setBackendType(AbstractAttributeType::BACKEND_TYPE_VARCHAR);
        // create value
        $this->value = new FlexibleValue();
        $this->value->setAttribute($this->attribute);
        $this->value->setEntity($this->flexible);
    }

    /**
     * Test related method
     */
    public function testGetId()
    {
        $this->assertNull($this->value->getId());
    }

    /**
     * Test related method
     */
    public function testGetAttribute()
    {
        $this->assertEquals($this->value->getAttribute(), $this->attribute);
    }

    /**
     * Test related method
     */
    public function testGetLocale()
    {
        $code = 'fr';
        $this->value->setLocale($code);
        $this->assertEquals($this->value->getLocale(), $code);
    }

    /**
     * Test related method
     */
    public function testGetScope()
    {
        $code = 'ecommerce';
        $this->value->setScope($code);
        $this->assertEquals($this->value->getScope(), $code);
    }

    /**
     * Test related method
     *
     * @param string $backendType the attribute backend type
     * @param mixed  $data        the value data
     *
     * @dataProvider valueProvider
     */
    public function testGetData($backendType, $data)
    {
        $this->value->getAttribute()->setBackendType($backendType);

        if ($this->value->getData() instanceof ArrayCollection) {
            $this->assertEquals($this->value->getData()->count(), 0);
        } else {
            $this->assertNull($this->value->getData());
        }

        $this->value->setData($data);
        if ($this->value->getData() instanceof ArrayCollection) {
            $this->assertEquals($this->value->getData()->count(), 1);
        } else {
            $this->assertEquals($this->value->getData(), $data);
        }

        $this->assertTrue(strlen($this->value->__toString()) >= 0);
    }

    /**
     * Data provider
     *
     * @return multitype:multitype:number string
     *
     * @static
     */
    public static function valueProvider()
    {
        $options = new ArrayCollection();
        $option  = new AttributeOption();
        $options->add($option);
        $price = new Price();
        $price->setData(5)->setCurrency('USD');
        $metric = new Metric();
        $metric->setData(12.5)->setUnit('km');

        return array(
            array(AbstractAttributeType::BACKEND_TYPE_TEXT, 'my really loooonnnng text'),
            array(AbstractAttributeType::BACKEND_TYPE_VARCHAR, 'my value'),
            array(AbstractAttributeType::BACKEND_TYPE_INTEGER, 12),
            array(AbstractAttributeType::BACKEND_TYPE_DECIMAL, 123.45),
            array(AbstractAttributeType::BACKEND_TYPE_DATE, new \DateTime()),
            array(AbstractAttributeType::BACKEND_TYPE_DATETIME, new \DateTime()),
            array(AbstractAttributeType::BACKEND_TYPE_OPTION, $option),
            array(AbstractAttributeType::BACKEND_TYPE_OPTIONS, $options),
            array(AbstractAttributeType::BACKEND_TYPE_MEDIA, new Media()),
            array(AbstractAttributeType::BACKEND_TYPE_PRICE, $price),
            array(AbstractAttributeType::BACKEND_TYPE_METRIC, $metric),
        );
    }

    /**
     * Test related method
     */
    public function testGetUnit()
    {
        $data = 5;
        $unit = 'mm';
        $metric = new Metric();
        $metric->setUnit($unit);
        $metric->setData($data);
        $this->value->setData($metric);
        $this->assertEquals($this->value->getData()->getUnit(), $unit);
        $this->assertEquals($this->value->getData()->getData(), $data);
    }

    /**
     * Test related method
     */
    public function testGetCurrency()
    {
        $data = 5;
        $currency = 'USD';
        $price = new Price();
        $price->setData($data);
        $price->setCurrency($currency);
        $this->value->setData($price);
        $this->assertEquals($this->value->getData()->getCurrency(), $currency);
        $this->assertEquals($this->value->getData()->getData(), $data);
    }

    /**
     * Test related method
     */
    public function testGetOption()
    {
        $option = new AttributeOption();
        $this->value->setOption($option);
        $this->assertEquals($this->value->getOption(), $option);
    }
}
