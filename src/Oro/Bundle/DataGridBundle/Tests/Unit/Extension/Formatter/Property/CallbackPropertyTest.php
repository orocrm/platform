<?php

namespace Oro\Bundle\DataGridBundle\Tests\Unit\Extension\Formatter\Property;

use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Oro\Bundle\DataGridBundle\Extension\Formatter\Property\CallbackProperty;
use Oro\Bundle\DataGridBundle\Extension\Formatter\Property\PropertyConfiguration;

class CallbackPropertyTest extends \PHPUnit\Framework\TestCase
{
    /** @var \stdClass|\PHPUnit\Framework\MockObject\MockObject */
    private $callable;

    /** @var CallbackProperty */
    private $property;

    protected function setUp(): void
    {
        $this->callable = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['virtualMethod'])
            ->getMock();

        $this->property = new CallbackProperty();
    }

    public function testGetRawValue()
    {
        $record = new ResultRecord([]);

        $this->callable->expects($this->once())
            ->method('virtualMethod')
            ->with($record)
            ->willReturn('returnValue');

        $this->property->init(PropertyConfiguration::create([
            CallbackProperty::CALLABLE_KEY => [$this->callable, 'virtualMethod'],
        ]));

        $this->assertSame('returnValue', $this->property->getRawValue($record));
    }

    public function testGetRawValueAndParams()
    {
        $record = new ResultRecord(['param1' => 'value1', 'param2' => 'value2']);

        $this->callable->expects($this->once())
            ->method('virtualMethod')
            ->with('value1', 'value2')
            ->willReturn('returnValue');

        $this->property->init(PropertyConfiguration::create([
            CallbackProperty::CALLABLE_KEY => [$this->callable, 'virtualMethod'],
            CallbackProperty::PARAMS_KEY => ['param1', 'param2'],
        ]));

        $this->assertSame('returnValue', $this->property->getRawValue($record));
    }
}
