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
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('translation-manager');
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): ?string
    {
        $pluginName = TranslationManager::getInstance()->getSettings()->pluginName;
        return Craft::t('translation-manager', '{pluginName}: Scheduled auto backup', ['pluginName' => $pluginName]);
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
                'filename' => basename($backupPath)
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
        
        $delay = match ($settings->backupSchedule) {
            'daily' => 86400, // 24 hours
            'weekly' => 604800, // 7 days
            'monthly' => 2592000, // 30 days
            default => 0,
        };
        
        if ($delay > 0) {
            // Create a new job for the next backup
            $job = new self([
                'reason' => 'scheduled',
                'reschedule' => true,
            ]);
            
            Craft::$app->getQueue()->delay($delay)->push($job);

            $this->logInfo('Next backup scheduled', ['delay_seconds' => $delay]);
        }
    }
}