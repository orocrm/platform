<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;

use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Entity\Repository\WorkflowDefinitionRepository;
use Oro\Bundle\WorkflowBundle\Model\Filter\WorkflowDefinitionFilterInterface;
use Oro\Bundle\WorkflowBundle\Model\Filter\WorkflowDefinitionFilters;
use Oro\Bundle\WorkflowBundle\Model\WorkflowAssembler;
use Oro\Bundle\WorkflowBundle\Model\WorkflowRegistry;
use Oro\Bundle\WorkflowBundle\Model\Workflow;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class WorkflowRegistryTest extends \PHPUnit_Framework_TestCase
{
    /** @var WorkflowDefinitionRepository|\PHPUnit_Framework_MockObject_MockObject */
    private $entityRepository;

    /** @var EntityManager|\PHPUnit_Framework_MockObject_MockObject */
    private $entityManager;

    /** @var ManagerRegistry|\PHPUnit_Framework_MockObject_MockObject */
    private $managerRegistry;

    /** @var WorkflowAssembler|\PHPUnit_Framework_MockObject_MockObject */
    private $assembler;

    /** @var WorkflowDefinitionFilters|\PHPUnit_Framework_MockObject_MockObject */
    private $filters;

    /** @var WorkflowDefinitionFilterInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $filter;

    /** @var WorkflowRegistry */
    private $registry;

    protected function setUp()
    {
        $this->entityRepository
            = $this->getMockBuilder(WorkflowDefinitionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->managerRegistry = $this->getMockBuilder(ManagerRegistry::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->entityManager = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->entityManager->expects($this->any())
            ->method('getRepository')
            ->with(WorkflowDefinition::class)
            ->willReturn($this->entityRepository);

        $this->managerRegistry->expects($this->any())
            ->method('getManagerForClass')
            ->with(WorkflowDefinition::class)
            ->willReturn($this->entityManager);

        $this->assembler = $this->getMockBuilder(WorkflowAssembler::class)
            ->disableOriginalConstructor()
            ->setMethods(['assemble'])
            ->getMock();

        $this->filters = $this->getMockBuilder(WorkflowDefinitionFilters::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->filter = $this->createMock(WorkflowDefinitionFilterInterface::class);

        $this->registry = new WorkflowRegistry($this->managerRegistry, $this->assembler, $this->filters);
    }

    protected function tearDown()
    {
        unset(
            $this->entityRepository,
            $this->managerRegistry,
            $this->entityManager,
            $this->configManager,
            $this->assembler,
            $this->filters,
            $this->filter,
            $this->registry
        );
    }

    /**
     * @param WorkflowDefinition|null $workflowDefinition
     * @param Workflow|null $workflow
     */
    public function prepareAssemblerMock($workflowDefinition = null, $workflow = null)
    {
        if ($workflowDefinition && $workflow) {
            $this->assembler->expects($this->once())
                ->method('assemble')
                ->with($workflowDefinition)
                ->willReturn($workflow);
        } else {
            $this->assembler->expects($this->never())
                ->method('assemble');
        }
    }

    public function testGetWorkflowWithDbEntitiesUpdate()
    {
        $workflowName = 'test_workflow';
        $oldDefinition = new WorkflowDefinition();
        $oldDefinition->setName($workflowName)->setLabel('Old Workflow');
        $newDefinition = new WorkflowDefinition();
        $newDefinition->setName($workflowName)->setLabel('New Workflow');

        /** @var Workflow $workflow */
        $workflow = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\Workflow')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
        $workflow->setDefinition($oldDefinition);

        $this->entityRepository->expects($this->at(0))
            ->method('find')
            ->with($workflowName)
            ->will($this->returnValue($oldDefinition));
        $this->entityRepository->expects($this->at(1))
            ->method('find')
            ->with($workflowName)
            ->will($this->returnValue($newDefinition));
        $this->prepareAssemblerMock($oldDefinition, $workflow);
        $this->setUpEntityManagerMock($oldDefinition, false);

        $this->filters->expects($this->once())->method('getFilters')->willReturn(new ArrayCollection([$this->filter]));
        $this->filter->expects($this->once())->method('filter')
            ->with(new ArrayCollection([$oldDefinition]))->willReturn(new ArrayCollection([$oldDefinition]));

        $this->assertEquals($workflow, $this->registry->getWorkflow($workflowName));
        $this->assertEquals($newDefinition, $workflow->getDefinition());
        $this->assertAttributeEquals([$workflowName => $workflow], 'workflowByName', $this->registry);
    }

    /**
     * @expectedException Oro\Bundle\WorkflowBundle\Exception\WorkflowNotFoundException
     * @expectedExceptionMessage Workflow "test_workflow" not found
     */
    public function testGetWorkflowAndFilteredItem()
    {
        $workflowName = 'test_workflow';
        $workflow = $this->createWorkflow($workflowName);
        $workflowDefinition = $workflow->getDefinition();

        $this->entityRepository->expects($this->once())->method('find')
            ->with($workflowName)->willReturn($workflowDefinition);

        $this->prepareAssemblerMock();

        $this->filters->expects($this->once())->method('getFilters')->willReturn(new ArrayCollection([$this->filter]));
        $this->filter->expects($this->once())->method('filter')
            ->with(new ArrayCollection([$workflowDefinition]))->willReturn(new ArrayCollection());

        $this->registry->getWorkflow($workflowName);
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\WorkflowNotFoundException
     * @expectedExceptionMessage Workflow "test_workflow" not found
     */
    public function testGetWorkflowNoUpdatedEntity()
    {
        $workflowName = 'test_workflow';
        $workflow = $this->createWorkflow($workflowName);
        $workflowDefinition = $workflow->getDefinition();

        $this->entityRepository->expects($this->at(0))
            ->method('find')
            ->with($workflowName)
            ->will($this->returnValue($workflowDefinition));
        $this->entityRepository->expects($this->at(1))
            ->method('find')
            ->with($workflowName)
            ->will($this->returnValue(null));
        $this->prepareAssemblerMock($workflowDefinition, $workflow);
        $this->setUpEntityManagerMock($workflowDefinition, false);

        $this->filters->expects($this->once())->method('getFilters')->willReturn(new ArrayCollection());

        $this->registry->getWorkflow($workflowName);
    }

    public function testGetActiveWorkflowsByEntityClass()
    {
        $entityClass = 'testEntityClass';
        $workflowName = 'test_workflow';
        $workflow = $this->createWorkflow($workflowName, $entityClass);
        $workflowDefinition = $workflow->getDefinition();

        $this->entityRepository->expects($this->once())
            ->method('findActiveForRelatedEntity')
            ->with($entityClass)
            ->willReturn([$workflowDefinition]);
        $this->prepareAssemblerMock($workflowDefinition, $workflow);
        $this->setUpEntityManagerMock($workflowDefinition);

        $this->filters->expects($this->once())->method('getFilters')->willReturn(new ArrayCollection());

        $this->assertEquals(
            new ArrayCollection(['test_workflow' => $workflow]),
            $this->registry->getActiveWorkflowsByEntityClass($entityClass)
        );
    }

    /**
     * @param array $groups
     * @param array $activeDefinitions
     * @param array|Workflow[] $expectedWorkflows
     * @param WorkflowDefinitionFilterInterface $filter
     * @dataProvider getActiveWorkflowsByActiveGroupsDataProvider
     */
    public function testGetActiveWorkflowsByActiveGroups(
        array $groups,
        array $activeDefinitions,
        array $expectedWorkflows,
        WorkflowDefinitionFilterInterface $filter
    ) {
        foreach ($expectedWorkflows as $at => $workflow) {
            $this->assembler->expects($this->at($at))
                ->method('assemble')
                ->with($workflow->getDefinition())
                ->willReturn($workflow);
            $this->setUpEntityManagerMock($workflow->getDefinition());
        }

        $this->entityRepository->expects($this->once())
            ->method('findActive')
            ->willReturn($activeDefinitions);

        $this->filters->expects($this->any())->method('getFilters')->willReturn(new ArrayCollection([$filter]));

        $this->assertEquals(
            $expectedWorkflows,
            $this->registry->getActiveWorkflowsByActiveGroups($groups)->getValues()
        );
    }

    /**
     * @return array
     */
    public function getActiveWorkflowsByActiveGroupsDataProvider()
    {
        $workflow1 = $this->createWorkflow('test_workflow1', 'testEntityClass');
        $workflowDefinition1 = $workflow1->getDefinition();
        $workflowDefinition1->setExclusiveActiveGroups(['group1']);
        $filter1 = $this->createDefinitionFilterMock(
            new ArrayCollection([]),
            new ArrayCollection([])
        );

        $filter2 = $this->createDefinitionFilterMock(
            new ArrayCollection(['test_workflow1' => $workflowDefinition1]),
            new ArrayCollection(['test_workflow1' => $workflowDefinition1])
        );

        $workflow2 = $this->createWorkflow('test_workflow2', 'testEntityClass');
        $workflowDefinition2 = $workflow2->getDefinition();
        $workflowDefinition2->setExclusiveActiveGroups(['group2', 'group3']);
        $filter3 = $this->createDefinitionFilterMock(
            new ArrayCollection(['test_workflow1' => $workflowDefinition1]),
            new ArrayCollection([])
        );

        return [
            'empty' => [
                'groups' => [],
                'activeDefinitions' => [],
                'expectedWorkflows' => [],
                'filter' => $filter1
            ],
            'filled' => [
                'groups' => ['group1'],
                'activeDefinitions' => [$workflowDefinition1, $workflowDefinition2],
                'expectedWorkflows' => [$workflow1],
                'filter' => $filter2
            ],
            'filtered' => [
                'groups' => ['group1'],
                'activeDefinitions' => [$workflowDefinition1, $workflowDefinition2],
                'expectedWorkflows' => [],
                'filter' => $filter3
            ]
        ];
    }

    public function testGetActiveWorkflows()
    {
        $workflowName = 'test_workflow';
        $workflow = $this->createWorkflow($workflowName, 'testEntityClass');
        $workflowDefinition = $workflow->getDefinition();
        $workflowDefinition->setExclusiveActiveGroups(['group1']);

        $filter = $this->createDefinitionFilterMock(
            new ArrayCollection([$workflowName => $workflowDefinition]),
            new ArrayCollection([$workflowName => $workflowDefinition])
        );
        $this->filters->expects($this->once())->method('getFilters')->willReturn(new ArrayCollection([$filter]));

        $this->entityRepository->expects($this->once())
            ->method('findActive')
            ->willReturn([$workflowDefinition]);
        $this->prepareAssemblerMock($workflowDefinition, $workflow);
        $this->setUpEntityManagerMock($workflowDefinition);

        $this->assertEquals(
            new ArrayCollection([$workflowName => $workflow]),
            $this->registry->getActiveWorkflows()
        );
    }

    public function testGetActiveWorkflowsNoFeature()
    {
        $workflowName = 'test_workflow';
        $workflow = $this->createWorkflow($workflowName, 'testEntityClass');
        $workflowDefinition = $workflow->getDefinition();
        $workflowDefinition->setExclusiveActiveGroups(['group1']);

        $filter = $this->createDefinitionFilterMock(
            new ArrayCollection([$workflowName => $workflowDefinition]),
            new ArrayCollection([])
        );
        $this->filters->expects($this->once())->method('getFilters')->willReturn(new ArrayCollection([$filter]));

        $this->entityRepository->expects($this->once())
            ->method('findActive')
            ->willReturn([$workflowDefinition]);

        $this->prepareAssemblerMock();

        $this->assertEquals(
            new ArrayCollection(),
            $this->registry->getActiveWorkflows()
        );
    }

    public function testGetActiveWorkflowsByActiveGroupsWithDisabledFeature()
    {
        $workflow1 = $this->createWorkflow('test_workflow1', 'testEntityClass');
        $workflowDefinition1 = $workflow1->getDefinition();
        $workflowDefinition1->setExclusiveActiveGroups(['group1']);

        $workflow2 = $this->createWorkflow('test_workflow2', 'testEntityClass');
        $workflowDefinition2 = $workflow2->getDefinition();
        $workflowDefinition2->setExclusiveActiveGroups(['group2', 'group3']);

        $this->entityRepository->expects($this->once())
            ->method('findActive')
            ->willReturn([$workflowDefinition1, $workflowDefinition2]);

        $filter = $this->createDefinitionFilterMock(
            new ArrayCollection(['test_workflow1' => $workflowDefinition1]),
            new ArrayCollection()
        );

        $this->filters->expects($this->once())->method('getFilters')->willReturn(new ArrayCollection([$filter]));

        $this->assertEmpty($this->registry->getActiveWorkflowsByActiveGroups(['group1']));
    }

    /**
     * @param Collection $in
     * @param Collection $out
     * @return WorkflowDefinitionFilterInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createDefinitionFilterMock(Collection $in, Collection $out)
    {
        $filter = $this->createMock(WorkflowDefinitionFilterInterface::class);
        $filter->expects($this->any())->method('filter')->with($in)->willReturn($out);

        return $filter;
    }

    /**
     * @param WorkflowDefinition $workflowDefinition
     * @param boolean $isEntityKnown
     */
    protected function setUpEntityManagerMock($workflowDefinition, $isEntityKnown = true)
    {
        $unitOfWork = $this->getMockBuilder('Doctrine\ORM\UnitOfWork')
            ->disableOriginalConstructor()
            ->getMock();
        $unitOfWork->expects($this->any())->method('isInIdentityMap')->with($workflowDefinition)
            ->will($this->returnValue($isEntityKnown));

        $this->entityManager->expects($this->any())->method('getUnitOfWork')
            ->will($this->returnValue($unitOfWork));
    }

    protected function setUpEntityManagerMockAllKnown()
    {
        $unitOfWork = $this->getMockBuilder('Doctrine\ORM\UnitOfWork')
            ->disableOriginalConstructor()
            ->getMock();
        $unitOfWork->expects($this->any())->method('isInIdentityMap')->with()->willReturn(true);

        $this->entityManager->expects($this->any())->method('getUnitOfWork')
            ->will($this->returnValue($unitOfWork));
    }

    /**
     * @param string $workflowName
     *
     * @param string|null $relatedEntity
     * @return Workflow|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createWorkflow($workflowName, $relatedEntity = null)
    {
        $workflowDefinition = new WorkflowDefinition();
        $workflowDefinition->setName($workflowName);

        if ($relatedEntity) {
            $workflowDefinition->setRelatedEntity($relatedEntity);
        }

        return $this->createWorkflowFromDefinition($workflowDefinition);
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\WorkflowNotFoundException
     * @expectedExceptionMessage Workflow "not_existing_workflow" not found
     */
    public function testGetWorkflowNotFoundException()
    {
        $workflowName = 'not_existing_workflow';

        $this->entityRepository->expects($this->once())
            ->method('find')
            ->with($workflowName)
            ->willReturn(null);
        $this->prepareAssemblerMock();

        $this->registry->getWorkflow($workflowName);
    }

    private function createWorkflowFromDefinition(WorkflowDefinition $definition)
    {
        /** @var Workflow|\PHPUnit_Framework_MockObject_MockObject $workflow */
        $workflow = $this->getMockBuilder(Workflow::class)
            ->disableOriginalConstructor()
            ->getMock();

        $workflow->expects($this->any())
            ->method('getDefinition')
            ->willReturn($definition);

        $workflow->expects($this->any())
            ->method('getName')
            ->willReturn($definition->getName());

        return $workflow;
    }
}
