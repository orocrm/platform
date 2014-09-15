<?php

namespace Oro\Bundle\IntegrationBundle\Tests\Unit\Form\Type;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\Common\Annotations\AnnotationReader;

use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Templating\Helper\CoreAssetsHelper;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;

use Oro\Bundle\IntegrationBundle\Manager\TypesRegistry;
use Oro\Bundle\IntegrationBundle\Form\Type\IntegrationSelectType;
use Oro\Bundle\TestFrameworkBundle\Test\Doctrine\ORM\OrmTestCase;
use Oro\Bundle\IntegrationBundle\Entity\Channel as Integration;

class IntegrationSelectTypeTest extends OrmTestCase
{
    /** @var  IntegrationSelectType */
    protected $type;

    /** @var TypesRegistry|\PHPUnit_Framework_MockObject_MockObject */
    protected $registry;

    /** @var EntityManager|\PHPUnit_Framework_MockObject_MockObject */
    protected $em;

    /** @var  CoreAssetsHelper|\PHPUnit_Framework_MockObject_MockObject */
    protected $assetHelper;

    protected function setUp()
    {
        $this->registry    = $this->getMockBuilder('Oro\Bundle\IntegrationBundle\Manager\TypesRegistry')
            ->disableOriginalConstructor()->getMock();
        $this->assetHelper = $this->getMockBuilder('Symfony\Component\Templating\Helper\CoreAssetsHelper')
            ->disableOriginalConstructor()->getMock();
        $this->em          = $this->getTestEntityManager();

        $this->type = new IntegrationSelectType($this->em, $this->registry, $this->assetHelper);
    }

    public function tearDown()
    {
        unset($this->type, $this->registry, $this->assetHelper);
    }

    public function testName()
    {
        $this->assertSame('oro_integration_select', $this->type->getName());
    }

    public function testParent()
    {
        $this->assertSame('genemu_jqueryselect2_choice', $this->type->getParent());
    }

    public function testFinishView()
    {
        $this->registry->expects($this->once())->method('getAvailableIntegrationTypesDetailedData')
            ->will(
                $this->returnValue(
                    [
                        'testType1' => ["label" => "oro.type1.label", "icon" => "bundles/acmedemo/img/logo.png"],
                        'testType2' => ["label" => "oro.type2.label"],
                    ]
                )
            );

        $this->assetHelper->expects($this->once())
            ->method('getUrl')
            ->will($this->returnArgument(0));

        $testIntegration1 = new Integration();
        $testIntegration1->setType('testType1');
        $testIntegration1Label = uniqid('label');
        $testIntegration1Id    = uniqid('id');

        $testIntegration2 = new Integration();
        $testIntegration2->setType('testType2');
        $testIntegration2Label = uniqid('label');
        $testIntegration2Id    = uniqid('id');

        $view                  = new FormView();
        $view->vars['choices'] = [
            new ChoiceView($testIntegration1, $testIntegration1Id, $testIntegration1Label),
            new ChoiceView($testIntegration2, $testIntegration2Id, $testIntegration2Label),
        ];

        $this->type->finishView($view, $this->getMock('Symfony\Component\Form\Test\FormInterface'), []);

        $this->assertInstanceOf('Oro\Bundle\FormBundle\Form\Type\ChoiceListItem', $view->vars['choices'][0]->label);
        $this->assertInstanceOf('Oro\Bundle\FormBundle\Form\Type\ChoiceListItem', $view->vars['choices'][1]->label);

        $this->assertSame(
            [
                'data-status' => true,
                'data-icon'   => 'bundles/acmedemo/img/logo.png'
            ],
            $view->vars['choices'][0]->label->getAttr()
        );
    }

    public function testSetDefaultOptions()
    {
        $reader         = new AnnotationReader();
        $metadataDriver = new AnnotationDriver(
            $reader,
            'Oro\Bundle\IntegrationBundle\Tests\Unit\Fixture\Entity'
        );

        $this->em->getConfiguration()->setMetadataDriverImpl($metadataDriver);
        $this->em->getConfiguration()->setEntityNamespaces(
            [
                'OroIntegrationBundle' => 'Oro\Bundle\IntegrationBundle\Tests\Unit\Fixture\Entity'
            ]
        );

        $resolver = new OptionsResolver();
        $this->type->setDefaultOptions($resolver);

        $resolved = $resolver->resolve(
            [
                'configs'       => ['placeholder' => 'testPlaceholder'],
                'allowed_types' => ['testType']
            ]
        );
        $this->assertInstanceOf('Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList', $resolved['choice_list']);
    }
}
