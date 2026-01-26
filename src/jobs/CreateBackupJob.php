<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Queue job for creating translation backups
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\jobs;

use Craft;
use craft\queue\BaseJob;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Create Backup Job
 *
 * @since 1.0.0
 */
class CreateBackupJob extends BaseJob
{
    use LoggingTrait;

    /**
     * @var string The reason for the backup
     */
    public string $reason = 'scheduled';

    /**
     * @var bool Whether to reschedule after completion
     * @deprecated Use cron for scheduling instead
     */
    public bool $reschedule = false;

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
        $this->setLoggingHandle('translation-manager');

        // Calculate and set next run time if not already set
        if ($this->reschedule && !$this->nextRunTime) {
            $settings = TranslationManager::getInstance()->getSettings();
            if ($settings->backupEnabled && $settings->backupSchedule !== 'manual') {
                $delay = $this->calculateNextRunDelay($settings->backupSchedule);
                if ($delay > 0) {
                    // Short format: "Nov 8, 12:00am"
                    $this->nextRunTime = date('M j, g:ia', time() + $delay);
                }
            }
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
    public function execute($queue): void
    {
        $backupService = TranslationManager::getInstance()->backup;
        
        // Create the backup
        $backupPath = $backupService->createBackup($this->reason);
        
        if ($backupPath) {
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
            
            // Reschedule if needed
            if ($this->reschedule) {
                $this->scheduleNextBackup();
            }
        } else {
            throw new \Exception('Failed to create scheduled backup');
        }
    }
    
    /**
     * Schedule the next backup based on settings
     */
    private function scheduleNextBackup(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();

        if (!$settings->backupEnabled || $settings->backupSchedule === 'manual') {
            return;
        }

        // Prevent duplicate scheduling - check if another backup job already exists
        // This prevents fan-out if multiple jobs end up in the queue (manual runs, retries, etc.)
        $existingJob = (new \craft\db\Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'translationmanager'])
            ->andWhere(['like', 'job', 'CreateBackupJob'])
            ->exists();

        if ($existingJob) {
            $this->logDebug('Skipping reschedule - backup job already exists');
            return;
        }

        $delay = $this->calculateNextRunDelay($settings->backupSchedule);

        if ($delay > 0) {
            // Calculate next run time for display
            $nextRunTime = date('M j, g:ia', time() + $delay);

            // Create a new job for the next backup
            $job = new self([
                'reason' => 'scheduled',
                'reschedule' => true,
                'nextRunTime' => $nextRunTime,
            ]);

            Craft::$app->getQueue()->delay($delay)->push($job);

            $this->logInfo('Next backup scheduled', [
                'delay_seconds' => $delay,
                'next_run' => $nextRunTime,
            ]);
        }
    }

    /**
     * Calculate the delay in seconds for the next backup
     */
    private function calculateNextRunDelay(string $schedule): int
    {
        return match ($schedule) {
            'daily' => 86400, // 24 hours
            'weekly' => 604800, // 7 days
            'monthly' => 2592000, // 30 days
            default => 0,
        };
    }
}
