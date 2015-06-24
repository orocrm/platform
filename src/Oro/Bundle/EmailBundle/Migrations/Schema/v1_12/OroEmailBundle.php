<?php

namespace Oro\Bundle\EmailBundle\Migrations\Schema\v1_12;

use Doctrine\DBAL\Schema\Schema;

use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Oro\Bundle\EntityConfigBundle\Migration\UpdateEntityConfigEntityValueQuery;

/**
 * Class OroEmailBundle
 * @package Oro\Bundle\EmailBundle\Migrations\Schema\v1_12
 */
class OroEmailBundle implements Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        self::addColumnMessageMultiId($schema);
    }

    /**
     * It adds column message_multi_id to table oro_email
     * @param Schema $schema - Schema
     * @throws \Doctrine\DBAL\Schema\SchemaException
     * @return void
     */
    public static function addColumnMessageMultiId(Schema $schema)
    {
        $table = $schema->getTable('oro_email');
        $table->addColumn(
            'message_multi_id',
            'string',
            [
                'notnull' => false,
                'length' => 255
            ]
        );
    }
}
