<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use Craft;
use craft\base\FsInterface;
use craft\fs\Local;
use craft\helpers\Json;
use craft\models\Volume;
use craft\services\Config;
use craft\services\Volumes;
use Generator;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\services\GenerationService;
use lindemannrock\translationmanager\services\TranslationsService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Pins canonical Craft volume subpaths and bounded historical compatibility.
 *
 * @since 5.35.0
 */
final class BackupVolumeSubpathTest extends TestCase
{
    private const SUBPATH = 'customer-assets/translation-backups';
    private const CANONICAL_ROOT = self::SUBPATH . '/translation-manager/backups';
    private const HISTORICAL_ROOT = 'translation-manager/backups';

    private Local $filesystem;
    private string $filesystemRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEmptyPluginConfig();
        $this->filesystemRoot = $this->createTrackedTempDirectory('translation-volume-subpath-');
        $this->filesystem = new Local([
            'name' => 'Translation backup subpath filesystem',
            'handle' => 'translationBackupSubpathFilesystem',
            'path' => $this->filesystemRoot,
        ]);
        $this->installVolume($this->volume($this->filesystem, self::SUBPATH));
        $this->settings()->backupEnabled = true;
        $this->settings()->backupVolumeUid = 'backup-volume-subpath';
        $this->settings()->backupRetentionDays = 0;
    }

    public function testNonEmptySubpathOwnsCreateListMetadataSizeDownloadAndDelete(): void
    {
        $this->seedTranslation();

        $createdPath = $this->backup()->createBackup('manual');

        self::assertIsString($createdPath);
        $name = $this->relativeName($createdPath);
        self::assertDirectoryExists($this->filesystemRoot . '/' . self::CANONICAL_ROOT . '/' . $name);
        self::assertDirectoryDoesNotExist($this->filesystemRoot . '/' . self::HISTORICAL_ROOT . '/' . $name);

        $backups = $this->backup()->getBackups();
        self::assertCount(1, $backups);
        self::assertSame($name, $backups[0]['name']);
        self::assertSame('canonical-volume', $backups[0]['storageType']);
        self::assertSame(
            'Volume: Translation backup subpath/' . self::CANONICAL_ROOT,
            $backups[0]['storageLocation'],
        );
        self::assertGreaterThan(0, $backups[0]['size']);

        $downloadFiles = $this->backup()->getDownloadFiles($name);
        self::assertArrayHasKey('metadata.json', $downloadFiles);
        self::assertStringContainsString('checksum', $downloadFiles['metadata.json']);

        self::assertTrue($this->backup()->deleteBackup($name));
        self::assertDirectoryDoesNotExist($this->filesystemRoot . '/' . self::CANONICAL_ROOT . '/' . $name);
    }

    public function testRemoteLikeFilesystemUsesTheSameVolumeWrapperLifecycle(): void
    {
        $remoteRoot = $this->createTrackedTempDirectory('translation-remote-subpath-');
        $delegate = new Local([
            'name' => 'Remote-like delegate',
            'handle' => 'translationRemoteLikeDelegate',
            'path' => $remoteRoot,
        ]);
        $this->installVolume($this->volume($this->remoteLikeFilesystem($delegate), self::SUBPATH));
        $this->seedTranslation();

        $createdPath = $this->backup()->createBackup('manual');

        self::assertIsString($createdPath);
        $name = $this->relativeName($createdPath);
        self::assertDirectoryExists($remoteRoot . '/' . self::CANONICAL_ROOT . '/' . $name);
        self::assertSame($name, $this->backup()->getBackups()[0]['name']);
        self::assertArrayHasKey('metadata.json', $this->backup()->getDownloadFiles($name));
        self::assertTrue($this->backup()->deleteBackup($name));
    }

    public function testExactHistoricalPrefixSupportsFolderAndTimestampOnlyNames(): void
    {
        $folderName = 'manual/2026-08-19_12-00-00';
        $rootName = '2026-08-19_11-00-00';
        $this->writeBackup(self::HISTORICAL_ROOT, $folderName, 'historical folder');
        $this->writeBackup(self::HISTORICAL_ROOT, $rootName, 'historical root', 1_755_601_200);

        $backups = $this->backup()->getBackups();

        self::assertCount(2, $backups);
        self::assertSame([$folderName, $rootName], array_column($backups, 'name'));
        self::assertSame(['historical-volume', 'historical-volume'], array_column($backups, 'storageType'));
        self::assertSame(
            ['Volume: Translation backup subpath/' . self::HISTORICAL_ROOT, 'Volume: Translation backup subpath/' . self::HISTORICAL_ROOT],
            array_column($backups, 'storageLocation'),
        );
        self::assertStringContainsString('historical folder', $this->backup()->getDownloadFiles($folderName)['metadata.json']);
        self::assertTrue($this->backup()->deleteBackup($folderName));
        self::assertDirectoryDoesNotExist($this->filesystemRoot . '/' . self::HISTORICAL_ROOT . '/' . $folderName);
        self::assertDirectoryExists($this->filesystemRoot . '/' . self::HISTORICAL_ROOT . '/' . $rootName);
    }

    public function testNoArbitraryPathIsScannedOrManaged(): void
    {
        $name = 'scheduled/2020-01-01_00-00-00';
        $this->writeBackup('unrelated/' . self::HISTORICAL_ROOT, $name, 'unrelated', 1_577_836_800, 'scheduled');
        $this->settings()->backupRetentionDays = 1;

        self::assertSame([], $this->backup()->getBackups());
        self::assertSame([], $this->backup()->getDownloadFiles($name));
        self::assertFalse($this->backup()->deleteBackup($name));
        self::assertSame(0, $this->backup()->cleanupOldBackups());
        self::assertDirectoryExists($this->filesystemRoot . '/unrelated/' . self::HISTORICAL_ROOT . '/' . $name);
    }

    public function testEmptySubpathHasOneCanonicalLocation(): void
    {
        $this->installVolume($this->volume($this->filesystem, ''));
        $name = 'manual/2026-08-19_12-00-00';
        $this->writeBackup(self::HISTORICAL_ROOT, $name, 'single location');

        $backups = $this->backup()->getBackups();

        self::assertCount(1, $backups);
        self::assertSame('canonical-volume', $backups[0]['storageType']);
        self::assertSame('Volume: Translation backup subpath/' . self::HISTORICAL_ROOT, $backups[0]['storageLocation']);
    }

    public function testCanonicalDuplicateWinsForListDownloadRestoreAndDelete(): void
    {
        $name = 'manual/2026-08-19_12-00-00';
        $this->writeBackup(self::CANONICAL_ROOT, $name, 'canonical copy');
        $this->writeBackup(self::HISTORICAL_ROOT, $name, 'historical copy', checksumValid: false);

        $backups = $this->backup()->getBackups();
        self::assertCount(1, $backups);
        self::assertSame('canonical-volume', $backups[0]['storageType']);
        self::assertStringContainsString('canonical copy', $this->backup()->getDownloadFiles($name)['metadata.json']);

        $translations = new RestoreTranslationsSpy();
        $generation = new RestoreGenerationSpy();
        $this->replacePluginComponent('translations', $translations);
        $this->replacePluginComponent('generate', $generation);
        $this->settings()->backupEnabled = false;

        $restore = $this->backup()->restoreBackup($name);
        self::assertTrue($restore['success']);
        self::assertSame(1, $translations->deleteCalls);
        self::assertSame(1, $generation->generateCalls);

        self::assertTrue($this->backup()->deleteBackup($name));
        self::assertDirectoryDoesNotExist($this->filesystemRoot . '/' . self::CANONICAL_ROOT . '/' . $name);
        self::assertDirectoryExists($this->filesystemRoot . '/' . self::HISTORICAL_ROOT . '/' . $name);
        self::assertStringContainsString('historical copy', $this->backup()->getDownloadFiles($name)['metadata.json']);

        $historicalRestore = $this->backup()->restoreBackup($name);
        self::assertFalse($historicalRestore['success']);
    }

    public function testRetentionUsesCanonicalDuplicatePrecedence(): void
    {
        $name = 'scheduled/2020-01-01_00-00-00';
        $this->writeBackup(self::CANONICAL_ROOT, $name, 'canonical expired', 1_577_836_800, 'scheduled');
        $this->writeBackup(self::HISTORICAL_ROOT, $name, 'historical duplicate', 1_577_836_800, 'scheduled');
        $this->settings()->backupRetentionDays = 1;

        self::assertSame(1, $this->backup()->cleanupOldBackups());
        self::assertDirectoryDoesNotExist($this->filesystemRoot . '/' . self::CANONICAL_ROOT . '/' . $name);
        self::assertDirectoryExists($this->filesystemRoot . '/' . self::HISTORICAL_ROOT . '/' . $name);
    }

    public function testHistoricalRetentionPreservesManualAndUnrelatedObjects(): void
    {
        $oldName = 'scheduled/2020-01-01_00-00-00';
        $manualName = 'manual/2020-01-01_00-00-00';
        $this->writeBackup(self::HISTORICAL_ROOT, $oldName, 'expired scheduled', 1_577_836_800, 'scheduled');
        $this->writeBackup(self::HISTORICAL_ROOT, $manualName, 'preserved manual', 1_577_836_800, 'manual');
        $this->writeBackup('unrelated/' . self::HISTORICAL_ROOT, $oldName, 'unrelated', 1_577_836_800, 'scheduled');
        $this->settings()->backupRetentionDays = 1;

        self::assertSame(1, $this->backup()->cleanupOldBackups());
        self::assertDirectoryDoesNotExist($this->filesystemRoot . '/' . self::HISTORICAL_ROOT . '/' . $oldName);
        self::assertDirectoryExists($this->filesystemRoot . '/' . self::HISTORICAL_ROOT . '/' . $manualName);
        self::assertDirectoryExists($this->filesystemRoot . '/unrelated/' . self::HISTORICAL_ROOT . '/' . $oldName);
    }

    private function backup(): BackupService
    {
        return TranslationManager::getInstance()->backup;
    }

    private function seedTranslation(): void
    {
        $this->requireLatinSourceLanguage();
        $source = self::MARKER . 'backup_storage_' . bin2hex(random_bytes(4));
        self::assertNotNull($this->translations->createOrUpdateTranslation($source, 'site.backup-storage'));
    }

    private function relativeName(string $createdPath): string
    {
        $prefix = self::HISTORICAL_ROOT . '/';
        self::assertStringStartsWith($prefix, $createdPath);
        return substr($createdPath, strlen($prefix));
    }

    private function volume(FsInterface $fs, string $subpath): Volume
    {
        $volume = new Volume([
            'name' => 'Translation backup subpath',
            'handle' => 'translationBackupSubpath',
            'uid' => 'backup-volume-subpath',
            'subpath' => $subpath,
        ]);
        $property = new \ReflectionProperty(Volume::class, '_fs');
        $property->setValue($volume, $fs);
        return $volume;
    }

    private function installVolume(Volume $volume): void
    {
        $volumes = $this->createMock(Volumes::class);
        $volumes->method('getVolumeByUid')->willReturn($volume);
        Craft::$app->set('volumes', $volumes);
    }

    private function installEmptyPluginConfig(): void
    {
        $original = Craft::$app->getConfig();
        $config = $this->createMock(Config::class);
        $config->method('getGeneral')->willReturn($original->getGeneral());
        $config->method('getConfigFromFile')->willReturn([]);
        Craft::$app->set('config', $config);
    }

    private function writeBackup(
        string $root,
        string $name,
        string $marker,
        int $timestamp = 1_755_604_800,
        string $reason = 'manual',
        bool $checksumValid = true,
    ): void {
        $path = $root . '/' . $name;
        $this->filesystem->createDirectory($path);
        $formie = Json::encode([]);
        $checksum = $checksumValid ? hash('sha256', $formie) : 'invalid-checksum';
        $metadata = Json::encode([
            'date' => basename($name),
            'timestamp' => $timestamp,
            'reason' => $reason,
            'user' => $marker,
            'translationCount' => 0,
            'checksum' => $checksum,
            'checksumAlgorithm' => 'sha256',
        ], JSON_PRETTY_PRINT);
        $this->filesystem->write($path . '/metadata.json', $metadata);
        $this->filesystem->write($path . '/formie-translations.json', $formie);
    }

    private function remoteLikeFilesystem(Local $delegate): FsInterface & MockObject
    {
        /** @var FsInterface&MockObject $filesystem */
        $filesystem = $this->createMock(FsInterface::class);
        $filesystem->method('directoryExists')->willReturnCallback(
            static fn(string $path): bool => $delegate->directoryExists($path),
        );
        $filesystem->method('createDirectory')->willReturnCallback(
            static fn(string $path, array $config = []) => $delegate->createDirectory($path, $config),
        );
        $filesystem->method('deleteDirectory')->willReturnCallback(
            static fn(string $path) => $delegate->deleteDirectory($path),
        );
        $filesystem->method('write')->willReturnCallback(
            static fn(string $path, string $contents, array $config = []) => $delegate->write($path, $contents, $config),
        );
        $filesystem->method('read')->willReturnCallback(
            static fn(string $path): string => $delegate->read($path),
        );
        $filesystem->method('fileExists')->willReturnCallback(
            static fn(string $path): bool => $delegate->fileExists($path),
        );
        $filesystem->method('getFileSize')->willReturnCallback(
            static fn(string $path): int => $delegate->getFileSize($path),
        );
        $filesystem->method('getFileList')->willReturnCallback(
            static fn(string $path = '', bool $recursive = true): Generator => $delegate->getFileList($path, $recursive),
        );

        return $filesystem;
    }
}

/** Prevents restore verification from deleting shared translation rows. */
final class RestoreTranslationsSpy extends TranslationsService
{
    public int $deleteCalls = 0;

    public function deleteAllTranslations(): int
    {
        $this->deleteCalls++;
        return 0;
    }
}

/** Prevents restore verification from writing generated translation files. */
final class RestoreGenerationSpy extends GenerationService
{
    public int $generateCalls = 0;

    public function generateAll(): array
    {
        $this->generateCalls++;
        return ['success' => true, 'results' => []];
    }
}
