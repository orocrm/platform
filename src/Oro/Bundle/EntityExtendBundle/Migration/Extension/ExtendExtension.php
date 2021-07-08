<?php

namespace Oro\Bundle\EntityExtendBundle\Migration\Extension;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Oro\Bundle\EntityBundle\EntityConfig\DatagridScope;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Entity\ConfigModel;
use Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigBag;
use Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\EntityExtendBundle\Migration\EntityMetadataHelper;
use Oro\Bundle\EntityExtendBundle\Migration\ExtendOptionsManager;
use Oro\Bundle\EntityExtendBundle\Migration\OroOptions;
use Oro\Bundle\EntityExtendBundle\Migration\Schema\ExtendTable;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendDbIdentifierNameGenerator;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\MigrationBundle\Migration\Extension\NameGeneratorAwareInterface;
use Oro\Bundle\MigrationBundle\Tools\DbIdentifierNameGenerator;

/**
 * Provides an ability to create extended enum tables and fields, add relations between tables.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ExtendExtension implements NameGeneratorAwareInterface
{
    private const ALLOWED_IDENTITY_FIELDS = ['id', 'name'];

    private const DEFAULT_IDENTITY_FIELDS = ['id'];

    /**
     * @var ExtendOptionsManager
     */
    protected $extendOptionsManager;

    /**
     * @var EntityMetadataHelper
     */
    protected $entityMetadataHelper;

    /**
     * @var ExtendDbIdentifierNameGenerator
     */
    protected $nameGenerator;

    /**
     * @var PropertyConfigBag
     */
    protected $propertyConfigBag;

    /**
     * @param ExtendOptionsManager $extendOptionsManager
     * @param EntityMetadataHelper $entityMetadataHelper
     * @param PropertyConfigBag    $propertyConfigBag
     */
    public function __construct(
        ExtendOptionsManager $extendOptionsManager,
        EntityMetadataHelper $entityMetadataHelper,
        PropertyConfigBag $propertyConfigBag
    ) {
        $this->extendOptionsManager = $extendOptionsManager;
        $this->entityMetadataHelper = $entityMetadataHelper;
        $this->propertyConfigBag = $propertyConfigBag;
    }

    /**
     * {@inheritdoc}
     */
    public function setNameGenerator(DbIdentifierNameGenerator $nameGenerator)
    {
        $this->nameGenerator = $nameGenerator;
    }

    /**
     * @return ExtendDbIdentifierNameGenerator
     */
    public function getNameGenerator()
    {
        return $this->nameGenerator;
    }

    /**
     * Creates a table for a custom entity.
     * The custom entity is an entity which has no PHP class in any bundle. The definition of such entity is
     * created automatically in Symfony cache
     *
     * @param Schema $schema
     * @param string $entityName
     * @param array  $options
     *
     * @return Table
     *
     * @throws \InvalidArgumentException
     */
    public function createCustomEntityTable(
        Schema $schema,
        $entityName,
        array $options = []
    ) {
        $className = ExtendHelper::ENTITY_NAMESPACE . $entityName;
        $tableName = $this->nameGenerator->generateCustomEntityTableName($className);
        $table     = $schema->createTable($tableName);
        $this->entityMetadataHelper->registerEntityClass($tableName, $className);

        $options = new OroOptions($options);
        // set options
        $options->setAuxiliary(ExtendOptionsManager::ENTITY_CLASS_OPTION, $className);
        if ($options->has('extend', 'owner')) {
            if ($options->get('extend', 'owner') !== ExtendScope::OWNER_CUSTOM) {
                throw new \InvalidArgumentException(
                    sprintf('The "extend.owner" option for a custom entity must be "%s".', ExtendScope::OWNER_CUSTOM)
                );
            }
        } else {
            $options->set('extend', 'owner', ExtendScope::OWNER_CUSTOM);
        }
        if ($options->has('extend', 'is_extend')) {
            if ($options->get('extend', 'is_extend') !== true) {
                throw new \InvalidArgumentException(
                    'The "extend.is_extend" option for a custom entity must be TRUE.'
                );
            }
        } else {
            $options->set('extend', 'is_extend', true);
        }
        $table->addOption(OroOptions::KEY, $options);

        // add a primary key
        $primaryKeyColumnName = $this->nameGenerator->getCustomEntityPrimaryKeyColumnName();
        $table->addColumn($primaryKeyColumnName, 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey([$primaryKeyColumnName]);

        return $table;
    }

    /**
     * Creates a table that is used to store enum values for the enum with the given code.
     *
     * @param Schema        $schema
     * @param string        $enumCode   The unique identifier of an enum
     * @param bool          $isMultiple Indicates whether several options can be selected for this enum
     *                                  or it supports only one selected option
     * @param bool          $isPublic   Indicates whether this enum can be used by any entity or
     *                                  it is designed to use in one entity only
     * @param bool|string[] $immutable  Indicates whether the changing the list of enum values and
     *                                  public flag is allowed or not. More details can be found
     *                                  in entity_config.yml
     * @param array         $options
     * @param array         $identityFields
     *
     * @return Table A table that is used to store enum values
     *
     * @throws \InvalidArgumentException
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function createEnum(
        Schema $schema,
        $enumCode,
        $isMultiple = false,
        $isPublic = false,
        $immutable = false,
        array $options = [],
        array $identityFields = self::DEFAULT_IDENTITY_FIELDS
    ) {
        if ($enumCode !== ExtendHelper::buildEnumCode($enumCode)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The enum code "%s" must contain only lower alphabetical symbols, numbers and underscore.',
                    $enumCode
                )
            );
        }

        if (empty($identityFields)) {
            throw new \InvalidArgumentException('At least one identify field is required.');
        }

        if ($invalidIdentifyFields = array_diff($identityFields, self::ALLOWED_IDENTITY_FIELDS)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The identification fields can only be: %s. Current invalid fields: %s.',
                    implode(', ', self::ALLOWED_IDENTITY_FIELDS),
                    implode(', ', $invalidIdentifyFields)
                )
            );
        }

        $tableName = $this->nameGenerator->generateEnumTableName($enumCode);
        $className = ExtendHelper::buildEnumValueClassName($enumCode);

        $options = array_replace_recursive(
            [
                ExtendOptionsManager::MODE_OPTION         => ConfigModel::MODE_HIDDEN,
                ExtendOptionsManager::ENTITY_CLASS_OPTION => $className,
                'entity'                                  => [
                    'label'        => ExtendHelper::getEnumTranslationKey('label', $enumCode),
                    'plural_label' => ExtendHelper::getEnumTranslationKey('plural_label', $enumCode),
                    'description'  => ExtendHelper::getEnumTranslationKey('description', $enumCode)
                ],
                'extend'                                  => [
                    'owner'     => ExtendScope::OWNER_SYSTEM,
                    'is_extend' => true,
                    'table'     => $tableName,
                    'inherit'   => ExtendHelper::BASE_ENUM_VALUE_CLASS
                ],
                'enum'                                    => [
                    'code'     => $enumCode,
                    'public'   => $isPublic,
                    'multiple' => $isMultiple
                ]
            ],
            $options
        );
        if ($immutable) {
            $options['enum']['immutable'] = true;
        }

        $table = $schema->createTable($tableName);
        $this->entityMetadataHelper->registerEntityClass($tableName, $className);
        $table->addOption(OroOptions::KEY, $options);

        $table->addColumn(
            'id',
            'string',
            [
                'length'        => ExtendHelper::MAX_ENUM_VALUE_ID_LENGTH,
                OroOptions::KEY => [
                    'entity'       => [
                        'label'       => ExtendHelper::getEnumTranslationKey('label', $enumCode, 'id'),
                        'description' => ExtendHelper::getEnumTranslationKey('description', $enumCode, 'id')
                    ],
                    'importexport' => [
                        'identity' => in_array('id', $identityFields, true),
                    ],
                ]
            ]
        );
        $table->addColumn(
            'name',
            'string',
            [
                'length'        => 255,
                OroOptions::KEY => [
                    'entity'   => [
                        'label'       => ExtendHelper::getEnumTranslationKey('label', $enumCode, 'name'),
                        'description' => ExtendHelper::getEnumTranslationKey('description', $enumCode, 'name')
                    ],
                    'datagrid' => [
                        'is_visible' => DatagridScope::IS_VISIBLE_FALSE
                    ],
                    'importexport' => [
                        'identity' => in_array('name', $identityFields, true),
                    ],
                ],
            ]
        );
        $table->addColumn(
            'priority',
            'integer',
            [
                OroOptions::KEY => [
                    'entity'   => [
                        'label'       => ExtendHelper::getEnumTranslationKey('label', $enumCode, 'priority'),
                        'description' => ExtendHelper::getEnumTranslationKey('description', $enumCode, 'priority')
                    ],
                    'datagrid' => [
                        'is_visible' => DatagridScope::IS_VISIBLE_FALSE
                    ]
                ]
            ]
        );
        $table->addColumn(
            'is_default',
            'boolean',
            [
                OroOptions::KEY => [
                    ExtendOptionsManager::FIELD_NAME_OPTION => 'default',
                    'entity'                                => [
                        'label'       => ExtendHelper::getEnumTranslationKey('label', $enumCode, 'default'),
                        'description' => ExtendHelper::getEnumTranslationKey('description', $enumCode, 'default')
                    ],
                    'datagrid'                              => [
                        'is_visible' => DatagridScope::IS_VISIBLE_FALSE
                    ]
                ]
            ]
        );
        $table->setPrimaryKey(['id']);

        return $table;
    }

    /**
     * Adds enumerable field
     *
     * Take in attention that this method creates new private enum if the enum with the given code
     * is not exist yet. If you want to create a public enum use {@link createEnum} method before.
     *
     * @param Schema        $schema
     * @param Table|string  $table           A Table object or table name
     * @param string        $associationName The name of a relation field
     * @param string        $enumCode        The target enum identifier
     * @param bool          $isMultiple      Indicates whether several options can be selected for this enum
     *                                       or it supports only one selected option
     * @param bool|string[] $immutable       Indicates whether the changing the list of enum values and
     *                                       public flag is allowed or not. More details can be found
     *                                       in entity_config.yml
     * @param array         $options
     * @param array         $identityFields
     *
     * @return Table A table that is used to store enum values
     */
    public function addEnumField(
        Schema $schema,
        $table,
        $associationName,
        $enumCode,
        $isMultiple = false,
        $immutable = false,
        array $options = [],
        array $identityFields = self::DEFAULT_IDENTITY_FIELDS
    ) {
        $enumTableName = $this->nameGenerator->generateEnumTableName($enumCode);
        $selfTable     = $this->getTable($table, $schema);
        $enumTable     = null;

        // make sure a table that is used to store enum values exists
        if (!$schema->hasTable($enumTableName)) {
            $enumTable = $this->createEnum($schema, $enumCode, $isMultiple, false, $immutable, [], $identityFields);
        } else {
            $enumTable = $this->getTable($enumTableName, $schema);
        }

        // create appropriate relation
        $options['enum']['enum_code'] = $enumCode;
        if ($isMultiple) {
            $options['extend']['without_default'] = true;
            $this->addManyToManyRelation(
                $schema,
                $selfTable,
                $associationName,
                $enumTableName,
                ['name'],
                ['name'],
                ['name'],
                $options,
                'multiEnum'
            );
            // create a column that will contain selected options
            // this column is required to avoid group by clause when multiple enum is shown in a datagrid
            $selfTable->addColumn(
                $this->nameGenerator->generateMultiEnumSnapshotColumnName($associationName),
                'string',
                [
                    'notnull' => false,
                    'length'  => ExtendHelper::MAX_ENUM_SNAPSHOT_LENGTH
                ]
            );
        } else {
            $this->addManyToOneRelation(
                $schema,
                $selfTable,
                $associationName,
                $enumTableName,
                'name',
                $options,
                'enum'
            );
        }

        return $enumTable;
    }

    /**
     * Adds one-to-many relation
     *
     * @param Schema       $schema
     * @param Table|string $table                     A Table object or table name of owning side entity
     * @param string       $associationName           The name of a relation field
     * @param Table|string $targetTable               A Table object or table name of inverse side entity
     * @param string[]     $targetTitleColumnNames    Column names are used to show a title of related entity
     * @param string[]     $targetDetailedColumnNames Column names are used to show detailed info about related entity
     * @param string[]     $targetGridColumnNames     Column names are used to show related entity in a grid
     * @param array        $options                   Entity config options. [scope => [name => value, ...], ...]
     * @param string       $fieldType                 The field type. By default the field type is oneToMany,
     *                                                but you can specify another type if it is based on oneToMany.
     *                                                In this case this type should be registered
     *                                                in entity_extend.yml under underlying_types section
     */
    public function addOneToManyRelation(
        Schema $schema,
        $table,
        $associationName,
        $targetTable,
        array $targetTitleColumnNames,
        array $targetDetailedColumnNames,
        array $targetGridColumnNames,
        array $options = [],
        $fieldType = RelationType::ONE_TO_MANY
    ) {
        $this->validateOptions($options, $fieldType);
        $this->ensureExtendFieldSet($options);
        $options['extend']['bidirectional'] = true; // has to be bidirectional

        $selfTableName = $this->getTableName($table);
        $selfTable     = $this->getTable($table, $schema);
        $selfClassName = $this->getEntityClassByTableName($selfTableName);

        $targetTableName  = $this->getTableName($targetTable);
        $targetTable      = $this->getTable($targetTable, $schema);
        $targetColumnName = $this->nameGenerator->generateOneToManyRelationColumnName(
            $selfClassName,
            $associationName,
            '_' . $this->getPrimaryKeyColumnName($selfTable)
        );

        $this->checkColumnsExist($targetTable, $targetTitleColumnNames);
        $this->checkColumnsExist($targetTable, $targetDetailedColumnNames);
        $this->checkColumnsExist($targetTable, $targetGridColumnNames);

        if (!isset($options['extend']['without_default']) || !$options['extend']['without_default']) {
            $this->addDefaultRelation($selfTable, $associationName, $targetTable);
        }

        $this->addRelation(
            $targetTable,
            $targetColumnName,
            $selfTable,
            ['notnull' => false],
            ['onDelete' => 'SET NULL']
        );

        $options[ExtendOptionsManager::TARGET_OPTION] = [
            'table_name' => $targetTableName,
            'columns'    => [
                'title'    => $targetTitleColumnNames,
                'detailed' => $targetDetailedColumnNames,
                'grid'     => $targetGridColumnNames,
            ],
        ];
        $options[ExtendOptionsManager::TYPE_OPTION]   = $fieldType;
        $this->extendOptionsManager->setColumnOptions(
            $selfTableName,
            $associationName,
            $options
        );
    }

    /**
     * Adds the inverse side of a one-to-many relation
     *
     * @param Schema       $schema
     * @param Table|string $table                 A Table object or table name of owning side entity
     * @param string       $associationName       The name of a relation field
     * @param Table|string $targetTable           A Table object or table name of inverse side entity
     * @param string       $targetAssociationName The name of a relation field on the inverse side
     * @param string       $titleColumnName       A column name is used to show owning side entity
     * @param array        $options               Entity config options. [scope => [name => value, ...], ...]
     *
     * @deprecated since 2.1, cause oneToMany relation has to be bidirectional always
     */
    public function addOneToManyInverseRelation(
        Schema $schema,
        $table,
        $associationName,
        $targetTable,
        $targetAssociationName,
        $titleColumnName,
        array $options = []
    ) {
        $this->ensureTargetNotHidden($table, $associationName);
        $this->ensureExtendFieldSet($options);

        $selfTableName = $this->getTableName($table);
        $selfTable     = $this->getTable($selfTableName, $schema);
        $selfClassName = $this->getEntityClassByTableName($selfTableName);

        $targetTableName = $this->getTableName($targetTable);
        $targetTable     = $this->getTable($targetTable, $schema);
        $targetClassName = $this->getEntityClassByTableName($targetTableName);

        $existingTargetColumnName = $this->nameGenerator->generateOneToManyRelationColumnName(
            $selfClassName,
            $associationName
        );

        $this->checkColumnsExist($selfTable, [$titleColumnName]);
        $this->checkColumnsExist($targetTable, [$existingTargetColumnName]);

        $selfRelationKey = ExtendHelper::buildRelationKey(
            $selfClassName,
            $associationName,
            RelationType::ONE_TO_MANY,
            $targetClassName
        );
        $targetRelationKey = ExtendHelper::toggleRelationKey($selfRelationKey);

        $targetFieldId = new FieldConfigId(
            'extend',
            $targetClassName,
            $targetAssociationName,
            RelationType::MANY_TO_ONE
        );

        $selfTableOptions['extend']['relation.' . $selfRelationKey . '.target_field_id'] = $targetFieldId;
        $this->extendOptionsManager->setTableOptions(
            $selfTableName,
            $selfTableOptions
        );

        $targetTableOptions['extend']['relation.' . $targetRelationKey . '.field_id'] = $targetFieldId;
        $this->extendOptionsManager->setTableOptions(
            $targetTableName,
            $targetTableOptions
        );

        $options[ExtendOptionsManager::TARGET_OPTION] = [
            'table_name'   => $selfTableName,
            'relation_key' => $targetRelationKey,
            'column'       => $titleColumnName,
        ];
        $options[ExtendOptionsManager::TYPE_OPTION]   = RelationType::MANY_TO_ONE;
        $options['extend']['column_name']             = $existingTargetColumnName;
        $this->extendOptionsManager->setColumnOptions(
            $targetTableName,
            $targetAssociationName,
            $options
        );
    }

    /**
     * Adds many-to-many relation
     *
     * @param Schema       $schema
     * @param Table|string $table                     A Table object or table name of owning side entity
     * @param string       $associationName           The name of a relation field
     * @param Table|string $targetTable               A Table object or table name of inverse side entity
     * @param string[]     $targetTitleColumnNames    Column names are used to show a title of related entity
     * @param string[]     $targetDetailedColumnNames Column names are used to show detailed info about related entity
     * @param string[]     $targetGridColumnNames     Column names are used to show related entity in a grid
     * @param array        $options                   Entity config options. [scope => [name => value, ...], ...]
     * @param string       $fieldType                 The field type. By default the field type is manyToMany,
     *                                                but you can specify another type if it is based on manyToMany.
     *                                                In this case this type should be registered
     *                                                in entity_extend.yml under underlying_types section
     */
    public function addManyToManyRelation(
        Schema $schema,
        $table,
        $associationName,
        $targetTable,
        array $targetTitleColumnNames,
        array $targetDetailedColumnNames,
        array $targetGridColumnNames,
        array $options = [],
        $fieldType = RelationType::MANY_TO_MANY
    ) {
        $this->validateOptions($options, $fieldType);
        $this->ensureExtendFieldSet($options);

        $selfTableName = $this->getTableName($table);
        $selfTable     = $this->getTable($table, $schema);

        $targetTableName = $this->getTableName($targetTable);
        $targetTable     = $this->getTable($targetTable, $schema);

        $this->checkColumnsExist($targetTable, $targetTitleColumnNames);
        $this->checkColumnsExist($targetTable, $targetDetailedColumnNames);
        $this->checkColumnsExist($targetTable, $targetGridColumnNames);

        if (!isset($options['extend']['without_default']) || !$options['extend']['without_default']) {
            $this->addDefaultRelation($selfTable, $associationName, $targetTable);
        }
        $selfIdColumn = $this->getPrimaryKeyColumnName($selfTable);
        $targetIdColumn = $this->getPrimaryKeyColumnName($targetTable);
        $selfClassName             = $this->getEntityClassByTableName($selfTableName);
        $targetClassName           = $this->getEntityClassByTableName($targetTableName);
        $joinTableName             = $this->nameGenerator->generateManyToManyJoinTableName(
            $selfClassName,
            $associationName,
            $targetClassName
        );
        $joinTable                 = $schema->createTable($joinTableName);
        $selfJoinTableColumnNamePrefix = null;
        $targetJoinTableColumnNamePrefix = null;
        if ($selfClassName === $targetClassName) {
            // fix the collision of names if owning side entity equals to inverse side entity
            $selfJoinTableColumnNamePrefix = 'src_';
            $targetJoinTableColumnNamePrefix = 'dest_';
        }
        $selfJoinTableColumnName   = $this->nameGenerator->generateManyToManyJoinTableColumnName(
            $selfClassName,
            '_' . $selfIdColumn,
            $selfJoinTableColumnNamePrefix
        );
        $targetJoinTableColumnName = $this->nameGenerator->generateManyToManyJoinTableColumnName(
            $targetClassName,
            '_' . $targetIdColumn,
            $targetJoinTableColumnNamePrefix
        );
        $this->addRelation(
            $joinTable,
            $selfJoinTableColumnName,
            $selfTable,
            [],
            ['onDelete' => 'CASCADE']
        );
        $this->addRelation(
            $joinTable,
            $targetJoinTableColumnName,
            $targetTable,
            [],
            ['onDelete' => 'CASCADE']
        );
        $joinTable->setPrimaryKey([$selfJoinTableColumnName, $targetJoinTableColumnName]);

        $options[ExtendOptionsManager::TARGET_OPTION] = [
            'table_name' => $targetTableName,
            'columns'    => [
                'title'    => $targetTitleColumnNames,
                'detailed' => $targetDetailedColumnNames,
                'grid'     => $targetGridColumnNames,
            ],
        ];
        $options[ExtendOptionsManager::TYPE_OPTION]   = $fieldType;
        $this->extendOptionsManager->setColumnOptions(
            $selfTableName,
            $associationName,
            $options
        );
    }

    /**
     * Adds the inverse side of a many-to-many relation
     *
     * @param Schema       $schema
     * @param Table|string $table                 A Table object or table name of owning side entity
     * @param string       $associationName       The name of a relation field
     * @param Table|string $targetTable           A Table object or table name of inverse side entity
     * @param string       $targetAssociationName The name of a relation field on the inverse side
     * @param string[]     $titleColumnNames      Column names are used to show a title of owning side entity
     * @param string[]     $detailedColumnNames   Column names are used to show detailed info about owning side entity
     * @param string[]     $gridColumnNames       Column names are used to show owning side entity in a grid
     * @param array        $options               Entity config options. [scope => [name => value, ...], ...]
     */
    public function addManyToManyInverseRelation(
        Schema $schema,
        $table,
        $associationName,
        $targetTable,
        $targetAssociationName,
        array $titleColumnNames,
        array $detailedColumnNames,
        array $gridColumnNames,
        array $options = []
    ) {
        $this->ensureTargetNotHidden($table, $associationName);
        $this->ensureExtendFieldSet($options);

        $selfTableName = $this->getTableName($table);
        $selfTable     = $this->getTable($selfTableName, $schema);
        $selfClassName = $this->getEntityClassByTableName($selfTableName);

        $targetTableName = $this->getTableName($targetTable);
        $targetClassName = $this->getEntityClassByTableName($targetTableName);

        $this->checkColumnsExist($selfTable, $titleColumnNames);
        $this->checkColumnsExist($selfTable, $detailedColumnNames);
        $this->checkColumnsExist($selfTable, $gridColumnNames);

        $selfRelationKey = ExtendHelper::buildRelationKey(
            $selfClassName,
            $associationName,
            RelationType::MANY_TO_MANY,
            $targetClassName
        );
        $targetRelationKey = ExtendHelper::toggleRelationKey($selfRelationKey);

        $targetFieldId = new FieldConfigId(
            'extend',
            $targetClassName,
            $targetAssociationName,
            RelationType::MANY_TO_MANY
        );

        $selfTableOptions['extend']['relation.' . $selfRelationKey . '.target_field_id'] = $targetFieldId;
        $this->extendOptionsManager->setTableOptions(
            $selfTableName,
            $selfTableOptions
        );

        $this->extendOptionsManager->mergeColumnOptions(
            $selfTableName,
            $associationName,
            ['extend' => ['bidirectional' => true]]
        );

        $targetTableOptions['extend']['relation.' . $targetRelationKey . '.field_id'] = $targetFieldId;
        $this->extendOptionsManager->setTableOptions(
            $targetTableName,
            $targetTableOptions
        );

        $options[ExtendOptionsManager::TARGET_OPTION] = [
            'table_name'   => $selfTableName,
            'relation_key' => $targetRelationKey,
            'columns'      => [
                'title'    => $titleColumnNames,
                'detailed' => $detailedColumnNames,
                'grid'     => $gridColumnNames,
            ],
        ];
        $options[ExtendOptionsManager::TYPE_OPTION]   = RelationType::MANY_TO_MANY;
        $this->extendOptionsManager->setColumnOptions(
            $targetTableName,
            $targetAssociationName,
            $options
        );
    }

    /**
     * Adds many-to-one relation
     *
     * @param Schema       $schema
     * @param Table|string $table                 A Table object or table name of owning side entity
     * @param string       $associationName       The name of a relation field
     * @param Table|string $targetTable           A Table object or table name of inverse side entity
     * @param string       $targetTitleColumnName A column name is used to show related entity
     * @param array        $options               Entity config options. [scope => [name => value, ...], ...]
     * @param string       $fieldType             The field type. By default the field type is manyToOne,
     *                                            but you can specify another type if it is based on manyToOne.
     *                                            In this case this type should be registered
     *                                            in entity_extend.yml under underlying_types section
     */
    public function addManyToOneRelation(
        Schema $schema,
        $table,
        $associationName,
        $targetTable,
        $targetTitleColumnName,
        array $options = [],
        $fieldType = RelationType::MANY_TO_ONE
    ) {
        $this->validateOptions($options, $fieldType);
        $this->ensureExtendFieldSet($options);

        $selfTableName        = $this->getTableName($table);
        $selfTable            = $this->getTable($table, $schema);
        $targetTableName      = $this->getTableName($targetTable);
        $targetTable          = $this->getTable($targetTable, $schema);
        $primaryKeyColumnName = $this->getPrimaryKeyColumnName($targetTable);
        $selfColumnName       = $this->nameGenerator->generateRelationColumnName(
            $associationName,
            '_' . $primaryKeyColumnName
        );

        $this->checkColumnsExist($targetTable, [$targetTitleColumnName]);

        $relation = $options['extend'];

        if (array_key_exists('nullable', $relation)) {
            $notnull = !$relation['nullable'];
        } else {
            $notnull = false;
        }

        $this->addRelation(
            $selfTable,
            $selfColumnName,
            $targetTable,
            ['notnull' => $notnull],
            ['onDelete' => $this->getOnDeleteAction($relation)]
        );

        $options[ExtendOptionsManager::TARGET_OPTION] = [
            'table_name' => $targetTableName,
            'column'     => $targetTitleColumnName,
        ];
        $options[ExtendOptionsManager::TYPE_OPTION]   = $fieldType;
        $this->extendOptionsManager->setColumnOptions(
            $selfTableName,
            $associationName,
            $options
        );
    }

    /**
     * Adds the inverse side of a many-to-one relation
     *
     * @param Schema       $schema
     * @param Table|string $table                 A Table object or table name of owning side entity
     * @param string       $associationName       The name of a relation field. This field can't be hidden
     * @param Table|string $targetTable           A Table object or table name of inverse side entity
     * @param string       $targetAssociationName The name of a relation field on the inverse side
     * @param string[]     $titleColumnNames      Column names are used to show a title of owning side entity
     * @param string[]     $detailedColumnNames   Column names are used to show detailed info about owning side entity
     * @param string[]     $gridColumnNames       Column names are used to show owning side entity in a grid
     * @param array        $options               Entity config options. [scope => [name => value, ...], ...]
     */
    public function addManyToOneInverseRelation(
        Schema $schema,
        $table,
        $associationName,
        $targetTable,
        $targetAssociationName,
        array $titleColumnNames,
        array $detailedColumnNames,
        array $gridColumnNames,
        array $options = []
    ) {
        $this->ensureTargetNotHidden($table, $associationName);
        $this->ensureExtendFieldSet($options);

        $selfTableName = $this->getTableName($table);
        $selfTable     = $this->getTable($selfTableName, $schema);
        $selfClassName = $this->getEntityClassByTableName($selfTableName);

        $targetTableName = $this->getTableName($targetTable);
        $targetClassName = $this->getEntityClassByTableName($targetTableName);

        $this->checkColumnsExist($selfTable, $titleColumnNames);
        $this->checkColumnsExist($selfTable, $detailedColumnNames);
        $this->checkColumnsExist($selfTable, $gridColumnNames);

        $selfRelationKey = ExtendHelper::buildRelationKey(
            $selfClassName,
            $associationName,
            RelationType::MANY_TO_ONE,
            $targetClassName
        );
        $targetRelationKey = ExtendHelper::toggleRelationKey($selfRelationKey);

        $targetFieldId = new FieldConfigId(
            'extend',
            $targetClassName,
            $targetAssociationName,
            RelationType::ONE_TO_MANY
        );

        $selfTableOptions['extend']['relation.' . $selfRelationKey . '.target_field_id'] = $targetFieldId;
        $selfTableOptions['extend']['relation.' . $selfRelationKey . '.on_delete']
            = $this->getOnDeleteAction($options['extend']);
        $this->extendOptionsManager->setTableOptions(
            $selfTableName,
            $selfTableOptions
        );

        $this->extendOptionsManager->mergeColumnOptions(
            $selfTableName,
            $associationName,
            ['extend' => ['bidirectional' => true]]
        );

        $targetTableOptions['extend']['relation.' . $targetRelationKey . '.field_id'] = $targetFieldId;
        if (isset($options['extend']['orphanRemoval'])) {
            $targetTableOptions['extend']['relation.' . $targetRelationKey . '.orphanRemoval']
                = $options['extend']['orphanRemoval'];
        }

        $this->extendOptionsManager->setTableOptions(
            $targetTableName,
            $targetTableOptions
        );

        $options[ExtendOptionsManager::TARGET_OPTION] = [
            'table_name'   => $selfTableName,
            'relation_key' => $targetRelationKey,
            'columns'      => [
                'title'    => $titleColumnNames,
                'detailed' => $detailedColumnNames,
                'grid'     => $gridColumnNames,
            ],
        ];
        $options[ExtendOptionsManager::TYPE_OPTION]   = RelationType::ONE_TO_MANY;
        $this->extendOptionsManager->setColumnOptions(
            $targetTableName,
            $targetAssociationName,
            $options
        );
    }

    /**
     * Gets an entity full class name by a table name
     *
     * @param string $tableName
     *
     * @return string|null
     */
    public function getEntityClassByTableName($tableName)
    {
        $classes = $this->entityMetadataHelper->getEntityClassesByTableName($tableName);

        if (count($classes) > 1) {
            throw new \RuntimeException(sprintf(
                'Table "%s" has more than 1 class. This is not supported by ExtendExtension',
                $tableName
            ));
        }

        return reset($classes) ?: null;
    }

    /**
     * Gets an entity full class name by a table name
     *
     * @param string $tableName
     *
     * @return array
     */
    public function getEntityClassesByTableName($tableName)
    {
        return $this->entityMetadataHelper->getEntityClassesByTableName($tableName);
    }

    /**
     * Gets a table name by entity full class name
     *
     * @param string $className
     *
     * @return string|null
     */
    public function getTableNameByEntityClass($className)
    {
        return $this->entityMetadataHelper->getTableNameByEntityClass($className);
    }

    /**
     * Gets a field name by a table name and a column name
     *
     * @param string $tableName
     * @param string $columnName
     *
     * @return string|null
     */
    public function getFieldNameByColumnName($tableName, $columnName)
    {
        return $this->entityMetadataHelper->getFieldNameByColumnName($tableName, $columnName);
    }

    /**
     * Gets a field name by a table name and a column name
     *
     * @param string $className
     * @param string $fieldName
     *
     * @return string|null
     */
    public function getColumnNameByFieldName($className, $fieldName)
    {
        return $this->entityMetadataHelper->getColumnNameByFieldName($className, $fieldName);
    }

    /**
     * @param Table|string $table A Table object or table name
     *
     * @return string
     */
    protected function getTableName($table)
    {
        return $table instanceof Table ? $table->getName() : $table;
    }

    /**
     * @param Table|string $table A Table object or table name
     * @param Schema       $schema
     *
     * @return Table
     */
    protected function getTable($table, Schema $schema)
    {
        return $table instanceof Table ? $table : $schema->getTable($table);
    }

    /**
     * @param Table    $table
     * @param string[] $columnNames
     *
     * @throws \InvalidArgumentException if $columnNames array is empty
     * @throws SchemaException if any column is not exist
     */
    protected function checkColumnsExist($table, array $columnNames)
    {
        if (empty($columnNames)) {
            throw new \InvalidArgumentException('At least one column must be specified.');
        }
        foreach ($columnNames as $columnName) {
            $table->getColumn($columnName);
        }
    }

    /**
     * @param Table $table
     *
     * @return string
     *
     * @throws SchemaException if valid primary key does not exist
     */
    protected function getPrimaryKeyColumnName(Table $table)
    {
        if (!$table->hasPrimaryKey()) {
            throw new SchemaException(
                sprintf('The table "%s" must have a primary key.', $table->getName())
            );
        }
        $primaryKeyColumns = $table->getPrimaryKey()->getColumns();
        if (count($primaryKeyColumns) !== 1) {
            throw new SchemaException(
                sprintf('A primary key of "%s" table must include only one column.', $table->getName())
            );
        }

        return array_pop($primaryKeyColumns);
    }

    /**
     * @param Table  $table
     * @param string $columnName
     * @param Column $targetColumn
     * @param array  $options
     *
     * @throws SchemaException
     */
    protected function addRelationColumn(Table $table, $columnName, Column $targetColumn, array $options = [])
    {
        $columnTypeName = $targetColumn->getType()->getName();
        if (!in_array($columnTypeName, [Types::INTEGER, Types::STRING, Types::SMALLINT, Types::BIGINT], true)) {
            throw new SchemaException(
                sprintf(
                    'The type of relation column "%s::%s" must be an integer or string. "%s" type is not supported.',
                    $table->getName(),
                    $columnName,
                    $columnTypeName
                )
            );
        }

        if ($columnTypeName === Types::STRING && $targetColumn->getLength() !== null) {
            $options['length'] = $targetColumn->getLength();
        }

        $table->addColumn($columnName, $columnTypeName, $options);
    }

    /**
     * @param Table  $table
     * @param string $columnName
     * @param Table  $targetTable
     * @param array  $columnOptions
     * @param array  $foreignKeyOptions
     */
    protected function addRelation(
        Table $table,
        $columnName,
        Table $targetTable,
        array $columnOptions = [],
        array $foreignKeyOptions = []
    ) {
        $targetPrimaryKeyColumnName = $this->getPrimaryKeyColumnName($targetTable);
        $targetPrimaryKeyColumn     = $targetTable->getColumn($targetPrimaryKeyColumnName);
        $this->addRelationColumn($table, $columnName, $targetPrimaryKeyColumn, $columnOptions);
        $table->addIndex([$columnName]);
        $table->addForeignKeyConstraint(
            $targetTable,
            [$columnName],
            [$targetPrimaryKeyColumnName],
            $foreignKeyOptions
        );
    }

    /**
     * @param Table  $table
     * @param string $associationName
     * @param Table  $targetTable
     */
    protected function addDefaultRelation(Table $table, $associationName, Table $targetTable)
    {
        $targetPrimaryKeyColumnName = $this->getPrimaryKeyColumnName($targetTable);
        $defaultRelationColumnName  = $this->nameGenerator->generateRelationDefaultColumnName(
            $associationName,
            '_' . $targetPrimaryKeyColumnName
        );
        $targetPrimaryKeyColumn     = $targetTable->getColumn($targetPrimaryKeyColumnName);
        $this->addRelationColumn($table, $defaultRelationColumnName, $targetPrimaryKeyColumn, ['notnull' => false]);
        $table->addIndex([$defaultRelationColumnName]);
        $table->addForeignKeyConstraint(
            $targetTable,
            [$defaultRelationColumnName],
            [$targetPrimaryKeyColumnName],
            ['onDelete' => 'SET NULL']
        );
    }

    /**
     * Makes sure that required for any extend field attributes are set
     *
     * @param array $options
     */
    protected function ensureExtendFieldSet(array &$options)
    {
        if (!isset($options['extend'])) {
            $options['extend'] = [];
        }
        if (!isset($options['extend']['is_extend'])) {
            $options['extend']['is_extend'] = true;
        }
        if (!isset($options['extend']['owner'])) {
            $options['extend']['owner'] = ExtendScope::OWNER_SYSTEM;
        }
        if (!isset($options['extend']['bidirectional'])) {
            $options['extend']['bidirectional'] = false;
        }
        if (!isset($options[ExtendOptionsManager::MODE_OPTION])) {
            $options[ExtendOptionsManager::MODE_OPTION] = ConfigModel::MODE_READONLY;
        }
    }

    /**
     * @param array  $options
     * @param string $fieldType
     * @throws \UnexpectedValueException
     */
    private function validateOptions(array $options, $fieldType)
    {
        foreach ($options as $scope => $scopeOptions) {
            /** @var PropertyConfigContainer $scopeConfig */
            $scopeConfig = $this->propertyConfigBag->getPropertyConfig($scope);

            if (!is_array($scopeOptions) || count($scopeConfig->getConfig()) === 0) {
                continue;
            }

            foreach ($scopeOptions as $optionName => $optionValue) {
                if (!isset($scopeConfig->getConfig()['field']['items'][$optionName]['options']['allowed_type'])) {
                    continue;
                }

                $allowedTypes = $scopeConfig->getConfig()['field']['items'][$optionName]['options']['allowed_type'];

                if (!in_array($fieldType, $allowedTypes)) {
                    throw new \UnexpectedValueException(sprintf(
                        'Option `%s|%s` is not allowed for field type `%s`. Allowed types [%s]',
                        $scope,
                        $optionName,
                        $fieldType,
                        implode(', ', $allowedTypes)
                    ));
                }
            }
        }
    }

    /**
     * @param string|ExtendTable $table
     * @param string $associationName
     */
    private function ensureTargetNotHidden($table, $associationName)
    {
        $options = $this->extendOptionsManager->getExtendOptions();
        $tableName = $this->getTableName($table);
        $keyName = $tableName.'!'.$associationName;
        if (!empty($options[$keyName][ExtendOptionsManager::MODE_OPTION])
            && $options[$keyName][ExtendOptionsManager::MODE_OPTION] === ConfigModel::MODE_HIDDEN) {
            throw new \InvalidArgumentException('Target field can\'t be hidden.');
        }
    }

    /**
     * @param array $relation
     * @return mixed|string
     */
    private function getOnDeleteAction(array $relation)
    {
        if (array_key_exists('on_delete', $relation)) {
            return $relation['on_delete'];
        }

        return 'SET NULL';
    }
}
