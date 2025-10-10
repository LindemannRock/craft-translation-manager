<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Service for managing translation backups using asset volumes
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
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * Volume Backup Service
 *
 * Handles backups using asset volumes (including cloud storage like Servd/S3)
 */
class VolumeBackupService extends Component
{
    use LoggingTrait;

    /**
     * @var \craft\base\FsInterface|null The filesystem instance for the selected volume
     */
    private $_volumeFs = null;

    /**
     * @var bool Whether we're using a volume for backups
     */
    private $_useVolume = false;

    /**
     * @var string The base path for backups within the volume
     */
    private $_volumeBackupPath = 'translation-manager/backups';

    /**
     * Initialize the service
     */
    public function init(): void
    {
        parent::init();

        $settings = TranslationManager::getInstance()->getSettings();

        // Check if a backup volume is configured
        if ($settings->backupVolumeUid) {
            $volume = Craft::$app->getVolumes()->getVolumeByUid($settings->backupVolumeUid);
            if ($volume && $volume->getFs()) {
                $this->_volumeFs = $volume->getFs();
                $this->_useVolume = true;

                $this->logInfo('Using volume for backups', [
                    'volumeName' => $volume->name,
                    'volumeUid' => $settings->backupVolumeUid,
                    'fsClass' => get_class($this->_volumeFs)
                ]);

                // Ensure base directory exists in the volume
                try {
                    if (!$this->_volumeFs->directoryExists($this->_volumeBackupPath)) {
                        $this->_volumeFs->createDirectory($this->_volumeBackupPath);
                        $this->logInfo('Created backup directory in volume', ['path' => $this->_volumeBackupPath]);
                    }
                } catch (\Exception $e) {
                    $this->logWarning('Could not create volume directory, will create on demand', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Get the backup base path
     */
    public function getBackupPath(): string
    {
        if ($this->_useVolume) {
            return $this->_volumeBackupPath;
        }

        // Fall back to local storage
        $settings = TranslationManager::getInstance()->getSettings();
        return $settings->getBackupPath();
    }

    /**
     * Create a backup of all translations
     *
     * @param string|null $reason Optional reason for the backup
     * @return string|null The backup directory path on success, null on failure
     */
    public function createBackup(?string $reason = null): ?string
    {
        $reasonText = $this->getDisplayReason($reason ?? 'manual');
        $storageType = $this->_useVolume ? 'volume' : 'local';
        $this->logInfo("Creating backup", [
            'reason' => $reasonText,
            'storageType' => $storageType
        ]);

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
            $backupDir = $subfolder . '/' . $date;

            // Get all translations
            $translations = TranslationManager::getInstance()->translations->getTranslations();

            if (empty($translations) && $reason !== 'Before Restore') {
                $this->logWarning('No translations to backup');
                return null;
            }

            // Create metadata
            $metadata = [
                'date' => $date,
                'timestamp' => $timestamp,
                'reason' => $reason ?? 'manual',
                'user' => Craft::$app->getUser()->getIdentity()->username ?? 'system',
                'userId' => Craft::$app->getUser()->getId(),
                'translationCount' => count($translations ?? []),
                'formieEnabled' => TranslationManager::getInstance()->getSettings()->enableFormieIntegration,
                'siteEnabled' => TranslationManager::getInstance()->getSettings()->enableSiteTranslations,
                'craftVersion' => Craft::$app->getVersion(),
                'pluginVersion' => TranslationManager::getInstance()->getVersion(),
            ];

            // Group translations by type
            $formieTranslations = [];
            $siteTranslations = [];

            foreach ($translations ?? [] as $translation) {
                if (str_starts_with($translation['context'], 'formie.')) {
                    $formieTranslations[] = $translation;
                } else {
                    $siteTranslations[] = $translation;
                }
            }

            if ($this->_useVolume) {
                return $this->_createVolumeBackup($backupDir, $metadata, $formieTranslations, $siteTranslations);
            } else {
                return $this->_createLocalBackup($backupDir, $metadata, $formieTranslations, $siteTranslations);
            }

        } catch (\Exception $e) {
            $this->logError('Failed to create backup', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create backup using volume storage (works with S3/Servd)
     */
    private function _createVolumeBackup(string $backupDir, array $metadata, array $formieTranslations, array $siteTranslations): string
    {
        $fullPath = $this->_volumeBackupPath . '/' . $backupDir;

        // Ensure directory exists
        if (!$this->_volumeFs->directoryExists($fullPath)) {
            $this->_volumeFs->createDirectory($fullPath);
        }

        // Write metadata file
        $this->_volumeFs->write($fullPath . '/metadata.json', Json::encode($metadata));

        // Save Formie translations
        if (!empty($formieTranslations)) {
            $this->_volumeFs->write($fullPath . '/formie-translations.json', Json::encode($formieTranslations));
        }

        // Save site translations
        if (!empty($siteTranslations)) {
            $this->_volumeFs->write($fullPath . '/site-translations.json', Json::encode($siteTranslations));
        }

        $this->logInfo('Backup created in volume', [
            'path' => $fullPath,
            'formieCount' => count($formieTranslations),
            'siteCount' => count($siteTranslations)
        ]);

        return $fullPath;
    }

    /**
     * Create backup using local storage
     */
    private function _createLocalBackup(string $backupDir, array $metadata, array $formieTranslations, array $siteTranslations): string
    {
        $basePath = $this->getBackupPath();
        $fullPath = $basePath . '/' . $backupDir;

        // Ensure directory exists
        FileHelper::createDirectory($fullPath);

        // Write metadata file
        FileHelper::writeToFile($fullPath . '/metadata.json', Json::encode($metadata));

        // Save Formie translations
        if (!empty($formieTranslations)) {
            FileHelper::writeToFile($fullPath . '/formie-translations.json', Json::encode($formieTranslations));
        }

        // Save site translations
        if (!empty($siteTranslations)) {
            FileHelper::writeToFile($fullPath . '/site-translations.json', Json::encode($siteTranslations));
        }

        $this->logInfo('Backup created locally', [
            'path' => $fullPath,
            'formieCount' => count($formieTranslations),
            'siteCount' => count($siteTranslations)
        ]);

        return $fullPath;
    }

    /**
     * Get list of all backups
     */
    public function getBackups(): array
    {
        if ($this->_useVolume) {
            return $this->_getVolumeBackups();
        } else {
            return $this->_getLocalBackups();
        }
    }

    /**
     * Get backups from volume storage
     */
    private function _getVolumeBackups(): array
    {
        $backups = [];

        try {
            // List all subdirectories
            $subfolders = ['manual', 'scheduled', 'imports', 'maintenance', 'other'];

            foreach ($subfolders as $subfolder) {
                $folderPath = $this->_volumeBackupPath . '/' . $subfolder;

                if (!$this->_volumeFs->directoryExists($folderPath)) {
                    continue;
                }

                // List contents of subfolder
                $contents = $this->_volumeFs->getFileList($folderPath, false);

                foreach ($contents as $item) {
                    // Check if this is a directory
                    $fileName = isset($item->basename) ? $item->basename : (isset($item->filename) ? $item->filename : $item->path);
                    $fullItemPath = $folderPath . '/' . $fileName;

                    if ($this->_volumeFs->directoryExists($fullItemPath)) {
                        $backupPath = $subfolder . '/' . $fileName;
                        $metadataPath = $this->_volumeBackupPath . '/' . $backupPath . '/metadata.json';

                        try {
                            if ($this->_volumeFs->fileExists($metadataPath)) {
                                $metadataContent = $this->_volumeFs->read($metadataPath);
                                $metadata = Json::decode($metadataContent);

                                $backups[] = [
                                    'name' => $backupPath,
                                    'timestamp' => $metadata['timestamp'],
                                    'reason' => $metadata['reason'],
                                    'user' => $metadata['user'] ?? 'Unknown',
                                    'translationCount' => $metadata['translationCount'] ?? 0,
                                    'size' => 0, // Size calculation for volumes is complex
                                ];
                            }
                        } catch (\Exception $e) {
                            // Skip invalid backups
                            continue;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logError('Failed to list volume backups', ['error' => $e->getMessage()]);
        }

        // Sort by timestamp desc
        usort($backups, fn($a, $b) => $b['timestamp'] - $a['timestamp']);

        return $backups;
    }

    /**
     * Get backups from local storage
     */
    private function _getLocalBackups(): array
    {
        $backupPath = $this->getBackupPath();
        $backups = [];

        if (!is_dir($backupPath)) {
            return [];
        }

        // Scan for backup directories
        $subfolders = ['manual', 'scheduled', 'imports', 'maintenance', 'other'];

        foreach ($subfolders as $subfolder) {
            $folderPath = $backupPath . '/' . $subfolder;
            if (!is_dir($folderPath)) {
                continue;
            }

            $dates = scandir($folderPath);
            foreach ($dates as $date) {
                if ($date === '.' || $date === '..') {
                    continue;
                }

                $backupDir = $folderPath . '/' . $date;
                if (!is_dir($backupDir)) {
                    continue;
                }

                $metadataFile = $backupDir . '/metadata.json';
                if (!file_exists($metadataFile)) {
                    continue;
                }

                try {
                    $metadata = Json::decode(file_get_contents($metadataFile));
                    $size = $this->_getDirectorySize($backupDir);

                    $backups[] = [
                        'name' => $subfolder . '/' . $date,
                        'timestamp' => $metadata['timestamp'],
                        'reason' => $metadata['reason'],
                        'user' => $metadata['user'] ?? 'Unknown',
                        'translationCount' => $metadata['translationCount'] ?? 0,
                        'size' => $size,
                    ];
                } catch (\Exception $e) {
                    // Skip invalid backups
                    continue;
                }
            }
        }

        // Sort by timestamp desc
        usort($backups, fn($a, $b) => $b['timestamp'] - $a['timestamp']);

        return $backups;
    }

    /**
     * Restore a backup
     */
    public function restoreBackup(string $backupName): bool
    {
        if ($this->_useVolume) {
            return $this->_restoreVolumeBackup($backupName);
        } else {
            return $this->_restoreLocalBackup($backupName);
        }
    }

    /**
     * Restore backup from volume storage
     */
    private function _restoreVolumeBackup(string $backupName): bool
    {
        $backupPath = $this->_volumeBackupPath . '/' . $backupName;

        try {
            // Read backup files
            $translations = [];

            $formiePath = $backupPath . '/formie-translations.json';
            if ($this->_volumeFs->fileExists($formiePath)) {
                $content = $this->_volumeFs->read($formiePath);
                $translations = array_merge($translations, Json::decode($content));
            }

            $sitePath = $backupPath . '/site-translations.json';
            if ($this->_volumeFs->fileExists($sitePath)) {
                $content = $this->_volumeFs->read($sitePath);
                $translations = array_merge($translations, Json::decode($content));
            }

            // Restore translations
            return $this->_restoreTranslations($translations);

        } catch (\Exception $e) {
            $this->logError('Failed to restore volume backup', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Restore backup from local storage
     */
    private function _restoreLocalBackup(string $backupName): bool
    {
        $backupDir = $this->getBackupPath() . '/' . $backupName;

        if (!is_dir($backupDir)) {
            throw new \Exception("Backup directory not found: $backupName");
        }

        try {
            // Read backup files
            $translations = [];

            $formiePath = $backupDir . '/formie-translations.json';
            if (file_exists($formiePath)) {
                $translations = array_merge($translations, Json::decode(file_get_contents($formiePath)));
            }

            $sitePath = $backupDir . '/site-translations.json';
            if (file_exists($sitePath)) {
                $translations = array_merge($translations, Json::decode(file_get_contents($sitePath)));
            }

            // Restore translations
            return $this->_restoreTranslations($translations);

        } catch (\Exception $e) {
            $this->logError('Failed to restore local backup', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Restore translations to database
     */
    private function _restoreTranslations(array $translations): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Clear existing translations
            $db->createCommand()->delete('{{%translationmanager_translations}}')->execute();

            // Insert restored translations
            foreach ($translations as $translation) {
                $db->createCommand()->insert('{{%translationmanager_translations}}', $translation)->execute();
            }

            $transaction->commit();

            $this->logInfo('Translations restored', ['count' => count($translations)]);
            return true;

        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a backup
     */
    public function deleteBackup(string $backupName): bool
    {
        if ($this->_useVolume) {
            return $this->_deleteVolumeBackup($backupName);
        } else {
            return $this->_deleteLocalBackup($backupName);
        }
    }

    /**
     * Delete backup from volume storage
     */
    private function _deleteVolumeBackup(string $backupName): bool
    {
        $backupPath = $this->_volumeBackupPath . '/' . $backupName;

        try {
            $this->_volumeFs->deleteDirectory($backupPath);
            $this->logInfo('Volume backup deleted', ['backup' => $backupName]);
            return true;
        } catch (\Exception $e) {
            $this->logError('Failed to delete volume backup', [
                'backup' => $backupName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete backup from local storage
     */
    private function _deleteLocalBackup(string $backupName): bool
    {
        $backupDir = $this->getBackupPath() . '/' . $backupName;

        if (!is_dir($backupDir)) {
            return false;
        }

        try {
            FileHelper::removeDirectory($backupDir);
            $this->logInfo('Local backup deleted', ['backup' => $backupName]);
            return true;
        } catch (\Exception $e) {
            $this->logError('Failed to delete local backup', [
                'backup' => $backupName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Format bytes to human readable
     */
    public function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Get directory size
     */
    private function _getDirectorySize($dir): int
    {
        $size = 0;
        foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $each) {
            $size += is_file($each) ? filesize($each) : $this->_getDirectorySize($each);
        }
        return $size;
    }

    /**
     * Convert internal reason code to user-friendly display text
     */
    private function getDisplayReason(string $reason): string
    {
        return match($reason) {
            'manual' => Craft::t('translation-manager', 'Manual'),
            'before_import' => Craft::t('translation-manager', 'Before Import'),
            'before_restore' => Craft::t('translation-manager', 'Before Restore'),
            'scheduled' => Craft::t('translation-manager', 'Scheduled'),
            'before_clear_all' => Craft::t('translation-manager', 'Before Clear All'),
            'before_clear_formie' => Craft::t('translation-manager', 'Before Clear Formie'),
            'before_clear_site' => Craft::t('translation-manager', 'Before Clear Site'),
            'before_cleanup' => Craft::t('translation-manager', 'Before Cleanup'),
            'before_cleanup_all' => Craft::t('translation-manager', 'Before Cleanup All'),
            'before_cleanup_formie' => Craft::t('translation-manager', 'Before Cleanup Formie'),
            'before_cleanup_site' => Craft::t('translation-manager', 'Before Cleanup Site'),
            'before_clear' => Craft::t('translation-manager', 'Before Clear'),
            default => Craft::t('translation-manager', ucfirst(str_replace('_', ' ', $reason)))
        };
    }
}
