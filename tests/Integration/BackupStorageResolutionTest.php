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
use craft\base\MissingComponentInterface;
use craft\fs\Local;
use craft\fs\MissingFs;
use craft\helpers\FileHelper;
use craft\models\Volume;
use craft\services\Config;
use craft\services\Volumes;
use craft\web\Request;
use craft\web\Response;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\translationmanager\controllers\BackupController;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use yii\base\UserException;

/**
 * Pins the effective backup-storage decision independently of storage health.
 *
 * @since 5.35.0
 */
final class BackupStorageResolutionTest extends TestCase
{
    private const UNAVAILABLE = 'The configured backup volume cannot currently be used. Backup operations are unavailable until the volume is restored or the effective setting is changed.';

    private string $localRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEmptyPluginConfig();
        $relativePath = 'translation-manager/' . $this->nextTestMarker('backup-storage-local-', 'path');
        $this->localRoot = Craft::getAlias('@storage/' . $relativePath);
        self::assertIsString($this->localRoot);
        FileHelper::createDirectory($this->localRoot);
        $this->trackTempPath($this->localRoot);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupPath = '@storage/' . $relativePath;
        $this->settings()->backupVolumeUid = null;
        $this->settings()->backupRetentionDays = 0;
    }

    public function testNoConfiguredVolumeUsesTheEffectiveExplicitLocalPath(): void
    {
        $volumes = $this->createMock(Volumes::class);
        $volumes->expects(self::never())->method('getVolumeByUid');
        Craft::$app->set('volumes', $volumes);

        self::assertFalse($this->backup()->isUsingVolumeStorage());
        self::assertSame($this->localRoot, $this->backup()->getBackupPath());
        $this->requireLatinSourceLanguage();
        self::assertNotNull($this->translations->createOrUpdateTranslation(
            self::MARKER . 'local_backup_' . bin2hex(random_bytes(4)),
            'site.backup-storage',
        ));

        $created = $this->backup()->createBackup('manual');

        self::assertIsString($created);
        self::assertStringStartsWith($this->localRoot . '/manual/', $created);
        self::assertDirectoryExists($created);
    }

    public function testEffectiveConfigSelectsVolumeInsteadOfItsLocalPathOverride(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getConfigFromFile')->willReturnCallback(
            static fn(string $handle): array => $handle === 'translation-manager'
                ? [
                    'backupPath' => '@storage/configured-local-fallback-must-not-be-used',
                    'backupVolumeUid' => 'configured-missing-volume',
                ]
                : [],
        );
        Craft::$app->set('config', $config);
        PluginHelper::applyConfigOverridesToSettings($this->settings(), 'translation-manager');
        $this->installVolume(null);

        self::assertSame('configured-missing-volume', $this->settings()->backupVolumeUid);
        self::assertSame('@storage/configured-local-fallback-must-not-be-used', $this->settings()->backupPath);
        $this->expectException(UserException::class);
        $this->expectExceptionMessage(self::UNAVAILABLE);
        $this->backup()->getBackups();
    }

    public function testValidConfiguredVolumeResolutionPerformsNoStorageIoOrSettingsMutation(): void
    {
        /** @var FsInterface&MockObject $fs */
        $fs = $this->createMock(FsInterface::class);
        foreach (['directoryExists', 'fileExists', 'getFileList', 'read', 'write', 'createDirectory', 'deleteDirectory'] as $method) {
            $fs->expects(self::never())->method($method);
        }
        $this->settings()->backupVolumeUid = 'backup-volume';
        $before = $this->settings()->getAttributes();
        $this->installVolume($this->volume($fs));

        self::assertTrue($this->backup()->isUsingVolumeStorage());
        self::assertSame($before, $this->settings()->getAttributes());
    }

    public function testMissingUidTargetFailsClosedWithoutLocalFallback(): void
    {
        $this->assertUnavailableWithoutLocalFallback('deleted-volume', null);
    }

    public function testValidationInvalidVolumeFailsClosedWithoutLocalFallback(): void
    {
        $webroot = Craft::getAlias('@webroot');
        self::assertIsString($webroot);
        $fs = new Local([
            'name' => 'Invalid webroot filesystem',
            'handle' => 'invalidWebrootFilesystem',
            'path' => $webroot . '/translation-manager-invalid-volume',
        ]);

        $this->assertUnavailableWithoutLocalFallback('invalid-volume', $this->volume($fs, 'invalid-volume'));
    }

    public function testMissingFsFailsClosedWithoutLocalFallback(): void
    {
        $this->assertUnavailableWithoutLocalFallback(
            'missing-filesystem',
            $this->volume(new MissingFs(['handle' => 'missing-filesystem']), 'missing-filesystem'),
        );
    }

    public function testMissingComponentInterfaceFailsClosedWithoutLocalFallback(): void
    {
        $fs = $this->createMockForIntersectionOfInterfaces([FsInterface::class, MissingComponentInterface::class]);
        $this->assertUnavailableWithoutLocalFallback(
            'missing-component',
            $this->volume($fs, 'missing-component'),
        );
    }

    public function testThrowableDuringResolutionFailsClosedWithoutLocalFallback(): void
    {
        $volume = $this->createMock(Volume::class);
        $volume->method('getFs')->willThrowException(new \Error('resolution failure'));
        $this->assertUnavailableWithoutLocalFallback('throwing-volume', $volume);
    }

    public function testReadOnlyAndOperationalFailuresFailClosed(): void
    {
        foreach (['read-only storage', 'remote operation failure'] as $message) {
            $fs = $this->createMock(FsInterface::class);
            $fs->method('directoryExists')->willThrowException(new RuntimeException($message));
            $this->settings()->backupVolumeUid = 'failing-volume';
            $this->installVolume($this->volume($fs, 'failing-volume'));

            try {
                $this->backup()->getBackups();
                self::fail('Expected configured volume operation to fail closed.');
            } catch (UserException $exception) {
                self::assertSame(self::UNAVAILABLE, $exception->getMessage());
            }

            self::assertSame([], $this->visibleEntries($this->localRoot));
            self::assertSame('failing-volume', $this->settings()->backupVolumeUid);
        }
    }

    public function testEveryOperationalSeamRefusesAnUnavailableConfiguredVolume(): void
    {
        $this->settings()->backupVolumeUid = 'unavailable-volume';
        $this->settings()->backupRetentionDays = 1;

        foreach (['create', 'list', 'download', 'restore', 'delete', 'retention', 'location'] as $operation) {
            $this->installVolume(null);
            try {
                match ($operation) {
                    'create' => $this->backup()->createBackup('manual'),
                    'list' => $this->backup()->getBackups(),
                    'download' => $this->backup()->getDownloadFiles('manual/2026-08-19_12-00-00'),
                    'restore' => $this->backup()->restoreBackup('manual/2026-08-19_12-00-00'),
                    'delete' => $this->backup()->deleteBackup('manual/2026-08-19_12-00-00'),
                    'retention' => $this->backup()->cleanupOldBackups(),
                    'location' => $this->backup()->getBackupPath(),
                };
                self::fail("Expected {$operation} to fail closed.");
            } catch (UserException $exception) {
                self::assertSame(self::UNAVAILABLE, $exception->getMessage());
            }
        }

        self::assertSame([], $this->visibleEntries($this->localRoot));
        self::assertSame('unavailable-volume', $this->settings()->backupVolumeUid);
    }

    public function testSameUidRecoversWhenTheVolumeBecomesAvailable(): void
    {
        $this->settings()->backupVolumeUid = 'recovering-volume';
        $volume = $this->volume(
            new Local([
                'name' => 'Recovering filesystem',
                'handle' => 'recoveringFilesystem',
                'path' => $this->createTrackedTempDirectory('translation-backup-recovery-'),
            ]),
            'recovering-volume',
        );
        $this->installVolumeSequence(null, $volume, $volume);

        try {
            $this->backup()->isUsingVolumeStorage();
            self::fail('Expected initial volume resolution to fail.');
        } catch (UserException) {
            self::assertSame('recovering-volume', $this->settings()->backupVolumeUid);
        }

        self::assertTrue($this->backup()->isUsingVolumeStorage());
        self::assertSame('recovering-volume', $this->settings()->backupVolumeUid);
    }

    public function testControllerDownloadDelegatesAllStorageReadsToBackupService(): void
    {
        $name = 'manual/2026-08-19_12-00-00';
        $service = new DownloadAuthorityBackupService();
        $this->replacePluginComponent('backup', $service);

        $request = $this->createMock(Request::class);
        $request->method('getRequiredParam')->with('backup')->willReturn($name);
        Craft::$app->set('request', $request);

        $response = $this->createMock(Response::class);
        $response->expects(self::once())->method('sendFile')->willReturnSelf();
        Craft::$app->set('response', $response);

        $zipPath = Craft::$app->getPath()->getTempPath() . '/translation-backup-manual-2026-08-19_12-00-00.zip';
        $this->trackTempPath($zipPath);

        $result = (new BackupController('backup', TranslationManager::getInstance()))->actionDownload();

        self::assertSame($response, $result);
        self::assertSame([$name], $service->requestedBackups);
        self::assertFileExists($zipPath);
    }

    private function assertUnavailableWithoutLocalFallback(string $uid, ?Volume $volume): void
    {
        $this->settings()->backupVolumeUid = $uid;
        $before = $this->settings()->getAttributes();
        $this->installVolume($volume);

        try {
            $this->backup()->getBackups();
            self::fail('Expected configured volume resolution to fail closed.');
        } catch (UserException $exception) {
            self::assertSame(self::UNAVAILABLE, $exception->getMessage());
        }

        self::assertSame([], $this->visibleEntries($this->localRoot));
        self::assertSame($before, $this->settings()->getAttributes());
    }

    private function backup(): BackupService
    {
        return TranslationManager::getInstance()->backup;
    }

    private function volume(FsInterface $fs, string $uid = 'backup-volume'): Volume
    {
        $volume = new Volume([
            'name' => 'Translation backup test volume',
            'handle' => 'translationBackupTestVolume',
            'uid' => $uid,
        ]);
        $property = new \ReflectionProperty(Volume::class, '_fs');
        $property->setValue($volume, $fs);
        return $volume;
    }

    private function installVolume(?Volume $volume): void
    {
        $this->installVolumeSequence($volume);
    }

    private function installVolumeSequence(?Volume ...$sequence): void
    {
        $volumes = $this->createMock(Volumes::class);
        $index = 0;
        $volumes->method('getVolumeByUid')->willReturnCallback(
            static function(string $uid) use (&$index, $sequence): ?Volume {
                $position = min($index, count($sequence) - 1);
                $index++;
                return $sequence[$position];
            },
        );
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

    /** @return list<string> */
    private function visibleEntries(string $directory): array
    {
        $entries = array_values(array_filter(
            scandir($directory) ?: [],
            static fn(string $entry): bool => !in_array($entry, ['.', '..'], true),
        ));
        sort($entries);
        return $entries;
    }
}

/** Records the backup requested by the controller while owning all file reads. */
final class DownloadAuthorityBackupService extends BackupService
{
    /** @var list<string> */
    public array $requestedBackups = [];

    public function getDownloadFiles(string $backupName): array
    {
        $this->requestedBackups[] = $backupName;
        return ['metadata.json' => '{"source":"service"}'];
    }
}
