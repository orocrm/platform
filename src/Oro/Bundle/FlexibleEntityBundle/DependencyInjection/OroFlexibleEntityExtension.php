<?php

namespace Oro\Bundle\FlexibleEntityBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;

/**
 * Flexible entity extension
 */
class OroFlexibleEntityExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        // retrieve each flexible entity config from bundles
        $entitiesConfig = array();
        foreach ($container->getParameter('kernel.bundles') as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            if (is_file($file = dirname($reflection->getFilename()).'/Resources/config/flexibleentity.yml')) {
                $bundleConfig = Yaml::parse(realpath($file));
                // merge entity configs
                if (isset($bundleConfig['entities_config'])) {
                    foreach ($bundleConfig['entities_config'] as $entity => $entityConfig) {
                        $entitiesConfig['entities_config'][$entity]= $entityConfig;
                    }
                }
                // merge attribute type configs
                if (isset($bundleConfig['attributes_config'])) {
                    foreach ($bundleConfig['attributes_config'] as $attributeType => $attributeConfig) {
                        $entitiesConfig['attributes_config'][$attributeType]= $attributeConfig;
                    }
                }
            }
        }
        $configs[]= $entitiesConfig;

        // process configurations to validate and merge
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        // load service
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('grid_services.yml');
        $loader->load('attribute_types.yml');
        // set entities config
        $container->setParameter('oro_flexibleentity.flexible_config', $config);
    }
}
