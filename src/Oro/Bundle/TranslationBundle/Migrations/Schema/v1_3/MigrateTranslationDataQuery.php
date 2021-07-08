<?php

namespace Oro\Bundle\TranslationBundle\Migrations\Schema\v1_3;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Types\Types;
use Oro\Bundle\MigrationBundle\Migration\ArrayLogger;
use Oro\Bundle\MigrationBundle\Migration\ParametrizedMigrationQuery;
use Psr\Log\LoggerInterface;

class MigrateTranslationDataQuery extends ParametrizedMigrationQuery
{
    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        $logger = new ArrayLogger();
        $this->migrateData($logger, true);

        return $logger->getMessages();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(LoggerInterface $logger)
    {
        $this->migrateData($logger);
    }

    /**
     * @param LoggerInterface $logger
     * @param bool $dryRun
     */
    protected function migrateData(LoggerInterface $logger, $dryRun = false)
    {
        $queries = [];

        foreach ($this->getNewLanguages($logger) as $languageCode) {
            $queries[] = [
                'INSERT INTO oro_language (code, created_at, updated_at, enabled) ' .
                    'VALUES(:code, now(), now(), :enabled);',
                ['code' => $languageCode, 'enabled' => true],
                ['code' => Types::STRING, 'enabled' => Types::BOOLEAN],
            ];
        }

        $keyField = 'key';

        if ($this->connection->getDatabasePlatform() instanceof MySqlPlatform) {
            $keyField = '`key`';
            //In cases when we have Case Insensitive characterset for `key` column
            $queries[] = [
                'ALTER TABLE `oro_translation` MODIFY COLUMN `key`  varchar(255) ' .
                    'CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL AFTER `id`;',
                [],
                [],
            ];
        }

        $queries = array_merge($queries, [
            [
                'UPDATE oro_translation t SET language_id = (SELECT id FROM oro_language l WHERE l.code = t.locale);',
                [],
                [],
            ],
            [
                'INSERT INTO oro_translation_key (' . $keyField . ', domain) ' .
                    'SELECT DISTINCT t.key, t.domain FROM oro_translation t;',
                [],
                [],
            ],
            [
                'UPDATE oro_translation t SET translation_key_id = ' .
                    '(SELECT id FROM oro_translation_key k WHERE k.key = t.key AND k.domain = t.domain);',
                [],
                [],
            ],
        ]);

        foreach ($this->getDuplicatedKeys($logger) as $id) {
            $queries[] = [
                'DELETE FROM oro_translation WHERE id = :id;',
                ['id' => $id],
                ['id' => Types::INTEGER],
            ];
        }

        foreach ($queries as $query) {
            $this->logQuery($logger, $query[0], $query[1], $query[2]);
            if (!$dryRun) {
                $this->connection->executeStatement($query[0], $query[1], $query[2]);
            }
        }
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return array
     */
    private function getNewLanguages(LoggerInterface $logger)
    {
        $query = 'SELECT DISTINCT locale FROM oro_translation WHERE locale NOT IN (SELECT code FROM oro_language);';

        $this->logQuery($logger, $query);

        return array_column($this->connection->fetchAll($query), 'locale');
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return array
     */
    private function getDuplicatedKeys(LoggerInterface $logger)
    {
        $query = [
            'SELECT GROUP_CONCAT(oro_translation.id) as ids, ' .
                'oro_translation.`key`, ' .
                'GROUP_CONCAT(oro_translation.scope) as scopes, ' .
                'COUNT(oro_translation.id) as _count ' .
                'FROM oro_translation ' .
                'GROUP BY oro_translation.`key`, oro_translation.locale, oro_translation.domain ' .
                'HAVING _count > 1;',
        ];

        if ($this->connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
            $query = [
                'SELECT ARRAY_TO_STRING(ARRAY_AGG(oro_translation.id), \',\') as ids, ' .
                    'oro_translation.key, ' .
                    'ARRAY_TO_STRING(ARRAY_AGG(oro_translation.scope), \',\') as scopes ' .
                    'FROM oro_translation ' .
                    'GROUP BY oro_translation.key, oro_translation.locale, oro_translation.domain ' .
                    'HAVING COUNT(*) > 1;',
            ];
        }

        $this->logQuery($logger, $query[0]);

        $aggregatedItems = $this->connection->fetchAll($query[0]);

        $items = [];
        foreach ($aggregatedItems as $item) {
            $ids = explode(',', $item['ids']);
            $scopes = explode(',', $item['scopes']);
            foreach ($ids as $index => $id) {
                $items[] = [
                    'id' => $id,
                    'key' => $item['key'],
                    'scope' => $scopes[$index],
                ];
            }
        }

        // filter duplications by max(scope)
        $filteredItems = array_filter($items, function ($item) use ($items) {
            foreach ($items as $subitem) {
                if ($item['key'] === $subitem['key'] && $item['id'] !== $subitem['id']) {
                    return $item['scope'] >= $subitem['scope'];
                }
            }

            return true;
        });

        // get a unique key values that will be left
        $keys = array_unique(array_column($filteredItems, 'key', 'id'));

        // exclude duplication keys
        $duplicatedItems = array_filter($items, function ($item) use ($keys) {
            return !isset($keys[$item['id']]);
        });

        return array_column($duplicatedItems, 'id');
    }
}
