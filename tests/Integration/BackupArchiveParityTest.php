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
use craft\helpers\FileHelper;
use craft\models\FsListing;
use craft\web\Request;
use craft\web\Response;
use Generator;
use lindemannrock\base\helpers\SafeSegmentHelper;
use lindemannrock\translationmanager\controllers\BackupController;
use lindemannrock\translationmanager\tests\Support\BackupManifestTestCase;
use lindemannrock\translationmanager\TranslationManager;
use RuntimeException;
use ZipArchive;

/**
 * Proves complete logical archive parity across supported backup storage.
 *
 * @since 5.35.0
 */
final class BackupArchiveParityTest extends BackupManifestTestCase
{
    public function testCanonicalVolumeCreationWithSubpathIncludesGeneratedPhpFiles(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();
        [, $filesystem] = $this->localFilesystem('translation-manifest-created-');
        $this->installVolume($this->remoteLikeFilesystem($filesystem));

        $category = self::MARKER . 'backup_category_' . bin2hex(random_bytes(4));
        $language = Craft::$app->getSites()->getPrimarySite()->getLanguage();
        $this->settings()->translationCategory = $category;
        $generationRoot = $this->settings()->getGenerationPath();
        $languageRoot = $generationRoot . '/' . $language;
        $languageRootExisted = is_dir($languageRoot);
        FileHelper::createDirectory($languageRoot);
        if (!$languageRootExisted) {
            $this->trackTempPath($languageRoot);
        }
        $generatedFile = $languageRoot . '/' . $category . '.php';
        self::assertNotFalse(file_put_contents($generatedFile, "<?php\nreturn ['Generated' => true];\n"));
        $this->trackTempPath($generatedFile);
        self::assertNotNull($this->translations->createOrUpdateTranslation(
            self::MARKER . 'backup_archive_' . bin2hex(random_bytes(4)),
            'site.backup-archive',
        ));

        $createdPath = $this->backup()->createBackup('manual');

        self::assertIsString($createdPath);
        $createdName = substr($createdPath, strlen(self::VOLUME_ROOT) + 1);
        $downloadFiles = $this->backup()->getDownloadFiles($createdName);
        $memberPath = 'php-files/' . str_replace('/', '_', $language . '/' . $category . '.php');
        self::assertArrayHasKey($memberPath, $downloadFiles);
        self::assertSame(file_get_contents($generatedFile), $downloadFiles[$memberPath]);
        self::assertStringNotContainsString(self::SUBPATH, $memberPath);
        self::assertStringNotContainsString(self::VOLUME_ROOT, $memberPath);
    }

    public function testLocalAndRemoteArchivesContainIdenticalJsonMetadataAndPhpFiles(): void
    {
        $files = $this->completeFiles();
        $this->writeLocalBackup($files);

        $localArchive = $this->downloadArchive();
        self::assertSame($files, $localArchive);

        [, $canonicalDelegate] = $this->localFilesystem('translation-manifest-canonical-');
        $this->writeVolumeBackup($canonicalDelegate, self::SUBPATH . '/' . self::VOLUME_ROOT, $files);
        $this->installVolume($canonicalDelegate);
        $canonicalArchive = $this->downloadArchive();

        [$remoteRoot, $delegate] = $this->localFilesystem('translation-manifest-remote-');
        $this->writeVolumeBackup($delegate, self::SUBPATH . '/' . self::VOLUME_ROOT, $files);
        $this->installVolume($this->remoteLikeFilesystem($delegate));
        $remoteArchive = $this->downloadArchive();

        [, $historicalDelegate] = $this->localFilesystem('translation-manifest-parity-historical-');
        $this->writeVolumeBackup($historicalDelegate, self::VOLUME_ROOT, $files);
        $this->installVolume($historicalDelegate);
        $historicalArchive = $this->downloadArchive();

        self::assertSame($localArchive, $canonicalArchive);
        self::assertSame($localArchive, $remoteArchive);
        self::assertSame($localArchive, $historicalArchive);
        self::assertSame(array_keys($files), array_keys($remoteArchive));
        self::assertArrayHasKey('php-files/nested/custom.php', $remoteArchive);
        self::assertDirectoryExists($remoteRoot . '/' . self::SUBPATH . '/' . self::VOLUME_ROOT . '/' . $this->backupName);
        foreach (array_keys($remoteArchive) as $memberPath) {
            self::assertStringNotContainsString(self::SUBPATH, $memberPath);
            self::assertStringNotContainsString(self::VOLUME_ROOT, $memberPath);
            self::assertStringNotContainsString('\\', $memberPath);
            self::assertFalse(str_starts_with($memberPath, '/'));
        }
    }

    public function testHistoricalExactPrefixIncludesTheCompleteNestedManifest(): void
    {
        $files = $this->completeFiles('historical');
        [, $filesystem] = $this->localFilesystem('translation-manifest-historical-');
        $this->writeVolumeBackup($filesystem, self::VOLUME_ROOT, $files);
        $this->installVolume($filesystem);

        $downloadFiles = $this->backup()->getDownloadFiles($this->backupName);

        self::assertSame($files, $downloadFiles);
        self::assertArrayHasKey('php-files/en_messages.php', $downloadFiles);
        self::assertArrayHasKey('php-files/nested/custom.php', $downloadFiles);
        self::assertArrayNotHasKey(self::VOLUME_ROOT . '/php-files/en_messages.php', $downloadFiles);
    }

    public function testLocalManifestIsRecursiveAndDoesNotFollowSiblingOrSymlinkTargets(): void
    {
        $files = $this->completeFiles('local');
        $backupRoot = $this->writeLocalBackup($files);
        $outside = $this->localRoot . '/outside-owner.txt';
        self::assertNotFalse(file_put_contents($outside, 'must remain outside'));
        self::assertTrue(symlink($outside, $backupRoot . '/php-files/outside-link.php'));

        $downloadFiles = $this->backup()->getDownloadFiles($this->backupName);

        self::assertSame($files, $downloadFiles);
        self::assertArrayNotHasKey('php-files/outside-link.php', $downloadFiles);
        self::assertFileExists($outside);
        self::assertSame('must remain outside', file_get_contents($outside));
    }

    public function testEmptyOptionalJsonFilesAreIncludedOnlyWhenStored(): void
    {
        $files = $this->completeFiles('empty-optional');
        $files['formie-translations.json'] = '';
        unset($files['site-translations.json']);
        $this->writeLocalBackup($files);
        $localFiles = $this->backup()->getDownloadFiles($this->backupName);

        [, $filesystem] = $this->localFilesystem('translation-manifest-empty-');
        $this->writeVolumeBackup($filesystem, self::SUBPATH . '/' . self::VOLUME_ROOT, $files);
        $this->installVolume($filesystem);
        $volumeFiles = $this->backup()->getDownloadFiles($this->backupName);

        self::assertSame($files, $localFiles);
        self::assertSame($localFiles, $volumeFiles);
        self::assertSame('', $volumeFiles['formie-translations.json']);
        self::assertArrayNotHasKey('site-translations.json', $volumeFiles);
    }

    public function testTraversalLikeProviderListingsCannotEscapeTheSelectedBackupPrefix(): void
    {
        $physicalRoot = self::SUBPATH . '/' . self::VOLUME_ROOT . '/' . $this->backupName;
        $safeFiles = [
            $physicalRoot . '/metadata.json' => '{"safe":true}',
            $physicalRoot . '/php-files/en_messages.php' => '<?php return [];',
        ];
        $listedUris = [
            ...array_keys($safeFiles),
            $physicalRoot,
            $physicalRoot . '/../sibling.txt',
            $physicalRoot . '-prefix/other.txt',
            $physicalRoot . '/php-files/../../escape.php',
            '/' . $physicalRoot . '/absolute.php',
            'C:/' . $physicalRoot . '/drive.php',
            $physicalRoot . '\\windows.php',
            $physicalRoot . '//double.php',
            $physicalRoot . '/./dot.php',
            $physicalRoot . '/%2e%2e/encoded.php',
        ];
        $reads = [];

        $filesystem = $this->createMock(FsInterface::class);
        $filesystem->method('directoryExists')->willReturnCallback(
            static fn(string $path): bool => $path === $physicalRoot,
        );
        $filesystem->method('getFileList')->willReturnCallback(
            static function() use ($listedUris): Generator {
                foreach ($listedUris as $uri) {
                    yield new RawUriFsListing($uri);
                }
            },
        );
        $filesystem->method('read')->willReturnCallback(
            static function(string $path) use (&$reads, $safeFiles): string {
                $reads[] = $path;
                if (!array_key_exists($path, $safeFiles)) {
                    throw new RuntimeException('Unsafe listing escaped manifest confinement.');
                }
                return $safeFiles[$path];
            },
        );
        $this->installVolume($filesystem);

        $downloadFiles = $this->backup()->getDownloadFiles($this->backupName);

        self::assertSame([
            'metadata.json' => '{"safe":true}',
            'php-files/en_messages.php' => '<?php return [];',
        ], $downloadFiles);
        self::assertSame(array_keys($safeFiles), $reads);
    }

    public function testCanonicalDuplicatePrecedenceAppliesToTheCompleteManifest(): void
    {
        [, $filesystem] = $this->localFilesystem('translation-manifest-duplicate-');
        $canonical = $this->completeFiles('canonical');
        $historical = $this->completeFiles('historical');
        $historical['php-files/historical-only.php'] = '<?php return ["historical" => true];';
        $this->writeVolumeBackup($filesystem, self::SUBPATH . '/' . self::VOLUME_ROOT, $canonical);
        $this->writeVolumeBackup($filesystem, self::VOLUME_ROOT, $historical);
        $this->installVolume($filesystem);

        $downloadFiles = $this->backup()->getDownloadFiles($this->backupName);

        self::assertSame($canonical, $downloadFiles);
        self::assertArrayNotHasKey('php-files/historical-only.php', $downloadFiles);
        self::assertStringContainsString('canonical', $downloadFiles['metadata.json']);
    }

    /** @return array<string, string> */
    private function downloadArchive(): array
    {
        $request = $this->createMock(Request::class);
        $request->method('getRequiredParam')->with('backup')->willReturn($this->backupName);
        Craft::$app->set('request', $request);

        $response = $this->createMock(Response::class);
        $response->expects(self::once())->method('sendFile')->willReturnSelf();
        Craft::$app->set('response', $response);

        $downloadFilename = 'translation-backup-' . SafeSegmentHelper::filenamePart($this->backupName, 'backup') . '.zip';
        $zipPath = Craft::$app->getPath()->getTempPath() . '/' . $downloadFilename;
        self::assertFileDoesNotExist($zipPath);
        $this->trackTempPath($zipPath);

        $result = (new BackupController('backup', TranslationManager::getInstance()))->actionDownload();
        self::assertSame($response, $result);
        self::assertFileExists($zipPath);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath));
        $files = [];
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                $content = $zip->getFromIndex($index);
                self::assertIsString($name);
                self::assertIsString($content);
                $files[$name] = $content;
            }
        } finally {
            $zip->close();
        }
        ksort($files, SORT_STRING);
        self::assertTrue(unlink($zipPath));

        return $files;
    }
}

/** Supplies raw provider URIs so unsafe separator and prefix forms remain observable. */
final class RawUriFsListing extends FsListing
{
    public function __construct(private readonly string $rawUri)
    {
        parent::__construct([
            'dirname' => '',
            'basename' => 'listing',
            'type' => 'file',
        ]);
    }

    public function getUri(): string
    {
        return $this->rawUri;
    }
}
