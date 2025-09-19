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
use craft\helpers\StringHelper;
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
                    'volumeUid' => $settings->backupVolumeUid
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
            // Return a display path for volume storage
            $settings = TranslationManager::getInstance()->getSettings();
            $volume = Craft::$app->getVolumes()->getVolumeByUid($settings->backupVolumeUid);
            if ($volume) {
                // Get the actual filesystem path
                $fs = $volume->getFs();
                if ($fs && property_exists($fs, 'path')) {
                    $path = Craft::parseEnv($fs->path);
                    return rtrim($path, '/') . '/' . $this->_volumeBackupPath;
                }
                return "Volume: {$volume->name} / {$this->_volumeBackupPath}";
            }
        }

        // Fall back to local storage
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
        $this->logInfo('Creating backup', ['reason' => $reason ?? 'manual', 'useVolume' => $this->_useVolume]);

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

            // Use volume storage if configured
            if ($this->_useVolume) {
                return $this->_createVolumeBackup($subfolder . '/' . $date, $metadata, $formieTranslations, $siteTranslations);
            } else {
                return $this->_createLocalBackup($subfolder . '/' . $date, $metadata, $formieTranslations, $siteTranslations);
            }
            
            
        } catch (\Exception $e) {
            $this->logError('Failed to create backup', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Create backup using volume storage
     */
    private function _createVolumeBackup(string $backupDir, array $metadata, array $formieTranslations, array $siteTranslations): string
    {
        $fullPath = $this->_volumeBackupPath . '/' . $backupDir;

        try {
            // Ensure directory hierarchy exists
            $parts = explode('/', $fullPath);
            $currentPath = '';
            foreach ($parts as $part) {
                if ($part) {
                    $currentPath = $currentPath ? $currentPath . '/' . $part : $part;
                    if (!$this->_volumeFs->directoryExists($currentPath)) {
                        $this->_volumeFs->createDirectory($currentPath);
                    }
                }
            }

            // Write metadata file
            $this->_volumeFs->write($fullPath . '/metadata.json', Json::encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Save Formie translations
            if (!empty($formieTranslations)) {
                $this->_volumeFs->write(
                    $fullPath . '/formie-translations.json',
                    Json::encode($formieTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            }

            // Save site translations
            if (!empty($siteTranslations)) {
                $this->_volumeFs->write(
                    $fullPath . '/site-translations.json',
                    Json::encode($siteTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            }

            $this->logInfo('Backup created in volume', [
                'path' => $fullPath,
                'formieCount' => count($formieTranslations),
                'siteCount' => count($siteTranslations)
            ]);

            return $fullPath;
        } catch (\Exception $e) {
            $this->logError('Failed to create volume backup', ['error' => $e->getMessage()]);
            throw new \Exception('Failed to create backup in volume. ' . $e->getMessage());
        }
    }

    /**
     * Create backup using local storage
     */
    private function _createLocalBackup(string $backupDir, array $metadata, array $formieTranslations, array $siteTranslations): string
    {
        $basePath = Craft::getAlias(TranslationManager::getInstance()->getSettings()->backupPath);
        $fullPath = $basePath . '/' . $backupDir;

        try {
            // Ensure directory exists
            FileHelper::createDirectory($fullPath);

            // Write metadata file
            FileHelper::writeToFile($fullPath . '/metadata.json', Json::encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Save Formie translations
            if (!empty($formieTranslations)) {
                FileHelper::writeToFile(
                    $fullPath . '/formie-translations.json',
                    Json::encode($formieTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            }

            // Save site translations
            if (!empty($siteTranslations)) {
                FileHelper::writeToFile(
                    $fullPath . '/site-translations.json',
                    Json::encode($siteTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            }

            // Also backup the generated PHP files if they exist
            $this->backupGeneratedFiles($fullPath);

            $this->logInfo('Backup created locally', [
                'path' => $fullPath,
                'formieCount' => count($formieTranslations),
                'siteCount' => count($siteTranslations)
            ]);

            return $fullPath;
        } catch (\Exception $e) {
            $this->logError('Failed to create local backup', ['error' => $e->getMessage()]);
            throw new \Exception('Failed to create backup. Please ensure the backup path is writable: ' . $basePath);
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

        $this->logInfo('Starting volume backup listing', [
            'volumePath' => $this->_volumeBackupPath,
            'useVolume' => $this->_useVolume,
            'fsClass' => $this->_volumeFs ? get_class($this->_volumeFs) : 'null'
        ]);

        try {
            // First check if the base backup directory exists
            if (!$this->_volumeFs->directoryExists($this->_volumeBackupPath)) {
                $this->logInfo('Volume backup base directory does not exist', ['path' => $this->_volumeBackupPath]);
                return $backups;
            }

            // List all subdirectories
            $subfolders = ['manual', 'scheduled', 'imports', 'maintenance', 'other'];

            foreach ($subfolders as $subfolder) {
                $folderPath = $this->_volumeBackupPath . '/' . $subfolder;

                $this->logInfo('Checking subfolder', ['subfolder' => $subfolder, 'path' => $folderPath]);

                if (!$this->_volumeFs->directoryExists($folderPath)) {
                    $this->logInfo('Subfolder does not exist', ['subfolder' => $subfolder]);
                    continue;
                }

                // List contents of subfolder
                $contents = $this->_volumeFs->listContents($folderPath, false);
                $this->logInfo('Subfolder contents', ['subfolder' => $subfolder, 'contentCount' => count($contents)]);

                foreach ($contents as $item) {
                    $this->logInfo('Processing item', ['item' => $item]);

                    // Check for both 'type' key and 'is_dir()' method
                    $isDirectory = false;
                    if (isset($item['type']) && $item['type'] === 'dir') {
                        $isDirectory = true;
                    } elseif (is_object($item) && method_exists($item, 'isDir') && $item->isDir()) {
                        $isDirectory = true;
                    } elseif (is_object($item) && method_exists($item, 'type') && $item->type() === 'directory') {
                        $isDirectory = true;
                    }

                    if ($isDirectory) {
                        // Get the path - handle both array and object formats
                        $itemPath = isset($item['path']) ? $item['path'] : (method_exists($item, 'path') ? $item->path() : null);
                        if (!$itemPath) {
                            continue;
                        }

                        $backupPath = $subfolder . '/' . basename($itemPath);
                        $metadataPath = $this->_volumeBackupPath . '/' . $backupPath . '/metadata.json';

                        $this->logInfo('Found backup directory', [
                            'backupPath' => $backupPath,
                            'metadataPath' => $metadataPath
                        ]);

                        try {
                            if ($this->_volumeFs->fileExists($metadataPath)) {
                                $metadataContent = $this->_volumeFs->read($metadataPath);
                                $metadata = Json::decode($metadataContent);

                                $backup = [
                                    'path' => $backupPath,
                                    'name' => $backupPath,
                                    'timestamp' => $metadata['timestamp'] ?? 0,
                                    'reason' => $metadata['reason'] ?? 'unknown',
                                    'user' => $metadata['user'] ?? 'Unknown',
                                    'translationCount' => $metadata['translationCount'] ?? 0,
                                    'size' => 0, // Size calculation for volumes is complex
                                    'folder' => $subfolder,
                                    'date' => $metadata['date'] ?? '',
                                    'userId' => $metadata['userId'] ?? null,
                                    'formieEnabled' => $metadata['formieEnabled'] ?? false,
                                    'siteEnabled' => $metadata['siteEnabled'] ?? false,
                                    'craftVersion' => $metadata['craftVersion'] ?? '',
                                    'pluginVersion' => $metadata['pluginVersion'] ?? '',
                                ];

                                $backups[] = $backup;
                                $this->logInfo('Added backup to list', ['backup' => $backup]);
                            } else {
                                $this->logInfo('Metadata file does not exist', ['metadataPath' => $metadataPath]);
                            }
                        } catch (\Exception $e) {
                            $this->logError('Error processing backup', [
                                'backupPath' => $backupPath,
                                'error' => $e->getMessage()
                            ]);
                            continue;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logError('Failed to list volume backups', ['error' => $e->getMessage()]);

            // Fallback: try to read directly from filesystem if it's a local volume
            $backups = $this->_tryDirectVolumeBackupListing();
        }

        $this->logInfo('Volume backup listing complete', ['totalBackups' => count($backups)]);

        // Sort by timestamp (newest first)
        usort($backups, function($a, $b) {
            return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
        });

        return $backups;
    }

    /**
     * Fallback method to list volume backups directly from filesystem
     */
    private function _tryDirectVolumeBackupListing(): array
    {
        $backups = [];

        try {
            $settings = TranslationManager::getInstance()->getSettings();
            $volume = Craft::$app->getVolumes()->getVolumeByUid($settings->backupVolumeUid);

            if (!$volume) {
                return $backups;
            }

            $fs = $volume->getFs();

            // Try to get the local path if it's a local volume
            $basePath = null;
            if (property_exists($fs, 'path')) {
                $basePath = Craft::parseEnv($fs->path);
            } elseif (method_exists($fs, 'getRootPath')) {
                $basePath = $fs->getRootPath();
            }

            if (!$basePath || !is_dir($basePath)) {
                $this->logInfo('Cannot determine local path for volume fallback', ['basePath' => $basePath]);
                return $backups;
            }

            $fullBackupPath = rtrim($basePath, '/') . '/' . $this->_volumeBackupPath;

            $this->logInfo('Trying direct volume backup listing', ['fullBackupPath' => $fullBackupPath]);

            if (!is_dir($fullBackupPath)) {
                return $backups;
            }

            // Use the same logic as local backups but with the volume path
            $subfolders = ['manual', 'scheduled', 'imports', 'maintenance', 'other'];

            foreach ($subfolders as $subfolder) {
                $subfolderPath = $fullBackupPath . '/' . $subfolder;
                if (!is_dir($subfolderPath)) {
                    continue;
                }

                $dirs = glob($subfolderPath . '/*', GLOB_ONLYDIR);

                foreach ($dirs as $dir) {
                    $metadataFile = $dir . '/metadata.json';
                    if (file_exists($metadataFile)) {
                        try {
                            $metadata = Json::decode(file_get_contents($metadataFile));
                            $backupName = $subfolder . '/' . basename($dir);

                            $backups[] = [
                                'path' => $backupName,
                                'name' => $backupName,
                                'timestamp' => $metadata['timestamp'] ?? 0,
                                'reason' => $metadata['reason'] ?? 'unknown',
                                'user' => $metadata['user'] ?? 'Unknown',
                                'translationCount' => $metadata['translationCount'] ?? 0,
                                'size' => $this->getDirectorySize($dir),
                                'folder' => $subfolder,
                                'date' => $metadata['date'] ?? '',
                                'userId' => $metadata['userId'] ?? null,
                                'formieEnabled' => $metadata['formieEnabled'] ?? false,
                                'siteEnabled' => $metadata['siteEnabled'] ?? false,
                                'craftVersion' => $metadata['craftVersion'] ?? '',
                                'pluginVersion' => $metadata['pluginVersion'] ?? '',
                            ];

                            $this->logInfo('Added backup via fallback method', ['backup' => $backupName]);
                        } catch (\Exception $e) {
                            $this->logError('Error processing backup in fallback', [
                                'dir' => $dir,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $this->logError('Direct volume backup listing failed', ['error' => $e->getMessage()]);
        }

        return $backups;
    }

    /**
     * Get backups from local storage
     */
    private function _getLocalBackups(): array
    {
        $backupPath = Craft::getAlias(TranslationManager::getInstance()->getSettings()->backupPath);
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
        $this->logInfo('Starting backup restore', ['backup' => $backupName, 'useVolume' => $this->_useVolume]);

        if ($this->_useVolume) {
            return $this->_restoreVolumeBackup($backupName);
        } else {
            return $this->_restoreLocalBackup($backupName);
        }
    }

    /**
     * Restore backup from volume storage
     */
    private function _restoreVolumeBackup(string $backupName): array
    {
        $backupPath = $this->_volumeBackupPath . '/' . $backupName;

        try {
            // Check if backup exists
            if (!$this->_volumeFs->directoryExists($backupPath)) {
                return [
                    'success' => false,
                    'message' => 'Backup not found in volume'
                ];
            }

            // Create a backup of current state before restoring if backups are enabled
            $settings = TranslationManager::getInstance()->getSettings();
            $this->logInfo('Restore: Checking backup settings', ['backupEnabled' => $settings->backupEnabled]);

            $preRestoreBackup = null;
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
            $formiePath = $backupPath . '/formie-translations.json';
            if ($this->_volumeFs->fileExists($formiePath)) {
                $content = $this->_volumeFs->read($formiePath);
                $result = $this->restoreFromContent($content);
                $imported += $result['imported'];
                $errors = array_merge($errors, $result['errors']);
            }

            // Restore site translations
            $sitePath = $backupPath . '/site-translations.json';
            if ($this->_volumeFs->fileExists($sitePath)) {
                $content = $this->_volumeFs->read($sitePath);
                $result = $this->restoreFromContent($content);
                $imported += $result['imported'];
                $errors = array_merge($errors, $result['errors']);
            }

            // Regenerate translation files
            TranslationManager::getInstance()->export->exportAll();

            $this->logInfo('Volume backup restored successfully', [
                'backup' => $backupName,
                'imported' => $imported,
                'errors' => count($errors)
            ]);

            return [
                'success' => true,
                'message' => "Restored {$imported} translations from volume backup",
                'imported' => $imported,
                'errors' => $errors,
                'preRestoreBackup' => $preRestoreBackup
            ];

        } catch (\Exception $e) {
            $this->logError('Failed to restore volume backup', [
                'backup' => $backupName,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to restore backup: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Restore backup from local storage
     */
    private function _restoreLocalBackup(string $backupName): array
    {
        // Handle subfolder structure
        if (str_contains($backupName, '/')) {
            $backupDir = Craft::getAlias(TranslationManager::getInstance()->getSettings()->backupPath) . '/' . $backupName;
        } else {
            // Legacy backup in root
            $backupDir = Craft::getAlias(TranslationManager::getInstance()->getSettings()->backupPath) . '/' . $backupName;
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

            $preRestoreBackup = null;
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

            $this->logInfo('Local backup restored successfully', [
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
            $this->logError('Failed to restore local backup', [
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
     * Restore translations from JSON content string
     */
    private function restoreFromContent(string $content): array
    {
        $imported = 0;
        $errors = [];

        try {
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
            $errors[] = 'Failed to parse content: ' . $e->getMessage();
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

        $this->logInfo('Attempting to delete volume backup', [
            'backup' => $backupName,
            'path' => $backupPath,
            'useVolume' => $this->_useVolume
        ]);

        try {
            if (!$this->_volumeFs->directoryExists($backupPath)) {
                $this->logError('Volume backup directory not found', ['backup' => $backupName, 'path' => $backupPath]);
                return false;
            }

            $this->_volumeFs->deleteDirectory($backupPath);
            $this->logInfo('Deleted volume backup successfully', ['backup' => $backupName]);
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
        // Handle subfolder structure
        if (str_contains($backupName, '/')) {
            $backupDir = Craft::getAlias(TranslationManager::getInstance()->getSettings()->backupPath) . '/' . $backupName;
        } else {
            // Legacy backup in root
            $backupDir = Craft::getAlias(TranslationManager::getInstance()->getSettings()->backupPath) . '/' . $backupName;
        }

        $this->logInfo('Attempting to delete local backup', [
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
            $this->logInfo('Deleted local backup successfully', ['backup' => $backupName]);
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