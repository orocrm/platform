<?php

namespace Oro\Bundle\ApiBundle\Tests\Functional\Environment;

use Doctrine\DBAL\Schema\Schema;

use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class TestEntitiesMigration implements Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $this->createTestDepartmentTable($schema);
        $this->createTestPersonTable($schema);
        $this->createTestDefaultAndNullTable($schema);
        $this->createTestNestedObjectsTable($schema);
    }

    /**
     * Create test_api_department table
     *
     * @param Schema $schema
     */
    protected function createTestDepartmentTable(Schema $schema)
    {
        if ($schema->hasTable('test_api_department')) {
            return;
        }

        $table = $schema->createTable('test_api_department');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->setPrimaryKey(['id']);
    }

    /**
     * Create test_api_person table
     *
     * @param Schema $schema
     */
    protected function createTestPersonTable(Schema $schema)
    {
        if ($schema->hasTable('test_api_person')) {
            return;
        }

        $table = $schema->createTable('test_api_person');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('department_id', 'integer', ['notnull' => false]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('type', 'string', ['length' => 255]);
        $table->addColumn('position', 'string', ['notnull' => false, 'length' => 255]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['department_id'], 'IDX_C91820CFAE80F5DF', []);
        $table->addForeignKeyConstraint(
            $schema->getTable('test_api_department'),
            ['department_id'],
            ['id'],
            ['onDelete' => null, 'onUpdate' => null]
        );
    }

    /**
     * Create test_api_default_and_null table
     *
     * @param Schema $schema
     */
    protected function createTestDefaultAndNullTable(Schema $schema)
    {
        if ($schema->hasTable('test_api_default_and_null')) {
            return;
        }

        $table = $schema->createTable('test_api_default_and_null');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('with_default_value_string', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('without_default_value_string', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('with_default_value_boolean', 'boolean', ['notnull' => false]);
        $table->addColumn('without_default_value_boolean', 'boolean', ['notnull' => false]);
        $table->addColumn('with_default_value_integer', 'integer', ['notnull' => false]);
        $table->addColumn('without_default_value_integer', 'integer', ['notnull' => false]);
        $table->addColumn('with_df_not_blank', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('with_df_not_null', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('with_not_blank', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('with_not_null', 'string', ['notnull' => false, 'length' => 255]);
        $table->setPrimaryKey(['id']);
    }

    /**
     * Create test_api_nested_objects table
     *
     * @param Schema $schema
     */
    public function createTestNestedObjectsTable(Schema $schema)
    {
        if ($schema->hasTable('test_api_nested_objects')) {
            return;
        }

        $table = $schema->createTable('test_api_nested_objects');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('first_name', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('last_name', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('related_class', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('related_id', 'integer', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
    }
}
