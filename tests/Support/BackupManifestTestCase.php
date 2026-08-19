<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Support;

use Craft;
use craft\base\FsInterface;
use craft\fs\Local;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\models\Volume;
use craft\services\Config;
use craft\services\Volumes;
use Generator;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Owns reusable backup-manifest storage fixtures and exact cleanup.
 *
 * @since 5.35.0
 */
abstract class BackupManifestTestCase extends TestCase
{
    protected const SUBPATH = 'customer-assets/translation-backups';
    protected const VOLUME_ROOT = 'translation-manager/backups';

    protected string $backupName;
    protected string $localRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEmptyPluginConfig();
        $this->backupName = 'manual/' . date('Y-m-d_H-i-s', time() + random_int(1_000_000, 9_000_000));
        $this->localRoot = Craft::getAlias('@storage/translation-manager/' . $this->nextTestMarker('manifest-local-', 'path'));
        self::assertIsString($this->localRoot);
        FileHelper::createDirectory($this->localRoot);
        $this->trackTempPath($this->localRoot);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupPath = $this->localRoot;
        $this->settings()->backupVolumeUid = null;
        $this->settings()->backupRetentionDays = 0;
    }

    protected function backup(): BackupService
    {
        return TranslationManager::getInstance()->backup;
    }

    /** @return array<string, string> */
    protected function completeFiles(string $marker = 'shared'): array
    {
        $files = [
            'metadata.json' => Json::encode([
                'date' => basename($this->backupName),
                'timestamp' => 1_755_604_800,
                'reason' => 'manual',
                'user' => $marker,
                'translationCount' => 2,
            ], JSON_PRETTY_PRINT),
            'formie-translations.json' => '[{"source":"Form label"}]',
            'site-translations.json' => '[{"source":"Site label"}]',
            'php-files/en_messages.php' => "<?php\nreturn ['Site label' => 'Site label'];\n",
            'php-files/fr_formie.php' => "<?php\nreturn ['Form label' => 'Libellé'];\n",
            'php-files/nested/custom.php' => "<?php\nreturn ['Nested' => true];\n",
            'notes/provider.txt' => 'provider-owned backup content',
        ];
        ksort($files, SORT_STRING);
        return $files;
    }

    /** @param array<string, string> $files */
    protected function writeLocalBackup(array $files): string
    {
        $root = $this->localRoot . '/' . $this->backupName;
        foreach ($files as $relativePath => $content) {
            $path = $root . '/' . $relativePath;
            FileHelper::createDirectory(dirname($path));
            self::assertNotFalse(file_put_contents($path, $content));
        }

        return $root;
    }

    /** @param array<string, string> $files */
    protected function writeVolumeBackup(Local $filesystem, string $root, array $files): void
    {
        foreach ($files as $relativePath => $content) {
            $path = $root . '/' . $this->backupName . '/' . $relativePath;
            $directory = dirname($path);
            if (!$filesystem->directoryExists($directory)) {
                $filesystem->createDirectory($directory);
            }
            self::assertNotFalse(file_put_contents($filesystem->getRootPath() . '/' . $path, $content));
        }
    }

    /** @return array{0: string, 1: Local} */
    protected function localFilesystem(string $prefix): array
    {
        $root = $this->createTrackedTempDirectory($prefix);
        return [
            $root,
            new Local([
                'name' => 'Backup manifest fixture',
                'handle' => 'backupManifestFixture' . bin2hex(random_bytes(4)),
                'path' => $root,
            ]),
        ];
    }

    protected function installVolume(FsInterface $filesystem, string $subpath = self::SUBPATH): void
    {
        $volume = new Volume([
            'name' => 'Backup manifest volume',
            'handle' => 'backupManifestVolume',
            'uid' => 'backup-manifest-volume',
            'subpath' => $subpath,
        ]);
        $property = new \ReflectionProperty(Volume::class, '_fs');
        $property->setValue($volume, $filesystem);

        $volumes = $this->createMock(Volumes::class);
        $volumes->method('getVolumeByUid')->with('backup-manifest-volume')->willReturn($volume);
        Craft::$app->set('volumes', $volumes);
        $this->settings()->backupVolumeUid = 'backup-manifest-volume';
    }

    /** @param null|callable(string, string): void $recordOperation */
    protected function remoteLikeFilesystem(Local $delegate, ?callable $recordOperation = null): FsInterface & MockObject
    {
        /** @var FsInterface&MockObject $filesystem */
        $filesystem = $this->createMock(FsInterface::class);
        $filesystem->method('directoryExists')->willReturnCallback(
            static function(string $path) use ($delegate, $recordOperation): bool {
                $recordOperation?->__invoke('directoryExists', $path);
                return $delegate->directoryExists($path);
            },
        );
        $filesystem->method('createDirectory')->willReturnCallback(
            static function(string $path, array $config = []) use ($delegate, $recordOperation): void {
                $recordOperation?->__invoke('createDirectory', $path);
                $delegate->createDirectory($path, $config);
            },
        );
        $filesystem->method('deleteDirectory')->willReturnCallback(
            static function(string $path) use ($delegate, $recordOperation): void {
                $recordOperation?->__invoke('deleteDirectory', $path);
                $delegate->deleteDirectory($path);
            },
        );
        $filesystem->method('renameDirectory')->willReturnCallback(
            static function(string $path, string $newName) use ($delegate, $recordOperation): void {
                $recordOperation?->__invoke('renameDirectory', $path);
                $delegate->renameDirectory($path, $newName);
            },
        );
        $filesystem->method('write')->willReturnCallback(
            static function(string $path, string $contents, array $config = []) use ($delegate, $recordOperation): void {
                $recordOperation?->__invoke('write', $path);
                $delegate->write($path, $contents, $config);
            },
        );
        $filesystem->method('read')->willReturnCallback(
            static function(string $path) use ($delegate, $recordOperation): string {
                $recordOperation?->__invoke('read', $path);
                return $delegate->read($path);
            },
        );
        $filesystem->method('fileExists')->willReturnCallback(
            static function(string $path) use ($delegate, $recordOperation): bool {
                $recordOperation?->__invoke('fileExists', $path);
                return $delegate->fileExists($path);
            },
        );
        $filesystem->method('getFileSize')->willReturnCallback(
            static function(string $path) use ($delegate, $recordOperation): int {
                $recordOperation?->__invoke('getFileSize', $path);
                return $delegate->getFileSize($path);
            },
        );
        $filesystem->method('getFileStream')->willReturnCallback(
            static function(string $path) use ($delegate, $recordOperation) {
                $recordOperation?->__invoke('getFileStream', $path);
                return $delegate->getFileStream($path);
            },
        );
        $filesystem->method('getFileList')->willReturnCallback(
            static function(string $path = '', bool $recursive = true) use ($delegate, $recordOperation): Generator {
                $recordOperation?->__invoke('getFileList', $path);
                return $delegate->getFileList($path, $recursive);
            },
        );

        return $filesystem;
    }

    /** @param array<string, string> $files */
    protected function expectedSize(array $files): int
    {
        return array_sum(array_map('strlen', $files));
    }

    private function installEmptyPluginConfig(): void
    {
        $original = Craft::$app->getConfig();
        $config = $this->createMock(Config::class);
        $config->method('getGeneral')->willReturn($original->getGeneral());
        $config->method('getConfigFromFile')->willReturn([]);
        Craft::$app->set('config', $config);
    }
}
