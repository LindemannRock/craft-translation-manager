<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use craft\base\FsInterface;
use craft\fs\Local;
use Generator;
use lindemannrock\translationmanager\tests\Support\BackupManifestTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use yii\base\UserException;

/**
 * Proves displayed backup sizes use the complete archive manifest.
 *
 * @since 5.35.0
 */
final class BackupSizeParityTest extends BackupManifestTestCase
{
    public function testReportedSizeIncludesEveryStoredFile(): void
    {
        $files = $this->completeFiles('local-size');
        $this->writeLocalBackup($files);

        $backup = $this->backup()->getBackups()[0];
        $downloadFiles = $this->backup()->getDownloadFiles($this->backupName);

        self::assertSame($this->expectedSize($files), $backup['size']);
        self::assertSame($files, $downloadFiles);
        self::assertSame($backup['size'], $this->expectedSize($downloadFiles));
        self::assertGreaterThan(strlen($files['metadata.json']), $backup['size']);
    }

    public function testCanonicalHistoricalAndRemoteLikeSizesMatchTheArchiveManifest(): void
    {
        $files = $this->completeFiles('volume-size');
        $expectedSize = $this->expectedSize($files);

        [, $canonicalFs] = $this->localFilesystem('translation-size-canonical-');
        $this->writeVolumeBackup($canonicalFs, self::SUBPATH . '/' . self::VOLUME_ROOT, $files);
        $this->installVolume($canonicalFs);
        $this->assertManifestSize($expectedSize, $files, 'canonical-volume');

        [, $historicalFs] = $this->localFilesystem('translation-size-historical-');
        $this->writeVolumeBackup($historicalFs, self::VOLUME_ROOT, $files);
        $this->installVolume($historicalFs);
        $this->assertManifestSize($expectedSize, $files, 'historical-volume');

        [, $remoteDelegate] = $this->localFilesystem('translation-size-remote-');
        $this->writeVolumeBackup($remoteDelegate, self::SUBPATH . '/' . self::VOLUME_ROOT, $files);
        $this->installVolume($this->remoteLikeFilesystem($remoteDelegate));
        $this->assertManifestSize($expectedSize, $files, 'canonical-volume');
    }

    public function testProviderSizeFailureUsesBoundedStreamsAndClosesEveryStream(): void
    {
        $files = $this->completeFiles('stream-fallback');
        $files['php-files/large.php'] = str_repeat('0123456789abcdef', 2_000);
        [, $delegate] = $this->localFilesystem('translation-size-stream-');
        $this->writeVolumeBackup($delegate, self::SUBPATH . '/' . self::VOLUME_ROOT, $files);
        $streams = [];
        $this->installVolume($this->streamFallbackFilesystem($delegate, $streams));

        $backup = $this->backup()->getBackups()[0];

        self::assertSame($this->expectedSize($files), $backup['size']);
        self::assertCount(count($files), $streams);
        foreach ($streams as $stream) {
            self::assertFalse(is_resource($stream));
        }
    }

    public function testProviderSizeAndStreamFailureFailsClosedWithoutReturningAPartialTotal(): void
    {
        $files = $this->completeFiles('stream-failure');
        [, $delegate] = $this->localFilesystem('translation-size-failure-');
        $this->writeVolumeBackup($delegate, self::SUBPATH . '/' . self::VOLUME_ROOT, $files);
        $streams = [];
        $this->installVolume($this->streamFallbackFilesystem(
            $delegate,
            $streams,
            'php-files/nested/custom.php',
        ));

        try {
            $this->backup()->getBackups();
            self::fail('Expected configured storage size failure to fail closed.');
        } catch (UserException $exception) {
            self::assertStringContainsString('configured backup volume cannot currently be used', $exception->getMessage());
        }

        self::assertNotEmpty($streams);
        foreach ($streams as $stream) {
            self::assertFalse(is_resource($stream));
        }
    }

    /** @param array<string, string> $files */
    private function assertManifestSize(int $expectedSize, array $files, string $storageType): void
    {
        $backup = $this->backup()->getBackups()[0];
        $downloadFiles = $this->backup()->getDownloadFiles($this->backupName);

        self::assertSame($storageType, $backup['storageType']);
        self::assertSame($expectedSize, $backup['size']);
        self::assertSame($files, $downloadFiles);
        self::assertSame($backup['size'], $this->expectedSize($downloadFiles));
    }

    /**
     * @param list<resource> $streams
     * @return FsInterface&MockObject
     */
    private function streamFallbackFilesystem(
        Local $delegate,
        array &$streams,
        ?string $failingStreamSuffix = null,
    ): FsInterface & MockObject {
        /** @var FsInterface&MockObject $filesystem */
        $filesystem = $this->createMock(FsInterface::class);
        $filesystem->method('directoryExists')->willReturnCallback(
            static fn(string $path): bool => $delegate->directoryExists($path),
        );
        $filesystem->method('getFileList')->willReturnCallback(
            static fn(string $path = '', bool $recursive = true): Generator => $delegate->getFileList($path, $recursive),
        );
        $filesystem->method('read')->willReturnCallback(
            static fn(string $path): string => $delegate->read($path),
        );
        $filesystem->method('fileExists')->willReturnCallback(
            static fn(string $path): bool => $delegate->fileExists($path),
        );
        $filesystem->method('getFileSize')->willThrowException(new RuntimeException('Provider size unavailable.'));
        $filesystem->method('getFileStream')->willReturnCallback(
            static function(string $path) use ($delegate, &$streams, $failingStreamSuffix) {
                if ($failingStreamSuffix !== null && str_ends_with($path, $failingStreamSuffix)) {
                    throw new RuntimeException('Provider stream unavailable.');
                }

                $stream = $delegate->getFileStream($path);
                $streams[] = $stream;
                return $stream;
            },
        );

        return $filesystem;
    }
}
