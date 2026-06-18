<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Active Record for generated translation file status reports
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\records;

use craft\db\ActiveRecord;

/**
 * Generation Status Record
 *
 * @property int $id
 * @property string|null $fingerprint
 * @property string $status
 * @property string $reason
 * @property string $triggerType
 * @property string|null $generationPath
 * @property int $translationCount
 * @property int $writtenFileCount
 * @property int $deletedFileCount
 * @property string|null $verificationStatus
 * @property string|null $message
 * @property string|null $details
 * @property string|null $dateStarted
 * @property string|null $dateFinished
 * @property string|null $dateCreated
 * @property string|null $dateUpdated
 * @property string $uid
 * @since 5.28.0
 */
class GenerationStatusRecord extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_NOOP = 'noop';

    public const REASON_FRESHNESS_CHECK = 'freshness-check';
    public const REASON_MANUAL = 'manual';
    public const REASON_CLI = 'cli';
    public const REASON_SETTINGS_CHANGE = 'settings-change';

    public const TRIGGER_RUNTIME = 'runtime';
    public const TRIGGER_QUEUE = 'queue';
    public const TRIGGER_CP = 'cp';
    public const TRIGGER_CLI = 'cli';

    public const VERIFICATION_PASSED = 'passed';
    public const VERIFICATION_FAILED = 'failed';
    public const VERIFICATION_SKIPPED = 'skipped';

    public static function tableName(): string
    {
        return '{{%translationmanager_generation_status}}';
    }

    public function rules(): array
    {
        return [
            [['status', 'reason', 'triggerType'], 'required'],
            [['generationPath', 'message', 'details'], 'string'],
            [['translationCount', 'writtenFileCount', 'deletedFileCount'], 'integer', 'min' => 0],
            [['fingerprint'], 'string', 'max' => 64],
            [['status', 'verificationStatus'], 'string', 'max' => 20],
            [['reason'], 'string', 'max' => 50],
            [['triggerType'], 'string', 'max' => 20],
            [['status'], 'in', 'range' => [
                self::STATUS_PENDING,
                self::STATUS_RUNNING,
                self::STATUS_SUCCESS,
                self::STATUS_FAILED,
                self::STATUS_NOOP,
            ]],
            [['reason'], 'in', 'range' => [
                self::REASON_FRESHNESS_CHECK,
                self::REASON_MANUAL,
                self::REASON_CLI,
                self::REASON_SETTINGS_CHANGE,
            ]],
            [['triggerType'], 'in', 'range' => [
                self::TRIGGER_RUNTIME,
                self::TRIGGER_QUEUE,
                self::TRIGGER_CP,
                self::TRIGGER_CLI,
            ]],
            [['verificationStatus'], 'in', 'range' => [
                self::VERIFICATION_PASSED,
                self::VERIFICATION_FAILED,
                self::VERIFICATION_SKIPPED,
            ]],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
            [['reason'], 'default', 'value' => self::REASON_FRESHNESS_CHECK],
            [['triggerType'], 'default', 'value' => self::TRIGGER_RUNTIME],
            [['translationCount', 'writtenFileCount', 'deletedFileCount'], 'default', 'value' => 0],
        ];
    }
}
