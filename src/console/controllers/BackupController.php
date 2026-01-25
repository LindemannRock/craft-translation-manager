<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Console controller for backup management commands
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use lindemannrock\translationmanager\TranslationManager;
use yii\console\ExitCode;

/**
 * Backup management commands
 *
 * @since 1.0.0
 */
class BackupController extends Controller
{
    /**
     * @var string|null The reason for the backup
     */
    public ?string $reason = null;
    
    /**
     * @var bool Whether to clean old backups
     */
    public bool $clean = true;
    
    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        
        switch ($actionID) {
            case 'create':
                $options[] = 'reason';
                $options[] = 'clean';
                break;
        }
        
        return $options;
    }
    
    /**
     * Creates a backup of all translations
     *
     * @return int
     * @since 1.0.0
     */
    public function actionCreate(): int
    {
        $this->stdout("Creating translation backup...\n", Console::FG_YELLOW);
        
        $settings = TranslationManager::getInstance()->getSettings();
        
        if (!$settings->backupEnabled) {
            $this->stderr("Backups are disabled in settings\n", Console::FG_RED);
            return ExitCode::CONFIG;
        }
        
        $reason = $this->reason ?? 'console';
        
        try {
            $backupService = TranslationManager::getInstance()->backup;
            $backupPath = $backupService->createBackup($reason);
            
            if ($backupPath) {
                $this->stdout("✓ Backup created successfully\n", Console::FG_GREEN);
                $this->stdout("  Path: " . basename($backupPath) . "\n");
                
                // Clean old backups if enabled
                if ($this->clean && $settings->backupRetentionDays > 0) {
                    $this->stdout("\nCleaning old backups...\n", Console::FG_YELLOW);
                    $deleted = $backupService->cleanupOldBackups();
                    if ($deleted > 0) {
                        $this->stdout("✓ Deleted $deleted old backup(s)\n", Console::FG_GREEN);
                    } else {
                        $this->stdout("  No old backups to clean\n");
                    }
                }
                
                return ExitCode::OK;
            } else {
                $this->stderr("✗ Failed to create backup\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        } catch (\Exception $e) {
            $this->stderr("✗ Error: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Runs scheduled backup based on settings
     *
     * @return int
     * @since 1.0.0
     */
    public function actionScheduled(): int
    {
        $settings = TranslationManager::getInstance()->getSettings();
        
        if (!$settings->backupEnabled) {
            $this->stdout("Backups are disabled\n");
            return ExitCode::OK;
        }
        
        if ($settings->backupSchedule === 'manual') {
            $this->stdout("Backup schedule is set to manual\n");
            return ExitCode::OK;
        }
        
        $this->stdout("Checking backup schedule...\n", Console::FG_YELLOW);
        
        // Check last backup time
        $lastBackupTime = $this->getLastScheduledBackupTime();
        $currentTime = time();
        
        $shouldBackup = match ($settings->backupSchedule) {
            'daily' => ($currentTime - $lastBackupTime) >= 86400,
            'weekly' => ($currentTime - $lastBackupTime) >= 604800,
            'monthly' => ($currentTime - $lastBackupTime) >= 2592000,
            default => false,
        };
        
        if ($shouldBackup) {
            $this->stdout("Running scheduled backup...\n", Console::FG_GREEN);
            $this->reason = 'scheduled';
            return $this->actionCreate();
        } else {
            $this->stdout("No backup needed at this time\n");
            return ExitCode::OK;
        }
    }
    
    /**
     * Lists all backups
     *
     * @return int
     * @since 1.0.0
     */
    public function actionList(): int
    {
        $this->stdout("Available backups:\n\n", Console::FG_YELLOW);
        
        $backups = TranslationManager::getInstance()->backup->getBackups();
        
        if (empty($backups)) {
            $this->stdout("No backups found\n");
            return ExitCode::OK;
        }
        
        $this->stdout(str_pad("Date", 20) . str_pad("Reason", 15) . str_pad("Size", 12) . "Translations\n");
        $this->stdout(str_repeat("-", 60) . "\n");
        
        foreach ($backups as $backup) {
            $timestamp = is_int($backup['timestamp'])
                ? date('Y-m-d H:i:s', $backup['timestamp'])
                : (string) $backup['timestamp'];
            $this->stdout(
                str_pad($timestamp, 20) .
                str_pad($backup['reason'], 15) .
                str_pad(TranslationManager::getInstance()->backup->formatBytes($backup['size']), 12) .
                $backup['translationCount'] . "\n"
            );
        }
        
        return ExitCode::OK;
    }
    
    /**
     * Cleans old backups based on retention settings
     *
     * @return int
     * @since 1.0.0
     */
    public function actionClean(): int
    {
        $settings = TranslationManager::getInstance()->getSettings();
        
        if ($settings->backupRetentionDays <= 0) {
            $this->stdout("Backup retention is disabled (set to keep forever)\n");
            return ExitCode::OK;
        }
        
        $this->stdout("Cleaning backups older than {$settings->backupRetentionDays} days...\n", Console::FG_YELLOW);
        
        try {
            $deleted = TranslationManager::getInstance()->backup->cleanupOldBackups();
            
            if ($deleted > 0) {
                $this->stdout("✓ Deleted $deleted old backup(s)\n", Console::FG_GREEN);
            } else {
                $this->stdout("No old backups to clean\n");
            }
            
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("✗ Error: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Get the timestamp of the last scheduled backup
     *
     * @return int
     */
    private function getLastScheduledBackupTime(): int
    {
        $backups = TranslationManager::getInstance()->backup->getBackups();
        
        foreach ($backups as $backup) {
            if ($backup['reason'] === 'scheduled') {
                return is_int($backup['timestamp']) ? $backup['timestamp'] : 0;
            }
        }
        
        // No scheduled backup found, return 0
        return 0;
    }
}
