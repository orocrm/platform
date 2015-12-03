<?php

namespace Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Form\Validator;

use Oro\Bundle\FlexibleEntityBundle\AttributeType\AbstractAttributeType;
use Oro\Bundle\FlexibleEntityBundle\Form\Validator\AttributeConstraintGuesser;
use Symfony\Component\Validator\Constraints;

/**
 * Test related class
 */
class ChainedConstraintGuesserTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->target = new AttributeConstraintGuesser;
    }

    public function testInstanceOfContraintGuesserInterface()
    {
        $this->assertInstanceOf(
            'Oro\Bundle\FlexibleEntityBundle\Form\Validator\ConstraintGuesserInterface',
            $this->target
        );
    }

    public function testGuessNotBlankConstraints()
    {
        $this->assertContainsInstanceOf(
            'Symfony\Component\Validator\Constraints\NotBlank',
            $this->target->guessConstraints(
                $this->getAttributeMock(array('required' => true))
            )
        );
    }

    public function testGuessDateConstraints()
    {
        $this->assertContainsInstanceOf(
            'Symfony\Component\Validator\Constraints\Date',
            $this->target->guessConstraints(
                $this->getAttributeMock(array('backendType' => AbstractAttributeType::BACKEND_TYPE_DATE))
            )
        );
    }

    public function testGuessDateTimeConstraints()
    {
        $this->assertContainsInstanceOf(
            'Symfony\Component\Validator\Constraints\DateTime',
            $this->target->guessConstraints(
                $this->getAttributeMock(array('backendType' => AbstractAttributeType::BACKEND_TYPE_DATETIME))
            )
        );
    }

    private function getAttributeMock(array $options)
    {
        $options = array_merge(
            array(
                'required'    => false,
                'backendType' => null,
            ),
            $options
        );

        $attribute = $this->getMock('Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttribute');

        $attribute->expects($this->any())
            ->method('getBackendType')
            ->will($this->returnValue($options['backendType']));

        $attribute->expects($this->any())
            ->method('getRequired')
            ->will($this->returnValue($options['required']));

        return $attribute;
    }

    private function assertContainsInstanceOf($class, $constraints)
    {
        foreach ($constraints as $constraint) {
            if ($constraint instanceof $class) {
                return true;
            }
        }

        throw new \Exception(sprintf('Expecting constraints to contain instance of "%s"', $class));
    }
}
