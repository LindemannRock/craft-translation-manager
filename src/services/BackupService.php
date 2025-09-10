<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Service for managing translation backups
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\services;

use Craft;
use craft\base\Component;
use craft\helpers\DateTimeHelper;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use lindemannrock\translationmanager\TranslationManager;
use lindemannrock\translationmanager\traits\LoggingTrait;

/**
 * Backup Service
 * 
 * Handles automatic backups of translations to @storage/translation-manager/backups/[date]
 */
class BackupService extends Component
{
    use LoggingTrait;
    
    /**
     * Get the backup base path
     */
    public function getBackupPath(): string
    {
        $settings = TranslationManager::getInstance()->getSettings();
        return $settings->getBackupPath();
    }
    
    /**
     * Create a backup of all translations
     * 
     * @param string|null $reason Optional reason for the backup (e.g., "before_import", "manual", "scheduled")
     * @return string|null The backup directory path on success, null on failure
     */
    public function createBackup(?string $reason = null): ?string
    {
        $this->logInfo('Creating backup', ['reason' => $reason ?? 'manual']);
        
        try {
            // Determine the subfolder based on reason
            $subfolder = match($reason) {
                'scheduled' => 'scheduled',
                'before_import' => 'imports',
                'before_cleanup' => 'maintenance',
                'before_clear' => 'maintenance',
                'manual' => 'manual',
                default => 'other'
            };
            
            // Create timestamp-based directory name
            $timestamp = DateTimeHelper::currentTimeStamp();
            $date = date('Y-m-d_H-i-s', $timestamp);
            $backupDir = $this->getBackupPath() . '/' . $subfolder . '/' . $date;
            
            // Ensure backup directory exists with proper permissions
            try {
                FileHelper::createDirectory($backupDir);
            } catch (\Exception $e) {
                $this->logError('Failed to create backup directory', [
                    'path' => $backupDir,
                    'error' => $e->getMessage()
                ]);
                throw new \Exception('Failed to create backup directory. Please ensure the backup path is writable: ' . $this->getBackupPath());
            }
            
            // Get all translations
            $translations = TranslationManager::getInstance()->translations->getTranslations();
            
            if (empty($translations)) {
                $this->logWarning('No translations to backup');
                // For restore operations, we still want to create an empty backup
                // to mark the state before restore
                if ($reason === 'Before Restore') {
                    $translations = [];
                } else {
                    return null;
                }
            }
            
            // Create metadata file
            $metadata = [
                'date' => $date,
                'timestamp' => $timestamp,
                'reason' => $reason ?? 'manual',
                'user' => Craft::$app->getUser()->getIdentity()->username ?? 'system',
                'userId' => Craft::$app->getUser()->getId(),
                'translationCount' => count($translations),
                'formieEnabled' => TranslationManager::getInstance()->getSettings()->enableFormieIntegration,
                'siteEnabled' => TranslationManager::getInstance()->getSettings()->enableSiteTranslations,
                'craftVersion' => Craft::$app->getVersion(),
                'pluginVersion' => TranslationManager::getInstance()->getVersion(),
            ];
            
            FileHelper::writeToFile($backupDir . '/metadata.json', Json::encode($metadata));
            
            // Group translations by type
            $formieTranslations = [];
            $siteTranslations = [];
            
            foreach ($translations as $translation) {
                if (str_starts_with($translation['context'], 'formie.')) {
                    $formieTranslations[] = $translation;
                } else {
                    $siteTranslations[] = $translation;
                }
            }
            
            // Save Formie translations
            if (!empty($formieTranslations)) {
                FileHelper::writeToFile(
                    $backupDir . '/formie-translations.json', 
                    Json::encode($formieTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            }
            
            // Save site translations
            if (!empty($siteTranslations)) {
                FileHelper::writeToFile(
                    $backupDir . '/site-translations.json', 
                    Json::encode($siteTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            }
            
            // Also backup the generated PHP files if they exist
            $this->backupGeneratedFiles($backupDir);
            
            $this->logInfo('Backup created successfully', [
                'path' => $backupDir,
                'translations' => count($translations),
                'formie' => count($formieTranslations),
                'site' => count($siteTranslations)
            ]);
            
            // Clean up old backups based on retention policy
            $this->cleanupOldBackups();
            
            return $backupDir;
            
        } catch (\Exception $e) {
            $this->logError('Failed to create backup', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Backup generated PHP translation files
     */
    private function backupGeneratedFiles(string $backupDir): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $exportPath = $settings->getExportPath();
        
        // Get all site languages for backup
        $sites = TranslationManager::getInstance()->getAllowedSites();
        $filesToBackup = [];
        
        foreach ($sites as $site) {
            $language = $site->language;
            $filesToBackup[] = $language . '/formie.php';
            $filesToBackup[] = $language . '/' . $settings->translationCategory . '.php';
        }
        
        $phpDir = $backupDir . '/php-files';
        FileHelper::createDirectory($phpDir);
        
        foreach ($filesToBackup as $file) {
            $sourcePath = $exportPath . '/' . $file;
            if (file_exists($sourcePath)) {
                $destPath = $phpDir . '/' . str_replace('/', '_', $file);
                copy($sourcePath, $destPath);
                $this->logInfo('Backed up PHP file', ['file' => $file]);
            }
        }
    }
    
    /**
     * Get all available backups
     * 
     * @return array Array of backup info sorted by date (newest first)
     */
    public function getBackups(): array
    {
        $backupPath = $this->getBackupPath();
        $backups = [];
        
        if (!is_dir($backupPath)) {
            return $backups;
        }
        
        // Define the subfolders to scan
        $subfolders = ['scheduled', 'imports', 'maintenance', 'manual', 'other'];
        
        // First check for legacy backups in root (backward compatibility)
        $rootDirs = FileHelper::findDirectories($backupPath, [
            'recursive' => false,
        ]);
        
        foreach ($rootDirs as $dir) {
            $dirName = basename($dir);
            // Skip if it's one of our new subfolders
            if (in_array($dirName, $subfolders)) {
                continue;
            }
            
            $metadataFile = $dir . '/metadata.json';
            if (file_exists($metadataFile)) {
                try {
                    $metadata = Json::decode(file_get_contents($metadataFile));
                    $metadata['path'] = $dir;
                    $metadata['name'] = basename($dir);
                    $metadata['size'] = $this->getDirectorySize($dir);
                    $metadata['folder'] = 'legacy';
                    $backups[] = $metadata;
                } catch (\Exception $e) {
                    $this->logError('Failed to read backup metadata', [
                        'dir' => $dir,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        // Now scan each subfolder
        foreach ($subfolders as $subfolder) {
            $subfolderPath = $backupPath . '/' . $subfolder;
            if (!is_dir($subfolderPath)) {
                continue;
            }
            
            $dirs = FileHelper::findDirectories($subfolderPath, [
                'recursive' => false,
            ]);
            
            foreach ($dirs as $dir) {
                $metadataFile = $dir . '/metadata.json';
                if (file_exists($metadataFile)) {
                    try {
                        $metadata = Json::decode(file_get_contents($metadataFile));
                        $metadata['path'] = $dir;
                        $metadata['name'] = $subfolder . '/' . basename($dir);
                        $metadata['size'] = $this->getDirectorySize($dir);
                        $metadata['folder'] = $subfolder;
                        $backups[] = $metadata;
                    } catch (\Exception $e) {
                        $this->logError('Failed to read backup metadata', [
                            'dir' => $dir,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        // Sort by timestamp descending (newest first)
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return $backups;
    }
    
    /**
     * Restore from a backup
     * 
     * @param string $backupName The backup directory name (e.g., "2024-01-05_14-30-00")
     * @return array Result with success status and message
     */
    public function restoreBackup(string $backupName): array
    {
        $this->logInfo('Starting backup restore', ['backup' => $backupName]);
        
        // Handle subfolder structure
        if (str_contains($backupName, '/')) {
            $backupDir = $this->getBackupPath() . '/' . $backupName;
        } else {
            // Legacy backup in root
            $backupDir = $this->getBackupPath() . '/' . $backupName;
        }
        
        if (!is_dir($backupDir)) {
            return [
                'success' => false,
                'message' => 'Backup not found'
            ];
        }
        
        try {
            // Create a backup of current state before restoring if backups are enabled
            $settings = TranslationManager::getInstance()->getSettings();
            $this->logInfo('Restore: Checking backup settings', ['backupEnabled' => $settings->backupEnabled]);
            
            if ($settings->backupEnabled) {
                $this->logInfo('Restore: Creating pre-restore backup');
                $preRestoreBackup = $this->createBackup('manual');
                if (!$preRestoreBackup) {
                    $this->logWarning('Failed to create pre-restore backup, continuing with restore');
                } else {
                    $this->logInfo('Restore: Pre-restore backup created', ['path' => $preRestoreBackup]);
                }
            } else {
                $this->logInfo('Restore: Skipping pre-restore backup (backups disabled)');
            }
            
            // Clear existing translations
            TranslationManager::getInstance()->translations->clearAllTranslations();
            
            $imported = 0;
            $errors = [];
            
            // Restore Formie translations
            $formieFile = $backupDir . '/formie-translations.json';
            if (file_exists($formieFile)) {
                $result = $this->restoreFromFile($formieFile);
                $imported += $result['imported'];
                $errors = array_merge($errors, $result['errors']);
            }
            
            // Restore site translations
            $siteFile = $backupDir . '/site-translations.json';
            if (file_exists($siteFile)) {
                $result = $this->restoreFromFile($siteFile);
                $imported += $result['imported'];
                $errors = array_merge($errors, $result['errors']);
            }
            
            // Regenerate translation files
            TranslationManager::getInstance()->export->exportAll();
            
            $this->logInfo('Backup restored successfully', [
                'backup' => $backupName,
                'imported' => $imported,
                'errors' => count($errors)
            ]);
            
            return [
                'success' => true,
                'message' => "Restored {$imported} translations from backup",
                'imported' => $imported,
                'errors' => $errors,
                'preRestoreBackup' => $preRestoreBackup
            ];
            
        } catch (\Exception $e) {
            $this->logError('Failed to restore backup', [
                'backup' => $backupName,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Restore translations from a JSON file
     */
    private function restoreFromFile(string $filePath): array
    {
        $imported = 0;
        $errors = [];
        
        try {
            $content = file_get_contents($filePath);
            $translations = Json::decode($content);
            
            foreach ($translations as $data) {
                try {
                    $translation = new \lindemannrock\translationmanager\records\TranslationRecord();
                    $translation->source = $data['source'];
                    $translation->sourceHash = $data['sourceHash'];
                    $translation->context = $data['context'];
                    
                    // Handle both old and new field names for backward compatibility
                    $translation->translationKey = $data['translationKey'] ?? $data['englishText'] ?? '';
                    $translation->translation = $data['translation'] ?? $data['arabicText'] ?? '';
                    $translation->siteId = $data['siteId'] ?? 1; // Default to primary site for old backups
                    
                    $translation->status = $data['status'];
                    $translation->usageCount = $data['usageCount'] ?? 1;
                    $translation->lastUsed = DateTimeHelper::toDateTime($data['lastUsed'] ?? time());
                    $translation->dateCreated = DateTimeHelper::toDateTime($data['dateCreated'] ?? time());
                    $translation->dateUpdated = DateTimeHelper::toDateTime($data['dateUpdated'] ?? time());
                    $translation->uid = $data['uid'] ?? \craft\helpers\StringHelper::UUID();
                    
                    if ($translation->save()) {
                        $imported++;
                    } else {
                        $errors[] = 'Failed to import: ' . ($data['translationKey'] ?? $data['englishText'] ?? 'Unknown');
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Import error: ' . $e->getMessage();
                }
            }
        } catch (\Exception $e) {
            $errors[] = 'Failed to read file: ' . $e->getMessage();
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors
        ];
    }
    
    /**
     * Delete a backup
     */
    public function deleteBackup(string $backupName): bool
    {
        // Handle subfolder structure
        if (str_contains($backupName, '/')) {
            $backupDir = $this->getBackupPath() . '/' . $backupName;
        } else {
            // Legacy backup in root
            $backupDir = $this->getBackupPath() . '/' . $backupName;
        }
        
        $this->logInfo('Attempting to delete backup', [
            'backup' => $backupName,
            'path' => $backupDir,
            'exists' => is_dir($backupDir) ? 'yes' : 'no'
        ]);
        
        if (!is_dir($backupDir)) {
            $this->logError('Backup directory not found', ['backup' => $backupName, 'path' => $backupDir]);
            return false;
        }
        
        try {
            FileHelper::removeDirectory($backupDir);
            $this->logInfo('Deleted backup successfully', ['backup' => $backupName]);
            return true;
        } catch (\Exception $e) {
            $this->logError('Failed to delete backup', [
                'backup' => $backupName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Clean up old backups based on retention policy
     * Manual backups are never deleted automatically
     */
    public function cleanupOldBackups(): int
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $retentionDays = $settings->backupRetentionDays ?? 30;
        
        if ($retentionDays <= 0) {
            // No cleanup if retention is 0 or negative
            return 0;
        }
        
        $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
        $backups = $this->getBackups();
        $deleted = 0;
        $skipped = 0;
        
        foreach ($backups as $backup) {
            // Skip manual backups - they must be deleted manually
            if ($backup['reason'] === 'manual' || $backup['reason'] === 'Manual' || 
                (isset($backup['folder']) && $backup['folder'] === 'manual')) {
                $skipped++;
                continue;
            }
            
            if ($backup['timestamp'] < $cutoffTime) {
                if ($this->deleteBackup($backup['name'])) {
                    $deleted++;
                }
            }
        }
        
        if ($deleted > 0 || $skipped > 0) {
            $this->logInfo('Cleaned up old backups', [
                'deleted' => $deleted,
                'skipped_manual' => $skipped,
                'retentionDays' => $retentionDays
            ]);
        }
        
        return $deleted;
    }
    
    /**
     * Get the size of a directory in bytes
     */
    private function getDirectorySize(string $dir): int
    {
        $size = 0;
        $files = FileHelper::findFiles($dir);
        
        foreach ($files as $file) {
            $size += filesize($file);
        }
        
        return $size;
    }
    
    /**
     * Format bytes into human readable format
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}