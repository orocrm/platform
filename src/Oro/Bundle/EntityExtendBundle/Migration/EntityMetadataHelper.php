<?php

namespace Oro\Bundle\EntityExtendBundle\Migration;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\ManagerRegistry;

/**
 * Provides a way to get metadata information from the Doctrine by entity class or table name.
 */
class EntityMetadataHelper
{
    /**
     * @var ManagerRegistry
     */
    protected $doctrine;

    /**
     * @var array {table name} => [{class name}, ...]
     */
    protected $tableToClassesMap;

    /**
     * @var string[] {class name} => {table name}
     */
    protected $classToTableMap;

    /**
     * @var string[][] {class name} => [{field name}, ...]
     */
    protected $classToColumnsMap;

    /**
     * @param ManagerRegistry $doctrine
     */
    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @param string $tableName
     *
     * @return string[]
     */
    public function getEntityClassesByTableName($tableName)
    {
        $this->ensureNameMapsLoaded();

        return $this->tableToClassesMap[$tableName] ?? [];
    }

    /**
     * Gets an entity table name by entity full class name
     *
     * @param string $className
     * @return string|null
     */
    public function getTableNameByEntityClass($className)
    {
        $this->ensureNameMapsLoaded();

        return $this->classToTableMap[$className] ?? null;
    }

    /**
     * @param string $className
     * @return string[]
     */
    public function getEntityColumnsByEntityClass($className)
    {
        $this->ensureNameMapsLoaded();

        return $this->classToColumnsMap[$className] ?? [];
    }

    /**
     * @param string $className
     * @param string $columnName
     * @return bool
     */
    public function isEntityClassContainsColumn($className, $columnName)
    {
        $this->ensureNameMapsLoaded();

        $columns = $this->getEntityColumnsByEntityClass($className);

        return !$columns || in_array($columnName, $columns, true);
    }

    /**
     * Gets an entity field name by entity table name and column name
     *
     * @param string $tableName
     * @param string $columnName
     * @return string|null
     */
    public function getFieldNameByColumnName($tableName, $columnName)
    {
        $classNames = $this->getEntityClassesByTableName($tableName);
        foreach ($classNames as $className) {
            $manager = $this->doctrine->getManagerForClass($className);
            if ($manager instanceof EntityManager) {
                return $manager->getClassMetadata($className)->getFieldName($columnName);
            }
        }

        return null;
    }

    /**
     * Gets an entity table column name by entity name and field name
     *
     * @param string $className
     * @param string $fieldName
     * @return string|null
     */
    public function getColumnNameByFieldName($className, $fieldName)
    {
        $manager = $this->doctrine->getManagerForClass($className);
        if ($manager instanceof EntityManager) {
            return $manager->getClassMetadata($className)->getColumnName($fieldName);
        }

        return null;
    }

    /**
     * Adds a mapping between a table name and entity class name.
     * This method can be used for new entities without doctrine mapping created during
     * loading migrations, for instance for custom entities.
     *
     * @param string $tableName
     * @param string $className
     */
    public function registerEntityClass($tableName, $className)
    {
        $this->ensureNameMapsLoaded();

        $this->tableToClassesMap[$tableName][] = $className;
        $this->classToTableMap[$className] = $tableName;
    }

    /**
     * Makes sure that table name <-> entity class name maps loaded
     */
    protected function ensureNameMapsLoaded()
    {
        if (null === $this->tableToClassesMap) {
            $this->loadNameMaps();
        }
    }

    /**
     * Loads table name <-> entity class name maps
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function loadNameMaps()
    {
        $this->tableToClassesMap = [];
        $this->classToTableMap = [];
        $this->classToColumnsMap = [];
        $names = $this->doctrine->getManagerNames();
        foreach ($names as $name => $id) {
            $manager = $this->doctrine->getManager($name);
            if ($manager instanceof EntityManager) {
                $allMetadata = $this->getAllMetadata($manager);
                foreach ($allMetadata as $metadata) {
                    $tableName = $metadata->getTableName();
                    if (!empty($tableName)) {
                        $className = $metadata->getName();
                        $this->tableToClassesMap[$tableName][] = $className;
                        $this->classToTableMap[$className] = $tableName;

                        // Column mapping
                        $this->classToColumnsMap[$className] = $metadata->getColumnNames();

                        if (!empty($metadata->discriminatorColumn)) {
                            $this->classToColumnsMap[$className][] = $metadata->discriminatorColumn['name'];
                        }

                        foreach ($metadata->getAssociationMappings() as $relation) {
                            if (array_key_exists('joinColumns', $relation) && is_array($relation['joinColumns'])) {
                                foreach ($relation['joinColumns'] as $joinColumn) {
                                    $this->classToColumnsMap[$className][] = $joinColumn['name'];
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Loads the metadata of all entities known to the given entity manager
     * mapping driver.
     *
     * @param EntityManager $em
     * @return ClassMetadata[]
     */
    protected function getAllMetadata(EntityManager $em)
    {
        try {
            return $em->getMetadataFactory()->getAllMetadata();
        } catch (\ReflectionException $ex) {
            // one of a reason $em->getMetadataFactory()->getAllMetadata() fails is
            // because there are some dynamic fields, for example for manyToOne relations and in case if
            // a field declaration in PHP class and metadata created by an event listener is different
            // Doctrine MetadataFactory fails. One of example when it happens is renaming extended column.
            // try to load metadata using low level algorithm based on Doctrine drivers
            // please note that metadata retrieved in this way is not full and can be used only to get a table name
            $result = [];
            $configuration = $em->getConfiguration();
            $driver        = $configuration->getMetadataDriverImpl();
            $allClassNames = $driver->getAllClassNames();
            foreach ($allClassNames as $className) {
                $metadata = new ClassMetadata($className, $configuration->getNamingStrategy());
                $driver->loadMetadataForClass($className, $metadata);
                $result[] = $metadata;
            }

            return $result;
        }
    }
}
