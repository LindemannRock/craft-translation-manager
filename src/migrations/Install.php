<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Installation migration
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\migrations;

use Craft;
use craft\db\Migration;

/**
 * Installation Migration
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        // Create the translations table with multi-site support
        if (!$this->tableExists('{{%translationmanager_translations}}')) {
            $this->createTable('{{%translationmanager_translations}}', [
                'id' => $this->primaryKey(),
                'source' => $this->text()->notNull(),
                'sourceHash' => $this->string(32)->notNull(),
                'context' => $this->string(255)->notNull(),
                'siteId' => $this->integer()->notNull()->defaultValue(1),
                'translationKey' => $this->text()->notNull()->comment('The key used in code (any language)'),
                'translation' => $this->text()->null()->comment('Site-specific translation'),
                'status' => $this->enum('status', ['pending', 'translated', 'approved', 'unused'])->notNull()->defaultValue('pending'),
                'usageCount' => $this->integer()->notNull()->defaultValue(1),
                'lastUsed' => $this->dateTime()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Create indexes for multi-site support
            $this->createIndex(null, '{{%translationmanager_translations}}', 'sourceHash', false);
            $this->createIndex(null, '{{%translationmanager_translations}}', 'context', false);
            $this->createIndex(null, '{{%translationmanager_translations}}', 'status', false);
            $this->createIndex(null, '{{%translationmanager_translations}}', 'siteId', false);
            $this->createIndex(null, '{{%translationmanager_translations}}', ['siteId', 'status'], false);
            $this->createIndex(null, '{{%translationmanager_translations}}', ['siteId', 'context'], false);
            // Unique constraint: same key can exist once per site (context used for tracking only)
            $this->createIndex(null, '{{%translationmanager_translations}}', ['sourceHash', 'siteId'], true);
        }

        // Check if old translations table exists and migrate data
        if ($this->tableExists('{{%translations}}')) {
            Craft::info('Migrating existing translations from old table', __METHOD__);
            
            // Copy data from old table to new table
            $oldTranslations = (new \craft\db\Query())
                ->select('*')
                ->from('{{%translations}}')
                ->all();
            
            foreach ($oldTranslations as $translation) {
                $this->insert('{{%translationmanager_translations}}', [
                    'source' => $translation['source'] ?? $translation['englishText'],
                    'sourceHash' => $translation['sourceHash'] ?? md5($translation['englishText']),
                    'context' => $translation['context'] ?? 'site',
                    'englishText' => $translation['englishText'],
                    'arabicText' => $translation['arabicText'],
                    'status' => $translation['status'] ?? 'pending',
                    'usageCount' => $translation['usageCount'] ?? 1,
                    'lastUsed' => $translation['lastUsed'] ?? null,
                    'dateCreated' => $translation['dateCreated'] ?? new \DateTime(),
                    'dateUpdated' => $translation['dateUpdated'] ?? new \DateTime(),
                    'uid' => $translation['uid'] ?? \craft\helpers\StringHelper::UUID(),
                ]);
            }
            
            Craft::info('Migration completed: ' . count($oldTranslations) . ' translations migrated', __METHOD__);
        }

        return true;
    }

    public function safeDown(): bool
    {
        // Drop the translations table
        if ($this->tableExists('{{%translationmanager_translations}}')) {
            $this->dropTable('{{%translationmanager_translations}}');
        }

        return true;
    }

    private function tableExists(string $tableName): bool
    {
        $schema = $this->db->getSchema();
        $tableNames = $schema->getTableNames();
        $tableName = $schema->getRawTableName($tableName);
        
        return in_array($tableName, $tableNames, true);
    }
}