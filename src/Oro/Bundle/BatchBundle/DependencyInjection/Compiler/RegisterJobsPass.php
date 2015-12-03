<?php

namespace Oro\Bundle\BatchBundle\DependencyInjection\Compiler;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * Read the jobs.yml file of the connectors to register the jobs
 *
 */
class RegisterJobsPass implements CompilerPassInterface
{
    /**
     * @var YamlParser
     */
    protected $yamlParser;

    /**
     * @var NodeInterface
     */
    protected $jobsConfig;

    public function __construct($yamlParser = null)
    {
        $this->yamlParser = $yamlParser ?: new YamlParser();
    }

    public function process(ContainerBuilder $container)
    {
        $registry = $container->getDefinition('oro_batch.connectors');

        foreach ($container->getParameter('kernel.bundles') as $bundle) {
            $reflClass = new \ReflectionClass($bundle);
            if (false === $bundleDir = dirname($reflClass->getFileName())) {
                continue;
            }
            // TODO: discuss using of only one file format
            if (is_file($configFile = $bundleDir.'/Resources/config/jobs.yml')) {
                $this->registerJobs($registry, $configFile);
            }
            if (is_file($configFile = $bundleDir.'/Resources/config/batch_jobs.yml')) {
                $this->registerJobs($registry, $configFile);
            }
        }
    }

    private function registerJobs(Definition $definition, $configFile)
    {
        $config = $this->processConfig(
            $this->yamlParser->parse(
                file_get_contents($configFile)
            )
        );

        foreach ($config['jobs'] as $alias => $job) {
            foreach ($job['steps'] as $step) {
                $definition->addMethodCall(
                    'addStepToJob',
                    array(
                        $config['name'],
                        $job['type'],
                        $alias,
                        $job['title'],
                        $step['title'],
                        new Reference($step['reader']),
                        new Reference($step['processor']),
                        new Reference($step['writer']),
                    )
                );
            }
        }
    }

    private function processConfig(array $config)
    {
        $processor = new Processor();
        if (!$this->jobsConfig) {
            $this->jobsConfig = $this->getJobsConfigTree();
        }

        return $processor->process($this->jobsConfig, $config);
    }

    private function getJobsConfigTree()
    {
        $treeBuilder = new TreeBuilder();
        $root = $treeBuilder->root('connector');
        $root
            ->children()
                ->scalarNode('name')->end()
                ->arrayNode('jobs')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('title')->end()
                            ->scalarNode('type')->end()
                            ->arrayNode('steps')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('title')->end()
                                        ->scalarNode('reader')->end()
                                        ->scalarNode('processor')->end()
                                        ->scalarNode('writer')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $treeBuilder->buildTree();
    }
}
