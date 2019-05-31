<?php

namespace Oro\Bundle\MigrationBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Mapping\ClassMetadata;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DumpMigrationsCommand extends ContainerAwareCommand
{
    /**
     * @var array
     */
    protected $allowedTables = [];

    /**
     * @var array
     */
    protected $extendedFieldOptions = [];

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('oro:migration:dump')
            ->addOption('plain-sql', null, InputOption::VALUE_NONE, 'Out schema as plain sql queries')
            ->addOption(
                'bundle',
                null,
                InputOption::VALUE_OPTIONAL,
                'Bundle name for which migration wll be generated'
            )
            ->addOption(
                'migration-version',
                null,
                InputOption::VALUE_OPTIONAL,
                'Migration version',
                'v1_0'
            )
            ->setDescription('Dump existing database structure.');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->version = $input->getOption('migration-version');
        $this->initializeBundleRestrictions($input->getOption('bundle'));
        $this->initializeMetadataInformation();
        $doctrine = $this->getContainer()->get('doctrine');
        /** @var Schema $schema */
        $schema = $doctrine->getConnection()->getSchemaManager()->createSchema();

        if ($input->getOption('plain-sql')) {
            /** @var Connection $connection */
            $connection = $this->getContainer()->get('doctrine')->getConnection();
            $sqls = $schema->toSql($connection->getDatabasePlatform());
            foreach ($sqls as $sql) {
                $output->writeln($sql . ';');
            }
        } else {
            $this->dumpPhpSchema($schema, $output);
        }
    }

    /**
     * @param string $bundle
     */
    protected function initializeBundleRestrictions($bundle)
    {
        if ($bundle) {
            $bundles = $this->getContainer()->getParameter('kernel.bundles');
            if (!array_key_exists($bundle, $bundles)) {
                throw new \InvalidArgumentException(
                    sprintf('Bundle "%s" is not a known bundle', $bundle)
                );
            }
            $this->namespace = substr($bundles[$bundle], 0, strrpos($bundles[$bundle], '\\')) . '\\Entity';
            $this->className = $bundle . 'Installer';
        }
    }

    /**
     * Process metadata information.
     */
    protected function initializeMetadataInformation()
    {
        $doctrine = $this->getContainer()->get('doctrine');
        /** @var ClassMetadata[] $allMetadata */
        $allMetadata = $doctrine->getManager()->getMetadataFactory()->getAllMetadata();
        array_walk(
            $allMetadata,
            function (ClassMetadata $entityMetadata) {
                if ($this->namespace) {
                    if ($entityMetadata->namespace == $this->namespace) {
                        $this->allowedTables[$entityMetadata->getTableName()] = true;
                        foreach ($entityMetadata->getAssociationMappings() as $associationMappingInfo) {
                            if (!empty($associationMappingInfo['joinTable'])) {
                                $joinTableName = $associationMappingInfo['joinTable']['name'];
                                $this->allowedTables[$joinTableName] = true;
                            }
                        }
                        $this->initializeExtendedFieldsOptions($entityMetadata);
                    }
                } else {
                    $this->initializeExtendedFieldsOptions($entityMetadata);
                }
            }
        );
    }

    /**
     * Initialize extended field options by field.
     *
     * @param ClassMetadata $classMetadata
     */
    protected function initializeExtendedFieldsOptions(ClassMetadata $classMetadata)
    {
        $configManager = $this->getConfigManager();
        $className = $classMetadata->getName();
        $tableName = $classMetadata->getTableName();
        foreach ($classMetadata->getFieldNames() as $fieldName) {
            if ($configManager->hasConfig($className, $fieldName)) {
                $columnName = $classMetadata->getColumnName($fieldName);
                $options = $this->getExtendedFieldOptions($className, $fieldName);
                if (!empty($options['extend']['is_extend'])) {
                    $this->extendedFieldOptions[$tableName][$columnName] = $options;
                }
            }
        }
    }

    /**
     * Get extended field options.
     *
     * @param string $className
     * @param string $fieldName
     * @return array
     */
    protected function getExtendedFieldOptions($className, $fieldName)
    {
        $configManager = $this->getConfigManager();
        $config = array();
        foreach ($configManager->getProviders() as $provider) {
            $fieldId = $provider->getId($className, $fieldName);
            $extendedConfig = $configManager->getConfig($fieldId)->all();
            if (!empty($extendedConfig)) {
                $config[$provider->getScope()] = $extendedConfig;
            }
        }

        return $config;
    }

    /**
     * @param Schema          $schema
     * @param OutputInterface $output
     */
    protected function dumpPhpSchema(Schema $schema, OutputInterface $output)
    {
        $visitor = $this->getContainer()->get('oro_migration.tools.schema_dumper');
        $schema->visit($visitor);

        $output->writeln(
            $visitor->dump(
                $this->allowedTables,
                $this->namespace,
                $this->className,
                $this->version,
                $this->extendedFieldOptions
            )
        );
    }

    /**
     * @return ConfigManager
     */
    protected function getConfigManager()
    {
        if (!$this->configManager) {
            $this->configManager = $this->getContainer()->get('oro_entity_config.config_manager');
        }

        return $this->configManager;
    }
}
