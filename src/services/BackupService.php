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

    private const SIZE_STREAM_CHUNK_BYTES = 8192;

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
     * @return string|null The completed backup directory path, or null when there are no translations to back up
     * @throws \Throwable when backup creation cannot complete safely
     */
    public function createBackup(?string $reason = null): ?string
    {
        $reasonText = $this->getDisplayReason($reason ?? 'manual');

        try {
            $subfolder = $this->getFolderForReason($reason);

            $timestamp = $this->createBackupTimestamp();
            $date = date('Y-m-d_H-i-s', $timestamp);

            // Get ALL translations for backup (including unused, pending, translated, approved)
            $translations = TranslationManager::getInstance()->translations->getTranslations([
                'status' => 'all', // Include all statuses
                'allSites' => true, // Include all sites
                'type' => 'all', // Include both formie and site translations
            ]);

            if (empty($translations)) {
                $this->logInfo('No translations to backup - skipping backup creation');
                return null;
            }

            $useVolume = $this->isUsingVolumeStorage();
            $storageType = $useVolume ? 'volume' : 'local';
            $this->logInfo("Creating backup", [
                'reason' => $reasonText,
                'storageType' => $storageType,
            ]);

            $backupId = $this->createBackupEntropy();
            $backupName = $date . '_' . $backupId;

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
                'backupId' => $backupId,
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
                return $this->createVolumeBackup($subfolder . '/' . $backupName, $metadata, $formieTranslations, $siteTranslations, $this->getVolume());
            } else {
                return $this->createLocalBackup($subfolder . '/' . $backupName, $metadata, $formieTranslations, $siteTranslations);
            }
        } catch (UserException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logError('Failed to create backup', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to create translation backup: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Return the timestamp used by one creation attempt.
     */
    protected function createBackupTimestamp(): int
    {
        return DateTimeHelper::currentTimeStamp();
    }

    /**
     * Return a collision-resistant identifier owned by one creation attempt.
     */
    protected function createBackupEntropy(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Create a backup using canonical Craft volume storage.
     */
    private function createVolumeBackup(
        string $backupDir,
        array $metadata,
        array $formieTranslations,
        array $siteTranslations,
        Volume $volume,
    ): string {
        $finalPath = self::VOLUME_BACKUP_ROOT . '/' . $backupDir;
        $parentPath = dirname($finalPath);
        $stagingPath = $parentPath . '/.' . basename($finalPath) . '.staging-' . $this->createBackupEntropy();
        $backupId = (string)$metadata['backupId'];
        $finalOwned = false;

        try {
            $this->ensureVolumeDirectory($volume, $parentPath);
            if ($volume->directoryExists($finalPath)) {
                throw new \RuntimeException("A completed backup already exists at {$finalPath}.");
            }
            if ($volume->directoryExists($stagingPath)) {
                throw new \RuntimeException("The owned backup staging path already exists: {$stagingPath}.");
            }

            $volume->createDirectory($stagingPath);
            if (!$volume->directoryExists($stagingPath)) {
                throw new \RuntimeException("Unable to create the owned backup staging directory: {$stagingPath}.");
            }

            $files = $this->buildBackupFiles($metadata, $formieTranslations, $siteTranslations);
            foreach ($files as $relativePath => $content) {
                $directory = dirname($stagingPath . '/' . $relativePath);
                $this->ensureVolumeDirectory($volume, $directory);
                $this->writeVolumeBackupFile($volume, $stagingPath . '/' . $relativePath, $content);
            }

            $this->validateVolumeBackupSnapshot($volume, $stagingPath, $files);

            if ($volume->directoryExists($finalPath)) {
                throw new \RuntimeException("A completed backup appeared before promotion at {$finalPath}.");
            }
            $this->promoteVolumeBackupDirectory($volume, $stagingPath, basename($finalPath));
            if ($volume->directoryExists($stagingPath) || !$volume->directoryExists($finalPath)) {
                throw new \RuntimeException("Backup promotion did not complete at {$finalPath}.");
            }
            $finalOwned = true;
            $this->validateVolumeBackupSnapshot($volume, $finalPath, $files);

            $formieCount = count($formieTranslations);
            $siteCount = count($siteTranslations);
            $this->logInfo("Backup created in volume", [
                'path' => $finalPath,
                'formieCount' => $formieCount,
                'siteCount' => $siteCount,
            ]);

            return $finalPath;
        } catch (Throwable $e) {
            $this->removeOwnedVolumeDirectory($volume, $stagingPath);
            if ($finalOwned || $this->volumeDirectoryBelongsToAttempt($volume, $finalPath, $backupId)) {
                $this->removeOwnedVolumeDirectory($volume, $finalPath);
            }
            $this->logError('Failed to create volume backup', ['error' => $e->getMessage()]);
            $this->throwStorageUnavailable('create', $e);
        }
    }

    /**
     * Create backup using local storage
     */
    private function createLocalBackup(string $backupDir, array $metadata, array $formieTranslations, array $siteTranslations): string
    {
        $basePath = rtrim(TranslationManager::getInstance()->getSettings()->getBackupPath(), '/');
        $finalPath = $basePath . '/' . $backupDir;
        $parentPath = dirname($finalPath);
        $stagingPath = $parentPath . '/.' . basename($finalPath) . '.staging-' . $this->createBackupEntropy();
        $backupId = (string)$metadata['backupId'];
        $finalOwned = false;

        try {
            $this->createLocalBackupDirectory($parentPath);
            if (!is_dir($parentPath)) {
                throw new \RuntimeException("Unable to create the backup parent directory: {$parentPath}.");
            }
            if (is_dir($finalPath)) {
                throw new \RuntimeException("A completed backup already exists at {$finalPath}.");
            }
            if (file_exists($stagingPath)) {
                throw new \RuntimeException("The owned backup staging path already exists: {$stagingPath}.");
            }

            $this->createLocalBackupDirectory($stagingPath);
            if (!is_dir($stagingPath)) {
                throw new \RuntimeException("Unable to create the owned backup staging directory: {$stagingPath}.");
            }

            $files = $this->buildBackupFiles($metadata, $formieTranslations, $siteTranslations);
            foreach ($files as $relativePath => $content) {
                $directory = dirname($stagingPath . '/' . $relativePath);
                $this->createLocalBackupDirectory($directory);
                if (!is_dir($directory)) {
                    throw new \RuntimeException("Unable to create the backup directory: {$directory}.");
                }
                $this->writeLocalBackupFile($stagingPath . '/' . $relativePath, $content);
            }

            $this->validateLocalBackupSnapshot($stagingPath, $files);

            if (file_exists($finalPath)) {
                throw new \RuntimeException("A completed backup appeared before promotion at {$finalPath}.");
            }
            $this->promoteLocalBackupDirectory($stagingPath, $finalPath);
            if (file_exists($stagingPath) || !is_dir($finalPath)) {
                throw new \RuntimeException("Backup promotion did not complete at {$finalPath}.");
            }
            $finalOwned = true;
            $this->validateLocalBackupSnapshot($finalPath, $files);

            $formieCount = count($formieTranslations);
            $siteCount = count($siteTranslations);
            $this->logInfo("Backup created locally", [
                'path' => $finalPath,
                'formieCount' => $formieCount,
                'siteCount' => $siteCount,
            ]);

            return $finalPath;
        } catch (Throwable $e) {
            $this->removeOwnedLocalDirectory($stagingPath);
            if ($finalOwned || $this->localDirectoryBelongsToAttempt($finalPath, $backupId)) {
                $this->removeOwnedLocalDirectory($finalPath);
            }
            $this->logError('Failed to create local backup', ['error' => $e->getMessage()]);
            throw new \RuntimeException(
                'Failed to create backup in ' . $basePath . ': ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Build the complete expected file set before publishing a backup.
     *
     * @return array<string, string>
     */
    private function buildBackupFiles(array $metadata, array $formieTranslations, array $siteTranslations): array
    {
        $formieContent = $formieTranslations !== []
            ? Json::encode($formieTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
        $siteContent = $siteTranslations !== []
            ? Json::encode($siteTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
        $checksum = hash('sha256', $formieContent . $siteContent);
        $metadata['checksum'] = $checksum;
        $metadata['checksumAlgorithm'] = 'sha256';

        $this->logInfo('Backup checksum calculated', [
            'checksum' => substr($checksum, 0, 16) . '...',
            'formieSize' => strlen($formieContent),
            'siteSize' => strlen($siteContent),
        ]);

        $files = [
            'metadata.json' => Json::encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];
        if ($formieTranslations !== []) {
            $files['formie-translations.json'] = $formieContent;
        }
        if ($siteTranslations !== []) {
            $files['site-translations.json'] = $siteContent;
        }

        foreach ($this->collectGeneratedBackupFiles() as $relativePath => $content) {
            $files[$relativePath] = $content;
        }

        ksort($files, SORT_STRING);
        return $files;
    }

    /** @return array<string, string> */
    private function collectGeneratedBackupFiles(): array
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $generationPath = rtrim($settings->getGenerationPath(), '/');

        $sites = TranslationManager::getInstance()->getAllowedSites();
        $filesToBackup = [];

        foreach ($sites as $site) {
            $language = $site->language;
            $filesToBackup[$language . '/formie.php'] = true;
            $filesToBackup[$language . '/' . $settings->translationCategory . '.php'] = true;
        }

        $files = [];
        foreach (array_keys($filesToBackup) as $file) {
            $sourcePath = $generationPath . '/' . $file;
            if (file_exists($sourcePath)) {
                $content = $this->readGeneratedBackupFile($sourcePath);
                $files['php-files/' . str_replace('/', '_', $file)] = $content;
                $this->logInfo("Backed up PHP file", ['file' => $file]);
            }
        }

        ksort($files, SORT_STRING);
        return $files;
    }

    protected function readGeneratedBackupFile(string $path): string
    {
        $content = file_get_contents($path);
        if (!is_string($content)) {
            throw new \RuntimeException("Unable to read generated translation file: {$path}.");
        }

        return $content;
    }

    protected function createLocalBackupDirectory(string $path): void
    {
        FileHelper::createDirectory($path);
    }

    protected function writeLocalBackupFile(string $path, string $content): void
    {
        $written = file_put_contents($path, $content, LOCK_EX);
        if (!is_int($written) || $written !== strlen($content)) {
            throw new \RuntimeException("Unable to write the complete backup file: {$path}.");
        }
    }

    protected function promoteLocalBackupDirectory(string $stagingPath, string $finalPath): void
    {
        if (!rename($stagingPath, $finalPath)) {
            throw new \RuntimeException("Unable to promote the completed backup to {$finalPath}.");
        }
    }

    protected function writeVolumeBackupFile(Volume $volume, string $path, string $content): void
    {
        $volume->write($path, $content);
    }

    protected function promoteVolumeBackupDirectory(Volume $volume, string $stagingPath, string $finalName): void
    {
        $volume->renameDirectory($stagingPath, $finalName);
    }

    /** @param array<string, string> $expectedFiles */
    protected function validateLocalBackupSnapshot(string $root, array $expectedFiles): void
    {
        foreach ($expectedFiles as $relativePath => $expectedContent) {
            $content = file_get_contents($root . '/' . $relativePath);
            if (!is_string($content) || !hash_equals(hash('sha256', $expectedContent), hash('sha256', $content))) {
                throw new \RuntimeException("Backup validation failed for {$relativePath}.");
            }
        }

        $this->validateBackupMetadata($expectedFiles);
        $manifest = $this->buildBackupManifest($root);
        if (array_keys($manifest) !== array_keys($expectedFiles)) {
            throw new \RuntimeException('Backup validation found an incomplete or unexpected local manifest.');
        }
    }

    /** @param array<string, string> $expectedFiles */
    protected function validateVolumeBackupSnapshot(Volume $volume, string $root, array $expectedFiles): void
    {
        foreach ($expectedFiles as $relativePath => $expectedContent) {
            $path = $root . '/' . $relativePath;
            if (!$volume->fileExists($path)) {
                throw new \RuntimeException("Backup validation could not find {$relativePath}.");
            }
            $content = $volume->read($path);
            if (!hash_equals(hash('sha256', $expectedContent), hash('sha256', $content))) {
                throw new \RuntimeException("Backup validation failed for {$relativePath}.");
            }
        }

        $this->validateBackupMetadata($expectedFiles);
        $manifest = $this->buildBackupManifest($root, $volume);
        if (array_keys($manifest) !== array_keys($expectedFiles)) {
            throw new \RuntimeException('Backup validation found an incomplete or unexpected volume manifest.');
        }
    }

    /** @param array<string, string> $files */
    private function validateBackupMetadata(array $files): void
    {
        $metadata = Json::decode($files['metadata.json'] ?? '');
        if (!is_array($metadata)
            || !isset($metadata['backupId'], $metadata['timestamp'], $metadata['reason'], $metadata['translationCount'], $metadata['checksum'])
            || $metadata['checksumAlgorithm'] !== 'sha256'
            || !is_string($metadata['checksum'])
            || strlen($metadata['checksum']) !== 64) {
            throw new \RuntimeException('Backup metadata is incomplete.');
        }

        $formieContent = $files['formie-translations.json'] ?? '';
        $siteContent = $files['site-translations.json'] ?? '';
        if (!hash_equals($metadata['checksum'], hash('sha256', $formieContent . $siteContent))) {
            throw new \RuntimeException('Backup checksum validation failed before publication.');
        }

        $translationCount = 0;
        foreach (['formie-translations.json', 'site-translations.json'] as $path) {
            if (!isset($files[$path])) {
                continue;
            }
            $translations = Json::decode($files[$path]);
            if (!is_array($translations)) {
                throw new \RuntimeException("Backup JSON content is invalid: {$path}.");
            }
            $translationCount += count($translations);
        }
        if ($translationCount !== (int)$metadata['translationCount'] || $translationCount === 0) {
            throw new \RuntimeException('Backup translation metadata does not match its JSON content.');
        }
    }

    private function ensureVolumeDirectory(Volume $volume, string $path): void
    {
        $parts = explode('/', trim($path, '/'));
        $currentPath = '';
        foreach ($parts as $part) {
            $currentPath = $currentPath === '' ? $part : $currentPath . '/' . $part;
            if (!$volume->directoryExists($currentPath)) {
                $volume->createDirectory($currentPath);
                if (!$volume->directoryExists($currentPath)) {
                    throw new \RuntimeException("Unable to create the backup directory: {$currentPath}.");
                }
            }
        }
    }

    private function localDirectoryBelongsToAttempt(string $path, string $backupId): bool
    {
        $metadata = @file_get_contents($path . '/metadata.json');
        return is_string($metadata) && $this->metadataBelongsToAttempt($metadata, $backupId);
    }

    private function volumeDirectoryBelongsToAttempt(Volume $volume, string $path, string $backupId): bool
    {
        try {
            $metadataPath = $path . '/metadata.json';
            return $volume->directoryExists($path)
                && $volume->fileExists($metadataPath)
                && $this->metadataBelongsToAttempt($volume->read($metadataPath), $backupId);
        } catch (Throwable $cleanupCheckError) {
            $this->logError('Unable to verify ownership of a partial volume backup', [
                'path' => $path,
                'error' => $cleanupCheckError->getMessage(),
            ]);
            return false;
        }
    }

    private function metadataBelongsToAttempt(string $content, string $backupId): bool
    {
        try {
            $metadata = Json::decode($content);
            return is_array($metadata) && ($metadata['backupId'] ?? null) === $backupId;
        } catch (Throwable) {
            return false;
        }
    }

    private function removeOwnedLocalDirectory(string $path): void
    {
        if (is_dir($path)) {
            FileHelper::removeDirectory($path);
        }
    }

    private function removeOwnedVolumeDirectory(Volume $volume, string $path): void
    {
        try {
            if ($volume->directoryExists($path)) {
                $volume->deleteDirectory($path);
            }
        } catch (Throwable $cleanupError) {
            $this->logError('Unable to remove an owned partial volume backup', [
                'path' => $path,
                'error' => $cleanupError->getMessage(),
            ]);
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

                $backupRoot = self::VOLUME_BACKUP_ROOT . '/' . $backupName;
                return $this->readBackupManifest(
                    $this->buildBackupManifest($backupRoot, $storage),
                    $storage,
                );
            } catch (Throwable $e) {
                $this->throwStorageUnavailable('download', $e);
            }
        }

        $backupDir = rtrim(TranslationManager::getInstance()->getSettings()->getBackupPath(), '/') . '/' . $backupName;
        if (!is_dir($backupDir)) {
            return [];
        }

        return $this->readBackupManifest(
            $this->buildBackupManifest($backupDir),
        );
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

    /**
     * Return whether a backup reference is a supported completed-backup name.
     *
     * Both legacy timestamp-only names and collision-resistant names are valid.
     * Staging names are deliberately excluded.
     *
     * @since 5.36.0
     */
    public function isValidBackupName(string $backupName): bool
    {
        $folder = null;
        $timestamp = $backupName;
        if (str_contains($backupName, '/')) {
            [$folder, $timestamp] = explode('/', $backupName, 2);
            if (!in_array($folder, self::BACKUP_FOLDERS, true)) {
                return false;
            }
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(?:_[a-f0-9]{32})?$/', $timestamp) === 1;
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
        $manifest = $this->buildBackupManifest(self::VOLUME_BACKUP_ROOT . '/' . $backupName, $storage);
        $metadata['size'] = $this->calculateBackupManifestSize($manifest, $storage);
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
            if (in_array($dirName, self::BACKUP_FOLDERS, true) || !$this->isValidBackupName($dirName)) {
                continue;
            }

            $metadataFile = $dir . '/metadata.json';
            if (file_exists($metadataFile)) {
                try {
                    $metadata = Json::decode(file_get_contents($metadataFile));
                    $metadata['path'] = $dir;
                    $metadata['name'] = basename($dir);
                    $metadata['size'] = $this->calculateBackupManifestSize($this->buildBackupManifest($dir));
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
                $backupName = $subfolder . '/' . basename($dir);
                if (!$this->isValidBackupName($backupName)) {
                    continue;
                }

                $metadataFile = $dir . '/metadata.json';
                if (file_exists($metadataFile)) {
                    try {
                        $metadata = Json::decode(file_get_contents($metadataFile));
                        $metadata['path'] = $dir;
                        $metadata['name'] = $backupName;
                        $metadata['size'] = $this->calculateBackupManifestSize($this->buildBackupManifest($dir));
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
     * @param string $backupName The backup reference (e.g., "manual/2024-01-05_14-30-00_<unique-id>")
     * @return array Result with success status and message
     */
    public function restoreBackup(string $backupName): array
    {
        if (!$this->isValidBackupName($backupName)) {
            return [
                'success' => false,
                'message' => 'Invalid backup name',
            ];
        }

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
                if ($preRestoreBackup === null) {
                    $this->logInfo('Restore: Current translation state is empty; no safety backup was needed');
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
                if ($preRestoreBackup === null) {
                    $this->logInfo('Restore: Current translation state is empty; no safety backup was needed');
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
        if (!$this->isValidBackupName($backupName)) {
            return false;
        }

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
     * Build the complete, path-confined file manifest for one backup root.
     *
     * Manifest keys are safe forward-slash ZIP member paths. Values are the
     * corresponding local paths or storage-relative paths used for reads and
     * size queries.
     *
     * @return array<string, string>
     */
    private function buildBackupManifest(string $backupRoot, ?BaseFsInterface $storage = null): array
    {
        if ($storage !== null) {
            return $this->buildStorageBackupManifest($backupRoot, $storage);
        }

        $resolvedRoot = realpath($backupRoot);
        if (!is_string($resolvedRoot) || !is_dir($resolvedRoot)) {
            return [];
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $resolvedRoot), '/');
        $manifest = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $resolvedRoot,
                \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isLink() || !$file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            if (!str_starts_with($path, $normalizedRoot . '/')) {
                continue;
            }

            $memberPath = $this->safeManifestPath(substr($path, strlen($normalizedRoot) + 1));
            if ($memberPath !== null) {
                $manifest[$memberPath] = $file->getPathname();
            }
        }

        ksort($manifest, SORT_STRING);
        return $manifest;
    }

    /** @return array<string, string> */
    private function buildStorageBackupManifest(string $backupRoot, BaseFsInterface $storage): array
    {
        $logicalRoot = trim(str_replace('\\', '/', $backupRoot), '/');
        $listingRoot = $logicalRoot;
        if ($storage instanceof Volume) {
            $subpath = trim(str_replace('\\', '/', $storage->getSubpath()), '/');
            if ($subpath !== '') {
                $listingRoot = $subpath . '/' . $logicalRoot;
            }
        }

        $safeListingRoot = $this->safeManifestPath($listingRoot);
        if ($safeListingRoot === null) {
            throw new \RuntimeException('Backup storage root is not a safe relative path.');
        }

        $rootSegments = explode('/', $safeListingRoot);
        $manifest = [];
        foreach ($storage->getFileList($logicalRoot, true) as $listing) {
            if (!$listing instanceof FsListing || $listing->getIsDir()) {
                continue;
            }

            $listedPath = $this->safeManifestPath($listing->getUri());
            if ($listedPath === null) {
                continue;
            }

            $listedSegments = explode('/', $listedPath);
            if (count($listedSegments) <= count($rootSegments)
                || array_slice($listedSegments, 0, count($rootSegments)) !== $rootSegments) {
                continue;
            }

            $memberPath = $this->safeManifestPath(implode('/', array_slice($listedSegments, count($rootSegments))));
            if ($memberPath !== null) {
                $manifest[$memberPath] = $logicalRoot . '/' . $memberPath;
            }
        }

        ksort($manifest, SORT_STRING);
        return $manifest;
    }

    private function safeManifestPath(string $path): ?string
    {
        if ($path === ''
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:/', $path) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return null;
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }

            $decoded = rawurldecode($segment);
            if ($decoded === '.'
                || $decoded === '..'
                || str_contains($decoded, '/')
                || str_contains($decoded, '\\')
                || str_contains($decoded, "\0")) {
                return null;
            }
        }

        return implode('/', $segments);
    }

    /**
     * @param array<string, string> $manifest
     * @return array<string, string>
     */
    private function readBackupManifest(array $manifest, ?BaseFsInterface $storage = null): array
    {
        $files = [];
        foreach ($manifest as $memberPath => $sourcePath) {
            if ($storage !== null) {
                $files[$memberPath] = $storage->read($sourcePath);
                continue;
            }

            $content = file_get_contents($sourcePath);
            if (!is_string($content)) {
                throw new \RuntimeException("Unable to read backup file: {$memberPath}");
            }
            $files[$memberPath] = $content;
        }

        return $files;
    }

    /** @param array<string, string> $manifest */
    private function calculateBackupManifestSize(array $manifest, ?BaseFsInterface $storage = null): int
    {
        $size = 0;
        foreach ($manifest as $memberPath => $sourcePath) {
            if ($storage !== null) {
                try {
                    $size += $storage->getFileSize($sourcePath);
                } catch (Throwable $sizeError) {
                    try {
                        $size += $this->getStreamSize($storage, $sourcePath);
                    } catch (Throwable $streamError) {
                        throw new \RuntimeException(
                            "Unable to calculate the size of backup file: {$memberPath}",
                            previous: $streamError,
                        );
                    }
                }
                continue;
            }

            $fileSize = filesize($sourcePath);
            if (!is_int($fileSize)) {
                throw new \RuntimeException("Unable to calculate the size of backup file: {$memberPath}");
            }
            $size += $fileSize;
        }

        return $size;
    }

    private function getStreamSize(BaseFsInterface $storage, string $sourcePath): int
    {
        $stream = $storage->getFileStream($sourcePath);
        if (!is_resource($stream)) {
            throw new \RuntimeException('Backup provider did not return a readable stream.');
        }

        $size = 0;
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, self::SIZE_STREAM_CHUNK_BYTES);
                if (!is_string($chunk)) {
                    throw new \RuntimeException('Backup provider stream could not be read.');
                }
                if ($chunk === '' && !feof($stream)) {
                    throw new \RuntimeException('Backup provider stream stopped before EOF.');
                }
                $size += strlen($chunk);
            }
        } finally {
            fclose($stream);
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
