<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Functional\DataFixtures;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;

class LoadWorkflowDefinitions extends AbstractFixture implements ContainerAwareInterface
{
    const NO_START_STEP    = 'test_flow';
    const WITH_START_STEP  = 'test_start_step_flow';
    const START_TRANSITION = 'start_transition';
    const MULTISTEP_START_TRANSITION = 'starting_point_transition';
    const MULTISTEP = 'test_multistep_flow';
    const WITH_GROUPS1 = 'test_groups_flow1';
    const WITH_GROUPS2 = 'test_groups_flow2';

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager)
    {
        $hasDefinitions = false;

        $listConfiguration = $this->container->get('oro_workflow.configuration.config.workflow_list');
        $configurationBuilder = $this->container->get('oro_workflow.configuration.builder.workflow_definition');

        $workflowConfiguration = $this->getWorkflowConfiguration();
        $workflowConfiguration = $listConfiguration->processConfiguration($workflowConfiguration);
        $workflowDefinitions = $configurationBuilder->buildFromConfiguration($workflowConfiguration);

        foreach ($workflowDefinitions as $workflowDefinition) {
            if ($manager->getRepository('OroWorkflowBundle:WorkflowDefinition')->find($workflowDefinition->getName())) {
                continue;
            }

            if (self::WITH_START_STEP === $workflowDefinition->getName()) {
                $workflowDefinition->setSystem(true);
            }

            $manager->persist($workflowDefinition);
            $hasDefinitions = true;
        }

        if ($hasDefinitions) {
            $manager->flush();
        }
    }

    /**
     * @return array
     */
    protected function getWorkflowConfiguration()
    {
        return Yaml::parse(file_get_contents(__DIR__ . '/config/workflows.yml')) ? : [];
    }
}
