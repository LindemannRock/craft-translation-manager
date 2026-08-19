<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Service for managing translation backups
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\services;

use Craft;
use craft\base\BaseFsInterface;
use craft\base\Component;
use craft\base\FsInterface;
use craft\base\MissingComponentInterface;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\models\FsListing;
use craft\models\Volume;
use lindemannrock\base\helpers\StorageVolumeHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\helpers\SiteLanguageHelper;
use lindemannrock\translationmanager\TranslationManager;
use Throwable;
use yii\base\UserException;

/**
 * Backup Service
 *
 * Handles automatic backups of translations to @storage/translation-manager/backups/[date]
 *
 * @since 1.0.0
 */
class BackupService extends Component
{
    use LoggingTrait;

    /**
     * Backup subfolders by type.
     */
    private const BACKUP_FOLDERS = ['scheduled', 'imports', 'maintenance', 'manual', 'other'];

    private const VOLUME_BACKUP_ROOT = 'translation-manager/backups';

    private const STORAGE_UNAVAILABLE_MESSAGE = 'The configured backup volume cannot currently be used. Backup operations are unavailable until the volume is restored or the effective setting is changed.';

    /**
     * Initialize the service
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(TranslationManager::$plugin->id);
    }

    /**
     * Get the backup base path
     */
    public function getBackupPath(): string
    {
        if ($this->isUsingVolumeStorage()) {
            return $this->volumeLocationLabel($this->getVolume(), false);
        }

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
        $reasonText = $this->getDisplayReason($reason ?? 'manual');
        $useVolume = $this->isUsingVolumeStorage();
        $storageType = $useVolume ? 'volume' : 'local';
        $this->logInfo("Creating backup", [
            'reason' => $reasonText,
            'storageType' => $storageType,
        ]);

        try {
            $subfolder = $this->getFolderForReason($reason);

            // Create timestamp-based directory name
            $timestamp = DateTimeHelper::currentTimeStamp();
            $date = date('Y-m-d_H-i-s', $timestamp);

            // Get ALL translations for backup (including unused, pending, translated, approved)
            $translations = TranslationManager::getInstance()->translations->getTranslations([
                'status' => 'all', // Include all statuses
                'allSites' => true, // Include all sites
                'type' => 'all', // Include both formie and site translations
            ]);

            if (empty($translations) && $reason !== 'Before Restore') {
                $this->logInfo('No translations to backup - skipping backup creation');
                return null;
            }

            // Create metadata
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

            // Use volume storage if configured
            if ($useVolume) {
                return $this->_createVolumeBackup($subfolder . '/' . $date, $metadata, $formieTranslations, $siteTranslations, $this->getVolume());
            } else {
                return $this->_createLocalBackup($subfolder . '/' . $date, $metadata, $formieTranslations, $siteTranslations);
            }
        } catch (UserException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logError('Failed to create backup', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create backup using volume storage
     */
    private function _createVolumeBackup(
        string $backupDir,
        array $metadata,
        array $formieTranslations,
        array $siteTranslations,
        Volume $volume,
    ): string {
        $fullPath = self::VOLUME_BACKUP_ROOT . '/' . $backupDir;

        try {
            // Ensure directory hierarchy exists using Craft FS API
            $parts = explode('/', $fullPath);
            $currentPath = '';
            foreach ($parts as $part) {
                if ($part) {
                    $currentPath = $currentPath ? $currentPath . '/' . $part : $part;
                    if (!$volume->directoryExists($currentPath)) {
                        $volume->createDirectory($currentPath);
                    }
                }
            }

            // Encode JSON content first
            $formieContent = !empty($formieTranslations)
                ? Json::encode($formieTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : '';
            $siteContent = !empty($siteTranslations)
                ? Json::encode($siteTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : '';

            // Calculate checksum for integrity verification
            $checksum = hash('sha256', $formieContent . $siteContent);
            $metadata['checksum'] = $checksum;
            $metadata['checksumAlgorithm'] = 'sha256';

            $this->logInfo('Backup checksum calculated', [
                'checksum' => substr($checksum, 0, 16) . '...',
                'formieSize' => strlen($formieContent),
                'siteSize' => strlen($siteContent),
            ]);

            // Write metadata file
            $volume->write($fullPath . '/metadata.json', Json::encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Save Formie translations
            if (!empty($formieTranslations)) {
                $volume->write($fullPath . '/formie-translations.json', $formieContent);
            }

            // Save site translations
            if (!empty($siteTranslations)) {
                $volume->write($fullPath . '/site-translations.json', $siteContent);
            }

            // Also backup the generated PHP files if they exist
            $this->backupGeneratedFilesToVolume($fullPath, $volume);

            $formieCount = count($formieTranslations);
            $siteCount = count($siteTranslations);
            $this->logInfo("Backup created in volume", [
                'path' => $fullPath,
                'formieCount' => $formieCount,
                'siteCount' => $siteCount,
            ]);

            return $fullPath;
        } catch (Throwable $e) {
            $this->logError('Failed to create volume backup', ['error' => $e->getMessage()]);
            $this->throwStorageUnavailable('create', $e);
        }
    }

    /**
     * Create backup using local storage
     */
    private function _createLocalBackup(string $backupDir, array $metadata, array $formieTranslations, array $siteTranslations): string
    {
        $basePath = TranslationManager::getInstance()->getSettings()->getBackupPath();
        $fullPath = $basePath . '/' . $backupDir;

        try {
            // Ensure directory exists
            FileHelper::createDirectory($fullPath);

            // Encode JSON content first
            $formieContent = !empty($formieTranslations)
                ? Json::encode($formieTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : '';
            $siteContent = !empty($siteTranslations)
                ? Json::encode($siteTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : '';

            // Calculate checksum for integrity verification
            $checksum = hash('sha256', $formieContent . $siteContent);
            $metadata['checksum'] = $checksum;
            $metadata['checksumAlgorithm'] = 'sha256';

            $this->logInfo('Backup checksum calculated', [
                'checksum' => substr($checksum, 0, 16) . '...',
                'formieSize' => strlen($formieContent),
                'siteSize' => strlen($siteContent),
            ]);

            // Write metadata file
            FileHelper::writeToFile($fullPath . '/metadata.json', Json::encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Save Formie translations
            if (!empty($formieTranslations)) {
                FileHelper::writeToFile($fullPath . '/formie-translations.json', $formieContent);
            }

            // Save site translations
            if (!empty($siteTranslations)) {
                FileHelper::writeToFile($fullPath . '/site-translations.json', $siteContent);
            }

            // Also backup the generated PHP files if they exist
            $this->backupGeneratedFiles($fullPath);

            $formieCount = count($formieTranslations);
            $siteCount = count($siteTranslations);
            $this->logInfo("Backup created locally", [
                'path' => $fullPath,
                'formieCount' => $formieCount,
                'siteCount' => $siteCount,
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
        $generationPath = $settings->getGenerationPath();

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
            $sourcePath = $generationPath . '/' . $file;
            if (file_exists($sourcePath)) {
                $destPath = $phpDir . '/' . str_replace('/', '_', $file);
                copy($sourcePath, $destPath);
                $this->logInfo("Backed up PHP file", ['file' => $file]);
            }
        }
    }

    /**
     * Backup generated PHP files to volume storage
     */
    private function backupGeneratedFilesToVolume(string $backupPath, BaseFsInterface $storage): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $generationPath = $settings->getGenerationPath();

        // Get all site languages for backup
        $sites = TranslationManager::getInstance()->getAllowedSites();
        $filesToBackup = [];

        foreach ($sites as $site) {
            $language = $site->language;
            $filesToBackup[] = $language . '/formie.php';
            $filesToBackup[] = $language . '/' . $settings->translationCategory . '.php';
        }

        // Create php-files directory in volume
        $phpDir = $backupPath . '/php-files';
        if (!$storage->directoryExists($phpDir)) {
            $storage->createDirectory($phpDir);
        }

        foreach ($filesToBackup as $file) {
            $sourcePath = $generationPath . '/' . $file;
            if (file_exists($sourcePath)) {
                $destPath = $phpDir . '/' . str_replace('/', '_', $file);
                $content = file_get_contents($sourcePath);
                $storage->write($destPath, $content);
                $this->logInfo("Backed up PHP file", ['file' => $file]);
            }
        }
    }

    /**
     * Return whether the effective settings select a Craft volume.
     *
     * @since 5.35.0
     */
    public function isUsingVolumeStorage(): bool
    {
        $settings = TranslationManager::getInstance()->getSettings();
        if (trim((string)$settings->backupVolumeUid) === '') {
            return false;
        }

        $this->getVolume();
        return true;
    }

    /**
     * Return the files that belong in a backup download.
     *
     * @return array<string, string> ZIP member path => file contents
     * @since 5.35.0
     */
    public function getDownloadFiles(string $backupName): array
    {
        if (!$this->isValidBackupName($backupName)) {
            return [];
        }

        if ($this->isUsingVolumeStorage()) {
            try {
                $storage = $this->resolveVolumeBackupStorage($backupName);
                if ($storage === null) {
                    return [];
                }

                $files = [];
                foreach (['metadata.json', 'formie-translations.json', 'site-translations.json'] as $filename) {
                    $path = self::VOLUME_BACKUP_ROOT . '/' . $backupName . '/' . $filename;
                    if ($storage->fileExists($path)) {
                        $files[$filename] = $storage->read($path);
                    }
                }

                return $files;
            } catch (Throwable $e) {
                $this->throwStorageUnavailable('download', $e);
            }
        }

        $backupDir = rtrim(TranslationManager::getInstance()->getSettings()->getBackupPath(), '/') . '/' . $backupName;
        if (!is_dir($backupDir)) {
            return [];
        }

        $files = [];
        foreach (FileHelper::findFiles($backupDir) as $file) {
            $content = file_get_contents($file);
            if (is_string($content)) {
                $files[str_replace($backupDir . '/', '', $file)] = $content;
            }
        }

        return $files;
    }

    private function getVolume(): Volume
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $volumeUid = trim((string)$settings->backupVolumeUid);
        if ($volumeUid === '') {
            throw new \LogicException('Volume storage was requested without an effective volume UID.');
        }

        try {
            $volumeErrors = StorageVolumeHelper::validateVolume($volumeUid);
            if ($volumeErrors !== []) {
                throw new \RuntimeException('Backup volume failed validation: ' . implode('; ', $volumeErrors));
            }

            $volume = Craft::$app->getVolumes()->getVolumeByUid($volumeUid);
            if (!$volume instanceof Volume) {
                throw new \RuntimeException('Configured backup volume could not be resolved.');
            }

            // Resolution classification only: no filesystem operation is performed.
            $fs = $volume->getFs();
            if (!$fs instanceof FsInterface || $fs instanceof MissingComponentInterface) {
                throw new \RuntimeException('Configured backup volume filesystem is unavailable.');
            }

            return $volume;
        } catch (Throwable $e) {
            $this->throwStorageUnavailable('resolve', $e, $volumeUid);
        }
    }

    /**
     * Resolve the underlying filesystem used by historical backups written at
     * the exact filesystem-root prefix before Craft volume wrapper support.
     */
    private function getHistoricalVolumeFs(Volume $volume): FsInterface
    {
        try {
            $fs = $volume->getFs();
            if (!$fs instanceof FsInterface || $fs instanceof MissingComponentInterface) {
                throw new \RuntimeException('Configured backup volume filesystem is unavailable.');
            }

            return $fs;
        } catch (Throwable $e) {
            $this->throwStorageUnavailable('historical-resolve', $e);
        }
    }

    /**
     * Resolve one volume backup, preferring the canonical Craft volume wrapper
     * over the exact historical filesystem-root prefix.
     */
    private function resolveVolumeBackupStorage(string $backupName): ?BaseFsInterface
    {
        if (!$this->isValidBackupName($backupName)) {
            return null;
        }

        $volume = $this->getVolume();
        $path = self::VOLUME_BACKUP_ROOT . '/' . $backupName;
        if ($volume->directoryExists($path)) {
            return $volume;
        }

        if (!$this->hasSeparateHistoricalLocation($volume)) {
            return null;
        }

        $historicalFs = $this->getHistoricalVolumeFs($volume);
        return $historicalFs->directoryExists($path) ? $historicalFs : null;
    }

    private function hasSeparateHistoricalLocation(Volume $volume): bool
    {
        return trim($volume->getSubpath(), '/') !== '';
    }

    private function volumeLocationLabel(Volume $volume, bool $historical): string
    {
        $path = self::VOLUME_BACKUP_ROOT;
        if (!$historical) {
            $subpath = trim($volume->getSubpath(), '/');
            if ($subpath !== '') {
                $path = $subpath . '/' . $path;
            }
        }

        return 'Volume: ' . (string)$volume->name . '/' . $path;
    }

    private function isValidBackupName(string $backupName): bool
    {
        $folder = null;
        $timestamp = $backupName;
        if (str_contains($backupName, '/')) {
            [$folder, $timestamp] = explode('/', $backupName, 2);
            if (!in_array($folder, self::BACKUP_FOLDERS, true)) {
                return false;
            }
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/', $timestamp) === 1;
    }

    private function throwStorageUnavailable(string $operation, Throwable $e, ?string $volumeUid = null): never
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $this->logError('Backup volume operation failed', [
            'operation' => $operation,
            'backupVolumeUid' => $volumeUid ?? $settings->backupVolumeUid,
            'error' => $e->getMessage(),
        ]);

        $message = Craft::t('translation-manager', self::STORAGE_UNAVAILABLE_MESSAGE);
        if ($e instanceof UserException && $e->getMessage() === $message) {
            throw $e;
        }

        throw new UserException($message, previous: $e);
    }

    /**
     * Get all available backups
     *
     * @return array Array of backup info sorted by date (newest first)
     */
    public function getBackups(): array
    {
        if ($this->isUsingVolumeStorage()) {
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
        $volume = $this->getVolume();
        /** @var array<string, array<string, mixed>> $backupsByName */
        $backupsByName = [];
        try {
            $this->collectVolumeBackups(
                $backupsByName,
                $volume,
                $this->volumeLocationLabel($volume, false),
                'canonical-volume',
            );

            if ($this->hasSeparateHistoricalLocation($volume)) {
                $this->collectVolumeBackups(
                    $backupsByName,
                    $this->getHistoricalVolumeFs($volume),
                    $this->volumeLocationLabel($volume, true),
                    'historical-volume',
                );
            }
        } catch (Throwable $e) {
            $this->logError('Failed to list volume backups', ['error' => $e->getMessage()]);
            $this->throwStorageUnavailable('list', $e);
        }

        $backups = array_values($backupsByName);
        usort($backups, function($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });

        return $backups;
    }

    /**
     * Collect backups beneath one bounded storage root. Canonical entries are
     * collected first, so they retain precedence over historical duplicates.
     *
     * @param array<string, array<string, mixed>> $backupsByName
     */
    private function collectVolumeBackups(
        array &$backupsByName,
        BaseFsInterface $storage,
        string $location,
        string $storageType,
    ): void {
        if (!$storage->directoryExists(self::VOLUME_BACKUP_ROOT)) {
            return;
        }

        foreach ($storage->getFileList(self::VOLUME_BACKUP_ROOT, false) as $listing) {
            if (!$listing instanceof FsListing || !$listing->getIsDir()) {
                continue;
            }

            $backupName = $listing->getBasename();
            if (in_array($backupName, self::BACKUP_FOLDERS, true)
                || !$this->isValidBackupName($backupName)
                || isset($backupsByName[$backupName])) {
                continue;
            }
            $this->addVolumeBackup($backupsByName, $backupName, $storage, $location, $storageType);
        }

        foreach (self::BACKUP_FOLDERS as $folder) {
            $folderPath = self::VOLUME_BACKUP_ROOT . '/' . $folder;
            if (!$storage->directoryExists($folderPath)) {
                continue;
            }

            foreach ($storage->getFileList($folderPath, false) as $listing) {
                if (!$listing instanceof FsListing || !$listing->getIsDir()) {
                    continue;
                }

                $backupName = $folder . '/' . $listing->getBasename();
                if (!$this->isValidBackupName($backupName) || isset($backupsByName[$backupName])) {
                    continue;
                }
                $this->addVolumeBackup($backupsByName, $backupName, $storage, $location, $storageType);
            }
        }
    }

    /** @param array<string, array<string, mixed>> $backupsByName */
    private function addVolumeBackup(
        array &$backupsByName,
        string $backupName,
        BaseFsInterface $storage,
        string $location,
        string $storageType,
    ): void {
        $metadataPath = self::VOLUME_BACKUP_ROOT . '/' . $backupName . '/metadata.json';
        if (!$storage->fileExists($metadataPath)) {
            return;
        }

        $metadata = Json::decode($storage->read($metadataPath));
        if (!is_array($metadata)) {
            return;
        }

        $metadata['path'] = self::VOLUME_BACKUP_ROOT . '/' . $backupName;
        $metadata['name'] = $backupName;
        $metadata['size'] = $this->_calculateVolumeBackupSize(self::VOLUME_BACKUP_ROOT . '/' . $backupName, $storage);
        $metadata['folder'] = str_contains($backupName, '/') ? explode('/', $backupName, 2)[0] : 'legacy';
        $metadata['storageLocation'] = $location;
        $metadata['storageType'] = $storageType;
        $backupsByName[$backupName] = $metadata;
    }

    /**
     * Get backups from local storage
     */
    private function _getLocalBackups(): array
    {
        $backupPath = TranslationManager::getInstance()->getSettings()->getBackupPath();
        $this->logInfo("Starting local backup listing", ['path' => $backupPath]);
        $backups = [];

        if (!is_dir($backupPath)) {
            return $backups;
        }

        // First check for legacy backups in root (backward compatibility)
        $rootDirs = FileHelper::findDirectories($backupPath, [
            'recursive' => false,
        ]);

        foreach ($rootDirs as $dir) {
            $dirName = basename($dir);
            // Skip if it's one of our new subfolders
            if (in_array($dirName, self::BACKUP_FOLDERS, true)) {
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
                    $metadata['storageLocation'] = $backupPath;
                    $metadata['storageType'] = 'local';
                    $backups[] = $metadata;
                } catch (\Exception $e) {
                    $this->logError('Failed to read backup metadata', [
                        'dir' => $dir,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Now scan each subfolder
        foreach (self::BACKUP_FOLDERS as $subfolder) {
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
                        $metadata['storageLocation'] = $backupPath;
                        $metadata['storageType'] = 'local';
                        $backups[] = $metadata;
                    } catch (\Exception $e) {
                        $this->logError('Failed to read backup metadata', [
                            'dir' => $dir,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        // Sort by timestamp descending (newest first)
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        $backupCount = count($backups);
        $this->logInfo("Local backup listing complete", ['backupCount' => $backupCount]);

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
        $useVolume = $this->isUsingVolumeStorage();
        $storageType = $useVolume ? 'volume' : 'local';
        $this->logInfo("Starting backup restore", [
            'backup' => $backupName,
            'storageType' => $storageType,
        ]);

        if ($useVolume) {
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
        $backupPath = self::VOLUME_BACKUP_ROOT . '/' . $backupName;

        try {
            $storage = $this->resolveVolumeBackupStorage($backupName);

            // Check if backup exists
            if ($storage === null) {
                return [
                    'success' => false,
                    'message' => 'Backup not found in volume',
                ];
            }

            // Read and validate metadata with checksum
            $metadataPath = $backupPath . '/metadata.json';
            if (!$storage->fileExists($metadataPath)) {
                return [
                    'success' => false,
                    'message' => 'Backup metadata not found',
                ];
            }

            $metadataContent = $storage->read($metadataPath);
            $metadata = Json::decode($metadataContent);

            // Read JSON files
            $formieContent = '';
            $formiePath = $backupPath . '/formie-translations.json';
            if ($storage->fileExists($formiePath)) {
                $formieContent = $storage->read($formiePath);
            }

            $siteContent = '';
            $sitePath = $backupPath . '/site-translations.json';
            if ($storage->fileExists($sitePath)) {
                $siteContent = $storage->read($sitePath);
            }

            // Validate checksum if present
            if (isset($metadata['checksum'])) {
                $expectedChecksum = $metadata['checksum'];
                $actualChecksum = hash('sha256', $formieContent . $siteContent);

                if ($expectedChecksum !== $actualChecksum) {
                    $this->logError('Backup checksum validation failed', [
                        'backup' => $backupName,
                        'expected' => substr($expectedChecksum, 0, 16) . '...',
                        'actual' => substr($actualChecksum, 0, 16) . '...',
                    ]);

                    return [
                        'success' => false,
                        'message' => 'Backup integrity check failed. The backup files may have been modified or corrupted.',
                    ];
                }

                $this->logInfo('Backup checksum validated successfully', [
                    'backup' => $backupName,
                    'checksum' => substr($actualChecksum, 0, 16) . '...',
                ]);
            } else {
                $this->logError('Backup checksum is missing', [
                    'backup' => $backupName,
                ]);

                return [
                    'success' => false,
                    'message' => 'Backup integrity check failed. The backup files may have been modified or corrupted.',
                ];
            }

            // Create a backup of current state before restoring if backups are enabled
            $settings = TranslationManager::getInstance()->getSettings();
            $backupStatus = $settings->backupEnabled ? 'enabled' : 'disabled';
            $this->logInfo("Restore: Checking backup settings", ['backupsEnabled' => $backupStatus]);

            $preRestoreBackup = null;
            if ($settings->backupEnabled) {
                $this->logInfo('Restore: Creating pre-restore backup');
                $preRestoreBackup = $this->createBackup('before_restore');
                if (!$preRestoreBackup) {
                    $this->logWarning('Failed to create pre-restore backup, continuing with restore');
                } else {
                    $this->logInfo("Restore: Pre-restore backup created", ['path' => $preRestoreBackup]);
                }
            } else {
                $this->logInfo('Restore: Skipping pre-restore backup (backups disabled)');
            }

            // Delete existing translations
            TranslationManager::getInstance()->translations->deleteAllTranslations();

            $imported = 0;
            $errors = [];

            // Restore Formie translations
            if (!empty($formieContent)) {
                $result = $this->restoreFromContent($formieContent);
                $imported += $result['imported'];
                $errors = array_merge($errors, $result['errors']);
            }

            // Restore site translations
            if (!empty($siteContent)) {
                $result = $this->restoreFromContent($siteContent);
                $imported += $result['imported'];
                $errors = array_merge($errors, $result['errors']);
            }

            // Regenerate translation files
            TranslationManager::getInstance()->generate->generateAll();

            $errorCount = count($errors);
            $this->logInfo("Volume backup restored successfully", [
                'backup' => $backupName,
                'imported' => $imported,
                'errorCount' => $errorCount,
            ]);

            return [
                'success' => true,
                'message' => "Restored {$imported} translations from volume backup",
                'imported' => $imported,
                'errors' => $errors,
                'preRestoreBackup' => $preRestoreBackup,
            ];
        } catch (Throwable $e) {
            $this->logError('Failed to restore volume backup', [
                'backup' => $backupName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => Craft::t('translation-manager', self::STORAGE_UNAVAILABLE_MESSAGE),
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
            $backupDir = TranslationManager::getInstance()->getSettings()->getBackupPath() . '/' . $backupName;
        } else {
            // Legacy backup in root
            $backupDir = TranslationManager::getInstance()->getSettings()->getBackupPath() . '/' . $backupName;
        }

        if (!is_dir($backupDir)) {
            return [
                'success' => false,
                'message' => 'Backup not found',
            ];
        }

        try {
            // Read and validate metadata with checksum
            $metadataFile = $backupDir . '/metadata.json';
            if (!file_exists($metadataFile)) {
                return [
                    'success' => false,
                    'message' => 'Backup metadata not found',
                ];
            }

            $metadata = Json::decode(file_get_contents($metadataFile));

            // Read JSON files
            $formieContent = '';
            $formieFile = $backupDir . '/formie-translations.json';
            if (file_exists($formieFile)) {
                $formieContent = file_get_contents($formieFile);
            }

            $siteContent = '';
            $siteFile = $backupDir . '/site-translations.json';
            if (file_exists($siteFile)) {
                $siteContent = file_get_contents($siteFile);
            }

            // Validate checksum if present
            if (isset($metadata['checksum'])) {
                $expectedChecksum = $metadata['checksum'];
                $actualChecksum = hash('sha256', $formieContent . $siteContent);

                if ($expectedChecksum !== $actualChecksum) {
                    $this->logError('Backup checksum validation failed', [
                        'backup' => $backupName,
                        'expected' => substr($expectedChecksum, 0, 16) . '...',
                        'actual' => substr($actualChecksum, 0, 16) . '...',
                    ]);

                    return [
                        'success' => false,
                        'message' => 'Backup integrity check failed. The backup files may have been modified or corrupted.',
                    ];
                }

                $this->logInfo('Backup checksum validated successfully', [
                    'backup' => $backupName,
                    'checksum' => substr($actualChecksum, 0, 16) . '...',
                ]);
            } else {
                $this->logError('Backup checksum is missing', [
                    'backup' => $backupName,
                ]);

                return [
                    'success' => false,
                    'message' => 'Backup integrity check failed. The backup files may have been modified or corrupted.',
                ];
            }

            // Create a backup of current state before restoring if backups are enabled
            $settings = TranslationManager::getInstance()->getSettings();
            $backupStatus = $settings->backupEnabled ? 'enabled' : 'disabled';
            $this->logInfo("Restore: Checking backup settings", ['backupsEnabled' => $backupStatus]);

            $preRestoreBackup = null;
            if ($settings->backupEnabled) {
                $this->logInfo('Restore: Creating pre-restore backup');
                $preRestoreBackup = $this->createBackup('before_restore');
                if (!$preRestoreBackup) {
                    $this->logWarning('Failed to create pre-restore backup, continuing with restore');
                } else {
                    $this->logInfo("Restore: Pre-restore backup created", ['path' => $preRestoreBackup]);
                }
            } else {
                $this->logInfo('Restore: Skipping pre-restore backup (backups disabled)');
            }

            // Delete existing translations
            TranslationManager::getInstance()->translations->deleteAllTranslations();

            $imported = 0;
            $errors = [];

            // Restore Formie translations
            if (!empty($formieContent)) {
                $result = $this->restoreFromContent($formieContent);
                $imported += $result['imported'];
                $errors = array_merge($errors, $result['errors']);
            }

            // Restore site translations
            if (!empty($siteContent)) {
                $result = $this->restoreFromContent($siteContent);
                $imported += $result['imported'];
                $errors = array_merge($errors, $result['errors']);
            }

            // Regenerate translation files
            TranslationManager::getInstance()->generate->generateAll();

            $errorCount = count($errors);
            $this->logInfo("Local backup restored successfully", [
                'backup' => $backupName,
                'imported' => $imported,
                'errorCount' => $errorCount,
            ]);

            return [
                'success' => true,
                'message' => "Restored {$imported} translations from backup",
                'imported' => $imported,
                'errors' => $errors,
                'preRestoreBackup' => $preRestoreBackup,
            ];
        } catch (\Exception $e) {
            $this->logError('Failed to restore local backup', [
                'backup' => $backupName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage(),
            ];
        }
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

                    $translation->translationKey = $data['translationKey'] ?? '';
                    $translation->translation = $data['translation'] ?? '';

                    // Restore language with fallbacks for backward compatibility
                    $language = $data['language']
                        ?? $data['siteLanguage']
                        ?? Craft::$app->getSites()->getPrimarySite()->language;
                    $translation->language = $language;

                    // Derive siteId from language if missing, to maintain consistency
                    if (isset($data['siteId'])) {
                        $translation->siteId = $data['siteId'];
                    } else {
                        $translation->siteId = SiteLanguageHelper::getSiteIdForLanguage($language);
                    }

                    // Restore category with fallbacks for backward compatibility
                    $context = $data['context'] ?? '';
                    $translation->category = $data['category']
                        ?? (str_starts_with($context, 'formie.') ? 'formie' : TranslationManager::getInstance()->getSettings()->getPrimaryCategory());

                    $translation->status = $data['status'];
                    $translation->usageCount = $data['usageCount'] ?? 1;
                    $translation->lastUsed = Db::prepareDateForDb(DateTimeHelper::toDateTime($data['lastUsed'] ?? time()));
                    $translation->dateCreated = Db::prepareDateForDb(DateTimeHelper::toDateTime($data['dateCreated'] ?? time()));
                    $translation->dateUpdated = Db::prepareDateForDb(DateTimeHelper::toDateTime($data['dateUpdated'] ?? time()));
                    $translation->uid = $data['uid'] ?? \craft\helpers\StringHelper::UUID();

                    if ($translation->save()) {
                        $imported++;
                    } else {
                        $errors[] = 'Failed to import: ' . ($data['translationKey'] ?? 'Unknown');
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
            'errors' => $errors,
        ];
    }

    /**
     * Delete a backup
     */
    public function deleteBackup(string $backupName): bool
    {
        if ($this->isUsingVolumeStorage()) {
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
        $backupPath = self::VOLUME_BACKUP_ROOT . '/' . $backupName;

        $this->logInfo("Attempting to delete volume backup", [
            'backup' => $backupName,
            'path' => $backupPath,
        ]);

        try {
            $storage = $this->resolveVolumeBackupStorage($backupName);
            if ($storage === null) {
                $this->logError('Volume backup directory not found', ['backup' => $backupName, 'path' => $backupPath]);
                return false;
            }

            $storage->deleteDirectory($backupPath);
            $this->logInfo("Deleted volume backup successfully", ['backup' => $backupName]);
            return true;
        } catch (Throwable $e) {
            $this->logError('Failed to delete volume backup', [
                'backup' => $backupName,
                'error' => $e->getMessage(),
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
            $backupDir = TranslationManager::getInstance()->getSettings()->getBackupPath() . '/' . $backupName;
        } else {
            // Legacy backup in root
            $backupDir = TranslationManager::getInstance()->getSettings()->getBackupPath() . '/' . $backupName;
        }

        $exists = is_dir($backupDir) ? 'exists' : 'missing';
        $this->logInfo("Attempting to delete local backup", [
            'backup' => $backupName,
            'path' => $backupDir,
            'exists' => $exists,
        ]);

        if (!is_dir($backupDir)) {
            $this->logError('Backup directory not found', ['backup' => $backupName, 'path' => $backupDir]);
            return false;
        }

        try {
            FileHelper::removeDirectory($backupDir);
            $this->logInfo("Deleted local backup successfully", ['backup' => $backupName]);
            return true;
        } catch (\Exception $e) {
            $this->logError('Failed to delete local backup', [
                'backup' => $backupName,
                'error' => $e->getMessage(),
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
                'retentionDays' => $retentionDays,
            ]);
        }

        return $deleted;
    }

    /**
     * Calculate backup size for volume storage
     */
    private function _calculateVolumeBackupSize(string $backupPath, BaseFsInterface $storage): int
    {
        $size = 0;

        try {
            // Try to get file sizes for common backup files
            $files = ['metadata.json', 'formie-translations.json', 'site-translations.json'];

            foreach ($files as $file) {
                $filePath = $backupPath . '/' . $file;
                if ($storage->fileExists($filePath)) {
                    try {
                        $size += $storage->getFileSize($filePath);
                    } catch (Throwable $e) {
                        // If getFileSize fails, estimate based on content
                        try {
                            $content = $storage->read($filePath);
                            $size += strlen($content);
                        } catch (Throwable $e2) {
                            // Skip if we can't read the file
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $this->logWarning('Could not calculate volume backup size', [
                'backupPath' => $backupPath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $size;
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

    /**
     * Map backup reason to storage folder.
     */
    private function getFolderForReason(?string $reason): string
    {
        $reason = strtolower((string)$reason);

        if (str_starts_with($reason, 'before_cleanup') || str_starts_with($reason, 'before_delete')) {
            return 'maintenance';
        }

        return match ($reason) {
            'import', 'before_import', 'before_php_import' => 'imports',
            'restore', 'before_restore', 'maintenance' => 'maintenance',
            'scheduled' => 'scheduled',
            'manual', 'console' => 'manual',
            default => 'other',
        };
    }

    /**
     * Convert internal reason code to user-friendly display text.
     *
     * The default branch handles dynamic reason values (e.g. user
     * category names from `before_delete_{$category}`) — those can't
     * be statically translated, so we return a humanized English
     * fallback rather than wrapping in `Craft::t()` (which would
     * silently no-op since translation extraction tools can't see
     * the dynamic key anyway).
     */
    private function getDisplayReason(string $reason): string
    {
        return match ($reason) {
            'manual' => Craft::t('translation-manager', 'Manual'),
            'before_import' => Craft::t('translation-manager', 'Before Import'),
            'before_php_import' => Craft::t('translation-manager', 'Before PHP Import'),
            'before_restore' => Craft::t('translation-manager', 'Before Restore'),
            'scheduled' => Craft::t('translation-manager', 'Scheduled'),
            'before_delete_all' => Craft::t('translation-manager', 'Before Delete All'),
            'before_delete_formie' => Craft::t('translation-manager', 'Before Delete Formie'),
            'before_delete_site' => Craft::t('translation-manager', 'Before Delete Site'),
            'before_cleanup' => Craft::t('translation-manager', 'Before Cleanup'),
            'before_cleanup_all' => Craft::t('translation-manager', 'Before Cleanup All'),
            'before_cleanup_formie' => Craft::t('translation-manager', 'Before Cleanup Formie'),
            'before_cleanup_site' => Craft::t('translation-manager', 'Before Cleanup Site'),
            'before_cleanup_languages' => Craft::t('translation-manager', 'Before Cleanup Languages'),
            'before_cleanup_categories' => Craft::t('translation-manager', 'Before Cleanup Categories'),
            'before_delete' => Craft::t('translation-manager', 'Before Delete'),
            default => ucfirst(str_replace('_', ' ', $reason)),
        };
    }
}
