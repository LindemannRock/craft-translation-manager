<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Active Record for translation entries
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\records;

use craft\db\ActiveRecord;

/**
 * Translation Record
 *
 * @property int $id
 * @property string $source
 * @property string $sourceHash
 * @property string $context
 * @property string $category Translation category used in |t() - e.g., messages, formie, emails
 * @property string $translationKey
 * @property string|null $translation
 * @property int $siteId Legacy - kept for backwards compatibility
 * @property string|null $language Language code (e.g., 'en-US', 'ar', 'fr')
 * @property string $status
 * @property int $usageCount
 * @property string|null $lastUsed
 * @property string|null $dateCreated
 * @property string|null $dateUpdated
 * @property string $uid
 * @property string|null $englishText @deprecated Legacy - use translationKey
 * @property string|null $arabicText @deprecated Legacy - use translation
 * @since 1.0.0
 */
class TranslationRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%translationmanager_translations}}';
    }

    public function rules(): array
    {
        return [
            [['source', 'sourceHash', 'context', 'category', 'translationKey', 'status', 'siteId'], 'required'],
            [['source', 'translationKey', 'translation'], 'string'],
            [['siteId'], 'integer', 'min' => 1],
            [['sourceHash'], 'string', 'max' => 32],
            [['context'], 'string', 'max' => 255],
            [['category'], 'string', 'max' => 50],
            [['language'], 'string', 'max' => 12],
            [['status'], 'in', 'range' => ['pending', 'translated', 'approved', 'unused']],
            [['usageCount'], 'integer', 'min' => 0],
            [['usageCount'], 'default', 'value' => 1],
            [['status'], 'default', 'value' => 'pending'],
            [['category'], 'default', 'value' => 'messages'],
        ];
    }
}
