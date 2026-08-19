<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Queue job for creating translation backups
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\jobs;

use Craft;
use craft\queue\BaseJob;
use lindemannrock\base\traits\QueueTtrTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\TranslationManager;
use yii\queue\RetryableJobInterface;

/**
 * Create Backup Job
 *
 * @since 1.0.0
 */
class CreateBackupJob extends BaseJob implements RetryableJobInterface
{
    use QueueTtrTrait;
    use LoggingTrait;

    /**
     * @var string The reason for the backup
     */
    public string $reason = 'scheduled';

    /**
     * @var bool Whether to reschedule after completion
     */
    public bool $reschedule = false;

    /**
     * @var string Stable recurring queue owner
     * @since 5.35.0
     */
    public string $recurringOwner = '';

    /**
     * @var string|null Next run time display string
     */
    public ?string $nextRunTime = null;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(TranslationManager::$plugin->id);

        if ($this->isRecurringScheduledBackup() && !$this->nextRunTime && TranslationManager::$plugin !== null) {
            $this->nextRunTime = TranslationManager::$plugin->scheduledBackups->getNextRunTime();
        }
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): ?string
    {
        $pluginName = TranslationManager::$plugin->getSettings()->getDisplayName();
        $description = Craft::t('translation-manager', '{pluginName}: Scheduled auto backup', ['pluginName' => $pluginName]);

        if ($this->nextRunTime) {
            $description .= " ({$this->nextRunTime})";
        }

        return $description;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        return false;
    }
    
    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        if ($this->isRecurringScheduledBackup()) {
            TranslationManager::$plugin->scheduledBackups->runOccurrence(fn() => $this->createBackup());
            return;
        }

        $this->createBackup();
    }

    private function createBackup(): void
    {
        $backupService = TranslationManager::getInstance()->backup;
        
        // Create the backup
        $backupPath = $backupService->createBackup($this->reason);
        
        if ($backupPath !== null) {
            $this->logInfo('Scheduled backup created successfully', [
                'filename' => basename($backupPath),
            ]);
            
            // Clean old backups based on retention policy
            $settings = TranslationManager::getInstance()->getSettings();
            if ($settings->backupRetentionDays > 0) {
                $deleted = $backupService->cleanupOldBackups();
                if ($deleted > 0) {
                    $this->logInfo('Cleaned old backups', ['deleted' => $deleted]);
                }
            }
            return;
        }

        $this->logInfo('Scheduled backup completed as an empty-state no-op');
    }

    private function isRecurringScheduledBackup(): bool
    {
        return $this->reason === 'scheduled' && $this->reschedule;
    }
}
