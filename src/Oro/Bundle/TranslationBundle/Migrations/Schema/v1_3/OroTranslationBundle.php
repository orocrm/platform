<?php

namespace Oro\Bundle\TranslationBundle\Migrations\Schema\v1_3;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\OrderedMigrationInterface;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroTranslationBundle implements Migration, OrderedMigrationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getOrder()
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        /** Tables generation **/
        $this->createOroTranslationKeyTable($schema);

        $this->updateOroTranslationTable($schema, $queries);
    }

    /**
     * Create oro_translation_key table
     *
     * @param Schema $schema
     */
    protected function createOroTranslationKeyTable(Schema $schema)
    {
        $table = $schema->createTable('oro_translation_key');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('key', 'string', ['length' => 255]);
        $table->addColumn('domain', 'string', ['default' => 'messages', 'length' => 255]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['key', 'domain'], 'key_domain_uniq');
        /**
         * Required to support Case Sensitive keys in MySQL
         */
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collate', 'utf8mb4_bin');
    }

    /**
     * @param Schema $schema
     * @param QueryBag $queries
     */
    protected function updateOroTranslationTable(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('oro_translation');
        $table->addColumn('translation_key_id', 'integer', ['notnull' => false]);
        $table->addColumn('language_id', 'integer', ['notnull' => false]);

        $table->dropIndex('MESSAGES_IDX');
        $table->dropIndex('MESSAGE_IDX');

        $queries->addQuery(new MigrateTranslationDataQuery());
    }
}
