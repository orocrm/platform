<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model;

use Oro\Bundle\WorkflowBundle\Configuration\WorkflowConfiguration;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Model\Step;
use Oro\Bundle\WorkflowBundle\Model\Transition;

use Oro\Component\Action\Action\ActionInterface;
use Oro\Component\ConfigExpression\ExpressionInterface;
use Oro\Component\Testing\Unit\EntityTestCaseTrait;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class TransitionTest extends \PHPUnit_Framework_TestCase
{
    use EntityTestCaseTrait;

    public function testAccessors()
    {
        $this->assertPropertyAccessors(
            new Transition(),
            [
                ['name', 'test'],
                ['hidden', true],
                ['start', true],
                ['unavailableHidden', true],
                ['stepTo', $this->getStepMock('testStep')],
                ['frontendOptions', ['key' => 'value']],
                ['formType', 'custom_workflow_transition'],
                ['displayType', 'page'],
                ['destinationPage', 'destination'],
                ['formOptions', ['one', 'two']],
                ['pageTemplate', 'Workflow:Test:page_template.html.twig'],
                ['dialogTemplate', 'Workflow:Test:dialog_template.html.twig'],
                ['scheduleCron', '1 * * * *'],
                ['scheduleFilter', "e.field < DATE_ADD(NOW(), 1, 'day')"],
                ['scheduleCheckConditions', true],
                ['preAction', $this->createMock(ActionInterface::class)],
                ['preCondition', $this->createMock(ExpressionInterface::class)],
                ['condition', $this->createMock(ExpressionInterface::class)],
                ['action', $this->createMock(ActionInterface::class)],
                ['initEntities', ['TEST_ENTITY_1', 'TEST_ENTITY_2', 'TEST_ENTITY_3']],
                ['initRoutes', ['TEST_ROUTE_1', 'TEST_ROUTE_2', 'TEST_ROUTE_3']],
                ['initContextAttribute', 'testInitContextAttribute'],
            ]
        );
    }

    public function testHidden()
    {
        $transition = new Transition();
        $this->assertFalse($transition->isHidden());
        $this->assertInstanceOf(
            'Oro\Bundle\WorkflowBundle\Model\Transition',
            $transition->setHidden(true)
        );
        $this->assertTrue($transition->isHidden());
        $this->assertInstanceOf(
            'Oro\Bundle\WorkflowBundle\Model\Transition',
            $transition->setHidden(false)
        );
        $this->assertFalse($transition->isHidden());
    }

    public function testToString()
    {
        $transition = new Transition();
        $transition->setName('test_transition');

        $this->assertEquals('test_transition', (string)$transition);
    }

    /**
     * @dataProvider isAllowedDataProvider
     *
     * @param bool $isAllowed
     * @param bool $expected
     */
    public function testIsAllowed($isAllowed, $expected)
    {
        $workflowItem = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Entity\WorkflowItem')
            ->disableOriginalConstructor()
            ->getMock();

        $obj = new Transition();

        if (null !== $isAllowed) {
            $condition = $this->createMock('Oro\Component\ConfigExpression\ExpressionInterface');
            $condition->expects($this->once())
                ->method('evaluate')
                ->with($workflowItem)
                ->will($this->returnValue($isAllowed));
            $obj->setCondition($condition);
        }

        $this->assertEquals($expected, $obj->isAllowed($workflowItem));
    }

    /**
     * @return array
     */
    public function isAllowedDataProvider()
    {
        return [
            'allowed' => [
                'isAllowed' => true,
                'expected' => true
            ],
            'not allowed' => [
                'isAllowed' => false,
                'expected' => false,
            ],
            'no condition' => [
                'isAllowed' => null,
                'expected' => true,
            ],
        ];
    }

    public function testIsPreConditionAllowedWithPreActions()
    {
        $workflowItem = $this->getMockBuilder(WorkflowItem::class)->disableOriginalConstructor()->getMock();

        $obj = new Transition();

        $action = $this->createMock(ActionInterface::class);
        $action->expects($this->once())->method('execute')->with($workflowItem);
        $obj->setPreAction($action);

        $condition = $this->createMock(ExpressionInterface::class);
        $condition->expects($this->once())->method('evaluate')->with($workflowItem)->willReturn(true);
        $obj->setCondition($condition);

        $this->assertTrue($obj->isAllowed($workflowItem));
    }

    /**
     * @dataProvider isAllowedDataProvider
     *
     * @param bool $isAllowed
     * @param bool $expected
     */
    public function testIsAvailableWithForm($isAllowed, $expected)
    {
        $workflowItem = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Entity\WorkflowItem')
            ->disableOriginalConstructor()
            ->getMock();

        $obj = new Transition();
        $obj->setFormOptions(['key' => 'value']);

        if (null !== $isAllowed) {
            $condition = $this->createMock('Oro\Component\ConfigExpression\ExpressionInterface');
            $condition->expects($this->once())
                ->method('evaluate')
                ->with($workflowItem)
                ->will($this->returnValue($isAllowed));
            $obj->setPreCondition($condition);
        }

        $this->assertEquals($expected, $obj->isAvailable($workflowItem));
    }

    /**
     * @dataProvider isAvailableDataProvider
     *
     * @param bool $isAllowed
     * @param bool $isAvailable
     * @param bool $expected
     */
    public function testIsAvailableWithoutForm($isAllowed, $isAvailable, $expected)
    {
        $workflowItem = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Entity\WorkflowItem')
            ->disableOriginalConstructor()
            ->getMock();

        $obj = new Transition();

        if (null !== $isAvailable) {
            $preCondition = $this->createMock('Oro\Component\ConfigExpression\ExpressionInterface');
            $preCondition->expects($this->any())
                ->method('evaluate')
                ->with($workflowItem)
                ->will($this->returnValue($isAvailable));
            $obj->setPreCondition($preCondition);
        }
        if (null !== $isAllowed) {
            $condition = $this->createMock('Oro\Component\ConfigExpression\ExpressionInterface');
            $condition->expects($this->any())
                ->method('evaluate')
                ->with($workflowItem)
                ->will($this->returnValue($isAllowed));
            $obj->setCondition($condition);
        }

        $this->assertEquals($expected, $obj->isAvailable($workflowItem));
    }

    /**
     * @return array
     */
    public function isAvailableDataProvider()
    {
        return [
            'allowed' => [
                'isAllowed' => true,
                'isAvailable' => true,
                'expected' => true
            ],
            'not allowed #1' => [
                'isAllowed' => false,
                'isAvailable' => true,
                'expected' => false,
            ],
            'not allowed #2' => [
                'isAllowed' => true,
                'isAvailable' => false,
                'expected' => false,
            ],
            'not allowed #3' => [
                'isAllowed' => false,
                'isAvailable' => false,
                'expected' => false,
            ],
            'no conditions' => [
                'isAllowed' => null,
                'isAvailable' => null,
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider transitDisallowedDataProvider
     *
     * @param bool $preConditionAllowed
     * @param bool $conditionAllowed
     *
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\ForbiddenTransitionException
     * @expectedExceptionMessage Transition "test" is not allowed.
     */
    public function testTransitNotAllowed($preConditionAllowed, $conditionAllowed)
    {
        $workflowItem = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Entity\WorkflowItem')
            ->disableOriginalConstructor()
            ->getMock();
        $workflowItem->expects($this->never())
            ->method('setCurrentStep');

        $preCondition = $this->createMock('Oro\Component\ConfigExpression\ExpressionInterface');
        $preCondition->expects($this->any())
            ->method('evaluate')
            ->with($workflowItem)
            ->will($this->returnValue($preConditionAllowed));

        $condition = $this->createMock('Oro\Component\ConfigExpression\ExpressionInterface');
        $condition->expects($this->any())
            ->method('evaluate')
            ->with($workflowItem)
            ->will($this->returnValue($conditionAllowed));

        $action = $this->createMock('Oro\Component\Action\Action\ActionInterface');
        $action->expects($this->never())
            ->method('execute');

        $obj = new Transition();
        $obj->setName('test');
        $obj->setPreCondition($preCondition);
        $obj->setCondition($condition);
        $obj->setAction($action);
        $obj->transit($workflowItem);
    }

    /**
     * @return array
     */
    public function transitDisallowedDataProvider()
    {
        return [
            [false, false],
            [false, true],
            [true, false]
        ];
    }

    /**
     * @dataProvider transitDataProvider
     *
     * @param boolean $isFinal
     * @param boolean $hasAllowedTransition
     */
    public function testTransit($isFinal, $hasAllowedTransition)
    {
        $currentStepEntity = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Entity\WorkflowStep')
            ->disableOriginalConstructor()
            ->getMock();

        $step = $this->getStepMock('currentStep', $isFinal, $hasAllowedTransition, $currentStepEntity);

        $workflowDefinition = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition')
            ->disableOriginalConstructor()
            ->getMock();
        $workflowDefinition->expects($this->once())
            ->method('getStepByName')
            ->with($step->getName())
            ->will($this->returnValue($currentStepEntity));

        $workflowItem = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Entity\WorkflowItem')
            ->disableOriginalConstructor()
            ->getMock();
        $workflowItem->expects($this->once())
            ->method('getDefinition')
            ->will($this->returnValue($workflowDefinition));
        $workflowItem->expects($this->once())
            ->method('setCurrentStep')
            ->with($currentStepEntity);

        $preCondition = $this->createMock('Oro\Component\ConfigExpression\ExpressionInterface');
        $preCondition->expects($this->once())
            ->method('evaluate')
            ->with($workflowItem)
            ->will($this->returnValue(true));

        $condition = $this->createMock('Oro\Component\ConfigExpression\ExpressionInterface');
        $condition->expects($this->once())
            ->method('evaluate')
            ->with($workflowItem)
            ->will($this->returnValue(true));

        $action = $this->createMock('Oro\Component\Action\Action\ActionInterface');
        $action->expects($this->once())
            ->method('execute')
            ->with($workflowItem);

        $obj = new Transition();
        $obj->setPreCondition($preCondition);
        $obj->setCondition($condition);
        $obj->setAction($action);
        $obj->setStepTo($step);
        $obj->transit($workflowItem);
    }

    /**
     * @return array
     */
    public function transitDataProvider()
    {
        return [
            [true, true],
            [true, false],
            [false, false]
        ];
    }

    /**
     * @param string $name
     * @param bool $isFinal
     * @param bool $hasAllowedTransitions
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|Step
     */
    protected function getStepMock($name, $isFinal = false, $hasAllowedTransitions = true)
    {
        $step = $this->getMockBuilder(Step::class)->disableOriginalConstructor()->getMock();
        $step->expects($this->any())->method('getName')->willReturn($name);
        $step->expects($this->any())->method('isFinal')->willReturn($isFinal);
        $step->expects($this->any())->method('hasAllowedTransitions')->willReturn($hasAllowedTransitions);

        return $step;
    }

    public function testStart()
    {
        $obj = new Transition();
        $this->assertFalse($obj->isStart());
        $obj->setStart(true);
        $this->assertTrue($obj->isStart());
    }

    public function testGetSetFrontendOption()
    {
        $obj = new Transition();

        $this->assertEquals([], $obj->getFrontendOptions());

        $frontendOptions = ['class' => 'foo', 'icon' => 'bar'];
        $obj->setFrontendOptions($frontendOptions);
        $this->assertEquals($frontendOptions, $obj->getFrontendOptions());
    }

    public function testHasForm()
    {
        $obj = new Transition();

        $this->assertFalse($obj->hasForm()); // by default transition has form

        $obj->setFormOptions(['key' => 'value']);
        $this->assertFalse($obj->hasForm());

        $obj->setFormOptions(['attribute_fields' => []]);
        $this->assertFalse($obj->hasForm());

        $obj->setFormOptions(['attribute_fields' => ['key' => 'value']]);
        $this->assertTrue($obj->hasForm());
    }

    public function testHasFormWithFormConfiguration()
    {
        $obj = new Transition();

        $this->assertFalse($obj->hasForm()); // by default transition has form

        $obj->setFormOptions(['key' => 'value']);
        $this->assertFalse($obj->hasForm());

        $obj->setFormOptions(['configuration' => []]);
        $this->assertFalse($obj->hasForm());

        $obj->setFormOptions(['configuration' => ['key' => 'value']]);
        $this->assertTrue($obj->hasForm());
    }

    /**
     * @dataProvider initContextProvider
     *
     * @param array $entities
     * @param array $routes
     * @param array $datagrids
     * @param bool $result
     */
    public function testIsNotEmptyInitContext(array $entities, array $routes, array $datagrids, $result)
    {
        $transition = new Transition();
        $transition->setInitEntities($entities);
        $transition->setInitRoutes($routes);
        $transition->setInitDatagrids($datagrids);
        $this->assertSame($result, $transition->isEmptyInitOptions());
    }

    /**
     * @return array
     */
    public function initContextProvider()
    {
        return [
            'empty' => [
                'entities' => [],
                'routes' => [],
                'datagrids' => [],
                'result' => true
            ],
            'only entity' => [
                'entities' => ['entity'],
                'routes' => [],
                'datagrids' => [],
                'result' => false
            ],
            'only route' => [
                'entities' => [],
                'routes' => ['route'],
                'datagrids' => [],
                'result' => false
            ],
            'only datagrid' => [
                'entities' => [],
                'routes' => [],
                'datagrids' => ['datagrid'],
                'result' => false
            ],
            'full' => [
                'entities' => ['entity'],
                'routes' => ['route'],
                'datagrids' => ['datagrid'],
                'result' => false
            ],
            'full with arrays' => [
                'entities' => ['entity1', 'entity2'],
                'routes' => ['route1', 'route2'],
                'datagrids' => ['datagrid1', 'datagrid2'],
                'result' => false
            ]
        ];
    }

    public function testFormOptionsConfiguration()
    {
        $obj = new Transition();

        $this->assertEquals([], $obj->getFormOptions());
        $this->assertFalse($obj->hasFormConfiguration());

        $formConfiguration = [
            'handler' => 'handler',
            'template' => 'template',
            'data_provider' => 'data_provider',
            'data_attribute' => 'data_attribute',
        ];
        $formOptions = [WorkflowConfiguration::NODE_FORM_OPTIONS_CONFIGURATION => $formConfiguration];

        $obj->setFormOptions($formOptions);

        $this->assertTrue($obj->hasFormConfiguration());
        $this->assertEquals($formConfiguration['handler'], $obj->getFormHandler());
        $this->assertEquals($formConfiguration['template'], $obj->getFormTemplate());
        $this->assertEquals($formConfiguration['data_provider'], $obj->getFormDataProvider());
        $this->assertEquals($formConfiguration['data_attribute'], $obj->getFormDataAttribute());
    }
}
