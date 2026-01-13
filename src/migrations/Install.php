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

use craft\db\Migration;
use craft\helpers\Db;
use craft\helpers\StringHelper;

/**
 * Installation Migration
 *
 * @since 1.0.0
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
                'status' => $this->enum('status', ['pending', 'translated', 'unused', 'approved'])->notNull()->defaultValue('pending'),
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

        // Create the import history table
        if (!$this->tableExists('{{%translationmanager_import_history}}')) {
            $this->createTable('{{%translationmanager_import_history}}', [
                'id' => $this->primaryKey(),
                'userId' => $this->integer()->notNull(),
                'filename' => $this->string()->notNull(),
                'filesize' => $this->integer()->notNull(),
                'imported' => $this->integer()->notNull()->defaultValue(0),
                'updated' => $this->integer()->notNull()->defaultValue(0),
                'skipped' => $this->integer()->notNull()->defaultValue(0),
                'errors' => $this->text()->null(),
                'backupPath' => $this->string()->null(),
                'details' => $this->text()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Create indexes
            $this->createIndex(null, '{{%translationmanager_import_history}}', ['userId']);
            $this->createIndex(null, '{{%translationmanager_import_history}}', ['dateCreated']);

            // Add foreign key
            $this->addForeignKey(null, '{{%translationmanager_import_history}}', ['userId'], '{{%users}}', ['id'], 'CASCADE');
        }

        // Create the settings table
        if (!$this->tableExists('{{%translationmanager_settings}}')) {
            $this->createTable('{{%translationmanager_settings}}', [
                'id' => $this->primaryKey(),
                'pluginName' => $this->string(100)->notNull()->defaultValue('Translation Manager'),
                'translationCategory' => $this->string()->notNull()->defaultValue('messages'),
                'sourceLanguage' => $this->string(10)->notNull()->defaultValue('en'),
                'enableFormieIntegration' => $this->boolean()->notNull()->defaultValue(true),
                'enableSiteTranslations' => $this->boolean()->notNull()->defaultValue(true),
                'autoExport' => $this->boolean()->notNull()->defaultValue(true),
                'exportPath' => $this->string()->notNull()->defaultValue('@root/translations'),
                'itemsPerPage' => $this->integer()->notNull()->defaultValue(100),
                'autoSaveEnabled' => $this->boolean()->notNull()->defaultValue(false),
                'autoSaveDelay' => $this->integer()->notNull()->defaultValue(2),
                'showContext' => $this->boolean()->notNull()->defaultValue(true),
                'enableSuggestions' => $this->boolean()->notNull()->defaultValue(false),
                'backupEnabled' => $this->boolean()->defaultValue(true),
                'backupRetentionDays' => $this->integer()->defaultValue(30),
                'backupOnImport' => $this->boolean()->defaultValue(true),
                'backupSchedule' => $this->string(20)->defaultValue('manual'),
                'backupPath' => $this->string()->defaultValue('@storage/translation-manager/backups'),
                'backupVolumeUid' => $this->string()->null(),
                'skipPatterns' => $this->text()->null(),
                'excludeFormHandlePatterns' => $this->text()->null(),
                'logLevel' => $this->string(20)->notNull()->defaultValue('error'),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Insert default settings row
            $this->insert('{{%translationmanager_settings}}', [
                'dateCreated' => Db::prepareDateForDb(new \DateTime()),
                'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
                'uid' => StringHelper::UUID(),
            ]);
        }

        return true;
    }

    public function safeDown(): bool
    {
        // Drop tables in reverse order due to foreign key constraints
        if ($this->tableExists('{{%translationmanager_import_history}}')) {
            $this->dropTable('{{%translationmanager_import_history}}');
        }
        
        if ($this->tableExists('{{%translationmanager_settings}}')) {
            $this->dropTable('{{%translationmanager_settings}}');
        }
        
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
