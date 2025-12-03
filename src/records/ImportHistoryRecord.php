<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Active Record for import history tracking
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\records;

use craft\db\ActiveRecord;
use craft\records\User;

/**
 * Import History Record
 *
 * @property int $id
 * @property int $userId
 * @property string $filename
 * @property int $filesize
 * @property int $imported
 * @property int $updated
 * @property int $skipped
 * @property string|null $errors
 * @property string|null $backupPath
 * @property string|null $details
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 *
 * @property-read User $user
 * @since 1.0.0
 */
class ImportHistoryRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%translationmanager_import_history}}';
    }
    
    /**
     * Returns the user relation
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }
}
