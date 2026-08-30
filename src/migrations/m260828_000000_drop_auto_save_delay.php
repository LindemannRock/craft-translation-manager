<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\migrations;

use craft\db\Migration;

/**
 * Remove the unused auto-save delay setting from existing installs.
 *
 * Rolling back restores only the column structure and its default. Values
 * deleted by the forward migration cannot be recovered and become `2`.
 *
 * @since 5.35.0
 */
class m260828_000000_drop_auto_save_delay extends Migration
{
    private const TABLE = '{{%translationmanager_settings}}';
    private const COLUMN = 'autoSaveDelay';

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $schema = $this->db->getTableSchema(self::TABLE, true);

        if ($schema === null || !isset($schema->columns[self::COLUMN])) {
            return true;
        }

        $this->dropColumn(self::TABLE, self::COLUMN);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $schema = $this->db->getTableSchema(self::TABLE, true);

        if ($schema === null || isset($schema->columns[self::COLUMN])) {
            return true;
        }

        $this->addColumn(
            self::TABLE,
            self::COLUMN,
            $this->integer()->notNull()->defaultValue(2),
        );

        return true;
    }
}
