<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\controllers\BackupController;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use RuntimeException;
use Throwable;
use yii\web\Response;

/**
 * Pins request-owned ZIP allocation and exact response/interruption cleanup.
 *
 * @since 5.36.0
 */
final class BackupDownloadCleanupTest extends TestCase
{
    private string $archiveRoot;
    private TranslationDownloadLifecycleController $controller;
    private DownloadManifestBackupService $backup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->archiveRoot = $this->createTrackedTempDirectory('translation-download-cleanup-');
        $this->controller = new TranslationDownloadLifecycleController(
            'backup',
            TranslationManager::getInstance(),
            $this->archiveRoot,
        );
        $this->backup = new DownloadManifestBackupService();
        $this->backup->files = [
            'metadata.json' => '{"complete":true}',
            'formie-translations.json' => '[{"source":"Form"}]',
            'php-files/en_messages.php' => '<?php return ["Site" => "Site"];',
            'php-files/nested/provider.php' => '<?php return ["Nested" => true];',
            'site-translations.json' => '[{"source":"Site"}]',
        ];
    }

    public function testSuccessfulResponseKeepsTheCompleteManifestUntilAfterSend(): void
    {
        $response = $this->controller->prepareArchive($this->backup);
        $path = $this->controller->lastOwnedPath();

        self::assertFileExists($path);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path));
        $expectedNames = array_keys($this->backup->files);
        sort($expectedNames, SORT_STRING);
        self::assertSame($expectedNames, $this->zipNames($zip));
        self::assertSame($this->backup->files['php-files/nested/provider.php'], $zip->getFromName('php-files/nested/provider.php'));
        self::assertTrue($zip->close());

        $response->trigger(Response::EVENT_AFTER_SEND);
        self::assertFileDoesNotExist($path);
        $this->controller->runLastShutdownCleanup();
        self::assertFileDoesNotExist($path);
    }

    public function testConcurrentDownloadsUseIndependentOwnedPathsAndCleanup(): void
    {
        $first = $this->controller->prepareArchive($this->backup);
        $firstPath = $this->controller->lastOwnedPath();
        $second = $this->controller->prepareArchive($this->backup);
        $secondPath = $this->controller->lastOwnedPath();

        self::assertNotSame($firstPath, $secondPath);
        self::assertFileExists($firstPath);
        self::assertFileExists($secondPath);
        $first->trigger(Response::EVENT_AFTER_SEND);
        self::assertFileDoesNotExist($firstPath);
        self::assertFileExists($secondPath);
        $second->trigger(Response::EVENT_AFTER_SEND);
        self::assertFileDoesNotExist($secondPath);
    }

    public function testManifestReadFailureCleansAfterImmediateShutdownRegistration(): void
    {
        $this->backup->failure = new RuntimeException('Injected manifest read failure.');

        try {
            $this->controller->prepareArchive($this->backup);
            self::fail('Expected manifest read failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected manifest read failure.', $exception->getMessage());
            self::assertCount(1, $this->controller->shutdownCleanups);
            self::assertFileDoesNotExist($this->controller->lastOwnedPath());
        }
    }

    public function testZipOpenFailureCleansOnlyItsOwnedFile(): void
    {
        $this->controller->failOpen = true;
        $unrelated = $this->archiveRoot . '/other-request.zip';
        self::assertNotFalse(file_put_contents($unrelated, 'preserve'));

        $this->expectException(RuntimeException::class);
        try {
            $this->controller->prepareArchive($this->backup);
        } finally {
            self::assertFileDoesNotExist($this->controller->lastOwnedPath());
            self::assertSame('preserve', file_get_contents($unrelated));
        }
    }

    public function testEveryMemberAddFailureClosesAndCleans(): void
    {
        foreach (range(1, count($this->backup->files)) as $memberCall) {
            $controller = new TranslationDownloadLifecycleController(
                'backup',
                TranslationManager::getInstance(),
                $this->archiveRoot,
            );
            $controller->failMemberCall = $memberCall;

            try {
                $controller->prepareArchive($this->backup);
                self::fail("Expected member {$memberCall} failure.");
            } catch (RuntimeException) {
                self::assertGreaterThanOrEqual(1, $controller->closeCalls);
                self::assertFileDoesNotExist($controller->lastOwnedPath());
            }
        }
    }

    public function testZipCloseFailureRetriesClosureAndCleans(): void
    {
        $this->controller->failClose = true;

        try {
            $this->controller->prepareArchive($this->backup);
            self::fail('Expected ZIP close failure.');
        } catch (RuntimeException) {
            self::assertSame(2, $this->controller->closeCalls);
            self::assertFileDoesNotExist($this->controller->lastOwnedPath());
        }
    }

    public function testResponsePreparationFailureCleansFinalizedArchive(): void
    {
        $this->controller->failResponse = true;

        $this->expectException(RuntimeException::class);
        try {
            $this->controller->prepareArchive($this->backup);
        } finally {
            self::assertSame(1, $this->controller->closeCalls);
            self::assertFileDoesNotExist($this->controller->lastOwnedPath());
        }
    }

    public function testInterruptionCleanupIsImmediateExactAndIdempotent(): void
    {
        $this->controller->prepareArchive($this->backup);
        $owned = $this->controller->lastOwnedPath();
        $other = $this->archiveRoot . '/other.zip';
        self::assertNotFalse(file_put_contents($other, 'other request'));

        self::assertCount(1, $this->controller->shutdownCleanups);
        $this->controller->runLastShutdownCleanup();
        $this->controller->runLastShutdownCleanup();

        self::assertFileDoesNotExist($owned);
        self::assertSame('other request', file_get_contents($other));
    }

    /** @return list<string> */
    private function zipNames(\ZipArchive $zip): array
    {
        $names = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            self::assertIsString($name);
            $names[] = $name;
        }
        sort($names, SORT_STRING);
        return $names;
    }
}

/** Controller seam for owned ZIP lifecycle failure injection. */
final class TranslationDownloadLifecycleController extends BackupController
{
    public bool $failOpen = false;
    public ?int $failMemberCall = null;
    public bool $failClose = false;
    public bool $failResponse = false;
    public int $closeCalls = 0;
    /** @var list<string> */
    public array $ownedPaths = [];
    /** @var list<callable(): void> */
    public array $shutdownCleanups = [];
    private int $memberCalls = 0;

    public function __construct(string $id, \yii\base\Module $module, private readonly string $archiveRoot, array $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function prepareArchive(BackupService $backup): Response
    {
        return $this->prepareOwnedBackupDownload(
            $backup,
            'manual/2026-08-19_12-00-00',
            'translation-backup.zip',
        );
    }

    public function lastOwnedPath(): string
    {
        $path = end($this->ownedPaths);
        if (!is_string($path)) {
            throw new RuntimeException('No owned ZIP path was recorded.');
        }
        return $path;
    }

    public function runLastShutdownCleanup(): void
    {
        $cleanup = end($this->shutdownCleanups);
        if (!is_callable($cleanup)) {
            throw new RuntimeException('No shutdown cleanup was recorded.');
        }
        $cleanup();
    }

    protected function createOwnedBackupZipPath(): string
    {
        $path = tempnam($this->archiveRoot, 'owned-download-');
        if (!is_string($path)) {
            throw new RuntimeException('Unable to allocate test-owned ZIP path.');
        }
        $this->ownedPaths[] = $path;
        return $path;
    }

    protected function openBackupZip(\ZipArchive $zip, string $path): bool
    {
        return !$this->failOpen && parent::openBackupZip($zip, $path);
    }

    protected function addBackupZipMember(\ZipArchive $zip, string $name, string $contents): bool
    {
        $this->memberCalls++;
        return $this->failMemberCall !== $this->memberCalls
            && parent::addBackupZipMember($zip, $name, $contents);
    }

    protected function closeBackupZip(\ZipArchive $zip): bool
    {
        $this->closeCalls++;
        if ($this->failClose && $this->closeCalls === 1) {
            return false;
        }
        return parent::closeBackupZip($zip);
    }

    protected function prepareBackupDownloadResponse(string $path, string $filename): Response
    {
        if ($this->failResponse) {
            throw new RuntimeException('Injected response preparation failure.');
        }
        return new Response();
    }

    protected function registerBackupDownloadShutdown(callable $cleanup): void
    {
        $this->shutdownCleanups[] = $cleanup;
    }
}

/** Complete-manifest seam with an injectable read failure. */
final class DownloadManifestBackupService extends BackupService
{
    /** @var array<string, string> */
    public array $files = [];
    public ?Throwable $failure = null;

    public function getDownloadFiles(string $backupName): array
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
        return $this->files;
    }
}
