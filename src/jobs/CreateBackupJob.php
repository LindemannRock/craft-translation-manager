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
use DateTime;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\ScheduleHelper;
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

        if ($this->reschedule && !$this->nextRunTime) {
            $this->nextRunTime = $this->formatNextRunTime($this->calculateNextRun());
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

        if (!$settings->backupEnabled || $settings->getEffectiveBackupSchedule() === 'disabled') {
            return;
        }

        $nextRun = $this->calculateNextRun();
        $delay = $this->calculateNextRunDelay($settings->getEffectiveBackupSchedule());

        if ($nextRun !== null && $delay > 0) {
            $job = new self([
                'reason' => 'scheduled',
                'reschedule' => true,
                'nextRunTime' => $this->formatNextRunTime($nextRun),
            ]);

            Craft::$app->getQueue()->delay($delay)->push($job);

            $this->logInfo('Next backup scheduled', [
                'delay_seconds' => $delay,
                'next_run' => $job->nextRunTime,
            ]);
        }
    }

    /**
     * Calculate the delay in seconds for the next backup
     */
    private function calculateNextRunDelay(string $schedule): int
    {
        return ScheduleHelper::calculateDelaySeconds($schedule);
    }

    /**
     * Calculate the next scheduled backup run.
     */
    private function calculateNextRun(): ?DateTime
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return ScheduleHelper::calculateNext($settings->getEffectiveBackupSchedule());
    }

    /**
     * Format the next run for the serialized queue description.
     */
    private function formatNextRunTime(?DateTime $nextRun): ?string
    {
        if ($nextRun === null) {
            return null;
        }

        return DateFormatHelper::formatCompactDatetimeFromSettings(
            $nextRun,
            TranslationManager::getInstance()->getSettings(),
            false,
            false,
        );
    }
}
