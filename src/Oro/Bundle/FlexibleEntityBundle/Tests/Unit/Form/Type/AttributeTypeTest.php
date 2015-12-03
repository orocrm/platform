<?php

namespace Oro\Bundle\FlexibleEntityBundle\Tests\Form\Type;

use Oro\Bundle\FlexibleEntityBundle\Form\Type\AttributeType;
use Symfony\Component\Form\Tests\Extension\Core\Type\TypeTestCase;

/**
 * Test related class
 */
class AttributeTypeTest extends TypeTestCase
{

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();
        $this->type = new AttributeType();
        $this->form = $this->factory->create($this->type);
    }

    /**
     * Test build of form with form type
     */
    public function testFormCreate()
    {
        $this->assertField('id', 'hidden');
        $this->assertField('code', 'text');
        $this->assertField('attributeType', 'choice');
        $this->assertField('required', 'choice');
        $this->assertField('unique', 'choice');
        $this->assertField('translatable', 'choice');
        $this->assertField('scopable', 'choice');
        $this->assertField('searchable', 'choice');

        $this->assertEquals(
            'Oro\Bundle\FlexibleEntityBundle\Entity\Attribute',
            $this->form->getConfig()->getDataClass()
        );

        $this->assertEquals('oro_flexibleentity_attribute', $this->form->getName());
    }

    /**
     * Assert field name and type
     * @param string $name Field name
     * @param string $type Field type alias
     */
    protected function assertField($name, $type)
    {
        $formType = $this->form->get($name);
        $this->assertInstanceOf('\Symfony\Component\Form\Form', $formType);
        $this->assertEquals($type, $formType->getConfig()->getType()->getInnerType()->getName());
    }
}
