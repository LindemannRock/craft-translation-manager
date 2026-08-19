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
use craft\fs\Local;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\models\Volume;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\services\GenerationService;
use lindemannrock\translationmanager\services\TranslationsService;
use lindemannrock\translationmanager\tests\Support\BackupManifestTestCase;
use lindemannrock\translationmanager\TranslationManager;
use RuntimeException;
use yii\base\UserException;

/**
 * Pins atomic, collision-resistant backup creation and owned failure cleanup.
 *
 * @since 5.36.0
 */
final class BackupCreationSafetyTest extends BackupManifestTestCase
{
    private SnapshotTranslationsService $snapshotTranslations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->snapshotTranslations = new SnapshotTranslationsService();
        $this->replacePluginComponent('translations', $this->snapshotTranslations);
    }

    public function testEmptyStateIsASuccessfulNoOpAndOperationalFailureThrows(): void
    {
        self::assertNull($this->backup()->createBackup('manual'));
        self::assertSame([], $this->backup()->getBackups());

        $this->settings()->backupVolumeUid = 'unavailable-empty-state-volume';
        self::assertNull($this->backup()->createBackup('before_restore'));
        self::assertSame('unavailable-empty-state-volume', $this->settings()->backupVolumeUid);
        $this->settings()->backupVolumeUid = null;

        $this->snapshotTranslations->rows = [$this->translationRow('write failure')];
        $service = new CreationFailureBackupService();
        $service->failLocalWrite = true;
        $this->replacePluginComponent('backup', $service);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Injected local short write.');
        try {
            $service->createBackup('manual');
        } finally {
            self::assertSame([], $this->completedOrStagingDirectories($this->localRoot . '/manual'));
        }
    }

    public function testSameSecondCreationsHaveDistinctCompletedNamesAndNoStagingResidue(): void
    {
        $this->snapshotTranslations->rows = [$this->translationRow('same second')];
        $service = new CreationFailureBackupService();
        $service->fixedTimestamp = 1_755_604_800;
        $this->replacePluginComponent('backup', $service);

        $first = $service->createBackup('manual');
        $second = $service->createBackup('manual');

        self::assertIsString($first);
        self::assertIsString($second);
        self::assertNotSame($first, $second);
        $timestampName = date('Y-m-d_H-i-s', $service->fixedTimestamp);
        self::assertMatchesRegularExpression('/' . preg_quote($timestampName, '/') . '_[a-f0-9]{32}$/', $first);
        self::assertMatchesRegularExpression('/' . preg_quote($timestampName, '/') . '_[a-f0-9]{32}$/', $second);
        self::assertDirectoryExists($first);
        self::assertDirectoryExists($second);
        self::assertCount(2, $service->getBackups());
        self::assertSame([], $this->stagingDirectories($this->localRoot . '/manual'));
    }

    public function testLocalSnapshotMetadataJsonChecksumManifestAndGeneratedFileAreComplete(): void
    {
        $this->snapshotTranslations->rows = [
            $this->translationRow('site row'),
            $this->translationRow('form row', 'formie.form.test'),
        ];
        $generated = $this->createOwnedGeneratedFile();

        $path = $this->backup()->createBackup('manual');

        self::assertIsString($path);
        $name = 'manual/' . basename($path);
        $files = $this->backup()->getDownloadFiles($name);
        self::assertArrayHasKey('metadata.json', $files);
        self::assertArrayHasKey('formie-translations.json', $files);
        self::assertArrayHasKey('site-translations.json', $files);
        $member = 'php-files/' . str_replace('/', '_', $generated['relative']);
        self::assertSame($generated['content'], $files[$member]);

        $metadata = Json::decode($files['metadata.json']);
        self::assertSame(2, $metadata['translationCount']);
        self::assertSame('sha256', $metadata['checksumAlgorithm']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $metadata['backupId']);
        self::assertSame(
            hash('sha256', $files['formie-translations.json'] . $files['site-translations.json']),
            $metadata['checksum'],
        );
        self::assertSame(array_keys($files), array_keys($this->backup()->getDownloadFiles($name)));
        self::assertSame([], $this->stagingDirectories(dirname($path)));
    }

    public function testLocalReadValidationAndPromotionFailuresRemoveOnlyOwnedArtifacts(): void
    {
        $cases = ['generated-read', 'validation', 'promotion', 'final-validation'];
        foreach ($cases as $case) {
            $root = $this->createTrackedTempDirectory('translation-creation-failure-');
            $this->settings()->backupPath = $root;
            $this->snapshotTranslations->rows = [$this->translationRow($case)];
            $service = new CreationFailureBackupService();
            $service->failGeneratedRead = $case === 'generated-read';
            $service->failLocalValidation = $case === 'validation';
            $service->failLocalPromotion = $case === 'promotion';
            $service->failLocalValidationCall = $case === 'final-validation' ? 2 : 0;
            $this->replacePluginComponent('backup', $service);
            if ($case === 'generated-read') {
                $this->createOwnedGeneratedFile();
            }
            $unrelated = $root . '/owner-file.txt';
            self::assertNotFalse(file_put_contents($unrelated, 'preserve'));

            try {
                $service->createBackup('manual');
                self::fail("Expected {$case} failure.");
            } catch (RuntimeException) {
                self::assertSame('preserve', file_get_contents($unrelated));
                self::assertSame([], $this->completedOrStagingDirectories($root . '/manual'));
            }
        }
    }

    public function testCollisionNeverOverwritesAnExistingCompleteBackup(): void
    {
        $this->snapshotTranslations->rows = [$this->translationRow('collision')];
        $service = new CreationFailureBackupService();
        $service->fixedTimestamp = 1_755_604_800;
        $service->entropy = [str_repeat('a', 32), str_repeat('b', 32)];
        $this->replacePluginComponent('backup', $service);
        $existing = $this->localRoot . '/manual/' . date('Y-m-d_H-i-s', $service->fixedTimestamp) . '_' . str_repeat('a', 32);
        FileHelper::createDirectory($existing);
        self::assertNotFalse(file_put_contents($existing . '/metadata.json', '{"owner":"earlier"}'));

        try {
            $service->createBackup('manual');
            self::fail('Expected collision to abort creation.');
        } catch (RuntimeException) {
            self::assertSame('{"owner":"earlier"}', file_get_contents($existing . '/metadata.json'));
            self::assertSame([], $this->stagingDirectories(dirname($existing)));
        }
    }

    public function testCanonicalLocalLikeAndRemoteLikeVolumesPromoteThroughConfiguredSubpath(): void
    {
        foreach ([false, true] as $remoteLike) {
            [$root, $delegate] = $this->localFilesystem($remoteLike ? 'creation-remote-' : 'creation-local-volume-');
            $filesystem = $remoteLike ? $this->remoteLikeFilesystem($delegate) : $delegate;
            $this->installVolume($filesystem);
            $this->snapshotTranslations->rows = [$this->translationRow($remoteLike ? 'remote' : 'local volume')];

            $created = $this->backup()->createBackup('manual');

            self::assertIsString($created);
            self::assertStringStartsWith(self::VOLUME_ROOT . '/manual/', $created);
            self::assertDirectoryExists($root . '/' . self::SUBPATH . '/' . $created);
            self::assertDirectoryDoesNotExist($root . '/' . $created);
            self::assertSame([], $this->stagingDirectories(dirname($root . '/' . self::SUBPATH . '/' . $created)));
            self::assertArrayHasKey('metadata.json', $this->backup()->getDownloadFiles(substr($created, strlen(self::VOLUME_ROOT) + 1)));
        }
    }

    public function testVolumeWriteValidationAndPromotionFailuresCleanOnlyOwnedPaths(): void
    {
        foreach (['write', 'validation', 'promotion', 'final-validation'] as $case) {
            [$root, $delegate] = $this->localFilesystem('creation-volume-failure-');
            $this->installVolume($this->remoteLikeFilesystem($delegate));
            $this->snapshotTranslations->rows = [$this->translationRow('volume ' . $case)];
            $service = new CreationFailureBackupService();
            $service->failVolumeWrite = $case === 'write';
            $service->failVolumeValidation = $case === 'validation';
            $service->failVolumePromotion = $case === 'promotion';
            $service->failVolumeValidationCall = $case === 'final-validation' ? 2 : 0;
            $this->replacePluginComponent('backup', $service);
            $unrelated = $root . '/' . self::SUBPATH . '/owner.txt';
            FileHelper::createDirectory(dirname($unrelated));
            self::assertNotFalse(file_put_contents($unrelated, 'preserve'));

            try {
                $service->createBackup('manual');
                self::fail("Expected volume {$case} failure.");
            } catch (UserException) {
                self::assertSame('preserve', file_get_contents($unrelated));
                $parent = $root . '/' . self::SUBPATH . '/' . self::VOLUME_ROOT . '/manual';
                self::assertSame([], $this->completedOrStagingDirectories($parent));
            }
        }
    }

    public function testLegacyNamesRemainListableDownloadableRestorableAndDeletable(): void
    {
        $name = 'manual/2026-08-19_12-00-00';
        $root = $this->localRoot . '/' . $name;
        FileHelper::createDirectory($root);
        $formie = '[]';
        $site = '[]';
        self::assertNotFalse(file_put_contents($root . '/formie-translations.json', $formie));
        self::assertNotFalse(file_put_contents($root . '/site-translations.json', $site));
        self::assertNotFalse(file_put_contents($root . '/metadata.json', Json::encode([
            'date' => basename($name),
            'timestamp' => 1_755_604_800,
            'reason' => 'manual',
            'translationCount' => 0,
            'checksum' => hash('sha256', $formie . $site),
            'checksumAlgorithm' => 'sha256',
        ])));
        $generation = new CreationRestoreGenerationSpy();
        $this->replacePluginComponent('generate', $generation);
        $this->settings()->backupEnabled = false;

        self::assertTrue($this->backup()->isValidBackupName($name));
        self::assertSame([$name], array_column($this->backup()->getBackups(), 'name'));
        self::assertSame(['formie-translations.json', 'metadata.json', 'site-translations.json'], array_keys($this->backup()->getDownloadFiles($name)));
        self::assertTrue($this->backup()->restoreBackup($name)['success']);
        self::assertSame(1, $this->snapshotTranslations->deleteCalls);
        self::assertSame(1, $generation->generateCalls);
        self::assertTrue($this->backup()->deleteBackup($name));
        self::assertDirectoryDoesNotExist($root);
    }

    public function testStagingNamesAreNeverValidListedDownloadableRestorableOrRetained(): void
    {
        $name = 'manual/.2026-08-19_12-00-00_' . str_repeat('a', 32) . '.staging-' . str_repeat('b', 32);
        $root = $this->localRoot . '/' . $name;
        FileHelper::createDirectory($root);
        self::assertNotFalse(file_put_contents($root . '/metadata.json', '{"timestamp":1,"reason":"scheduled"}'));
        $this->settings()->backupRetentionDays = 1;

        self::assertFalse($this->backup()->isValidBackupName($name));
        self::assertSame([], $this->backup()->getBackups());
        self::assertSame([], $this->backup()->getDownloadFiles($name));
        self::assertFalse($this->backup()->restoreBackup($name)['success']);
        self::assertFalse($this->backup()->deleteBackup($name));
        self::assertSame(0, $this->backup()->cleanupOldBackups());
        self::assertDirectoryExists($root);
    }

    /** @return array<string, mixed> */
    private function translationRow(string $source, string $context = 'site.creation-safety'): array
    {
        return [
            'source' => self::MARKER . str_replace(' ', '_', $source),
            'sourceHash' => hash('sha256', $source),
            'context' => $context,
            'category' => str_starts_with($context, 'formie.') ? 'formie' : 'messages',
            'siteId' => 1,
            'language' => 'en',
            'translationKey' => $source,
            'translation' => $source,
            'status' => 'translated',
        ];
    }

    /** @return array{relative: string, content: string} */
    private function createOwnedGeneratedFile(): array
    {
        $this->requireAtLeastOneSite();
        $language = Craft::$app->getSites()->getPrimarySite()->getLanguage();
        $category = self::MARKER . 'creation_' . bin2hex(random_bytes(4));
        $this->settings()->translationCategory = $category;
        $relative = $language . '/' . $category . '.php';
        $path = $this->settings()->getGenerationPath() . '/' . $relative;
        self::assertFileDoesNotExist($path);
        FileHelper::createDirectory(dirname($path));
        $content = "<?php\nreturn ['Owned creation file' => true];\n";
        self::assertNotFalse(file_put_contents($path, $content));
        $this->trackTempPath($path);
        return ['relative' => $relative, 'content' => $content];
    }

    /** @return list<string> */
    private function stagingDirectories(string $parent): array
    {
        return array_values(array_filter(
            $this->completedOrStagingDirectories($parent),
            static fn(string $name): bool => str_contains($name, '.staging-'),
        ));
    }

    /** @return list<string> */
    private function completedOrStagingDirectories(string $parent): array
    {
        if (!is_dir($parent)) {
            return [];
        }
        $directories = array_map('basename', FileHelper::findDirectories($parent, ['recursive' => false]));
        sort($directories, SORT_STRING);
        return $directories;
    }
}

/** Backup creation seams for deterministic names and operation failures. */
final class CreationFailureBackupService extends BackupService
{
    public ?int $fixedTimestamp = null;
    /** @var list<string> */
    public array $entropy = [];
    public bool $failLocalWrite = false;
    public bool $failGeneratedRead = false;
    public bool $failLocalValidation = false;
    public bool $failLocalPromotion = false;
    public bool $failVolumeWrite = false;
    public bool $failVolumeValidation = false;
    public bool $failVolumePromotion = false;
    public int $failLocalValidationCall = 0;
    public int $failVolumeValidationCall = 0;
    private int $localValidationCalls = 0;
    private int $volumeValidationCalls = 0;

    protected function createBackupTimestamp(): int
    {
        return $this->fixedTimestamp ?? parent::createBackupTimestamp();
    }

    protected function createBackupEntropy(): string
    {
        return array_shift($this->entropy) ?? parent::createBackupEntropy();
    }

    protected function writeLocalBackupFile(string $path, string $content): void
    {
        if ($this->failLocalWrite) {
            if (file_put_contents($path, substr($content, 0, 1)) === false) {
                throw new RuntimeException('Unable to perform injected short write.');
            }
            throw new RuntimeException('Injected local short write.');
        }
        parent::writeLocalBackupFile($path, $content);
    }

    protected function readGeneratedBackupFile(string $path): string
    {
        if ($this->failGeneratedRead) {
            throw new RuntimeException('Injected generated-file read failure.');
        }
        return parent::readGeneratedBackupFile($path);
    }

    protected function validateLocalBackupSnapshot(string $root, array $expectedFiles): void
    {
        $this->localValidationCalls++;
        if ($this->failLocalValidation || $this->failLocalValidationCall === $this->localValidationCalls) {
            throw new RuntimeException('Injected local validation failure.');
        }
        parent::validateLocalBackupSnapshot($root, $expectedFiles);
    }

    protected function promoteLocalBackupDirectory(string $stagingPath, string $finalPath): void
    {
        if ($this->failLocalPromotion) {
            throw new RuntimeException('Injected local promotion failure.');
        }
        parent::promoteLocalBackupDirectory($stagingPath, $finalPath);
    }

    protected function writeVolumeBackupFile(Volume $volume, string $path, string $content): void
    {
        if ($this->failVolumeWrite) {
            throw new RuntimeException('Injected volume write failure.');
        }
        parent::writeVolumeBackupFile($volume, $path, $content);
    }

    protected function validateVolumeBackupSnapshot(Volume $volume, string $root, array $expectedFiles): void
    {
        $this->volumeValidationCalls++;
        if ($this->failVolumeValidation || $this->failVolumeValidationCall === $this->volumeValidationCalls) {
            throw new RuntimeException('Injected volume validation failure.');
        }
        parent::validateVolumeBackupSnapshot($volume, $root, $expectedFiles);
    }

    protected function promoteVolumeBackupDirectory(Volume $volume, string $stagingPath, string $finalName): void
    {
        if ($this->failVolumePromotion) {
            throw new RuntimeException('Injected volume promotion failure.');
        }
        parent::promoteVolumeBackupDirectory($volume, $stagingPath, $finalName);
    }
}

/** Current-state seam used to avoid touching non-test translation rows. */
final class SnapshotTranslationsService extends TranslationsService
{
    /** @var list<array<string, mixed>> */
    public array $rows = [];
    public int $deleteCalls = 0;

    public function getTranslations(array $criteria = []): array
    {
        return $this->rows;
    }

    public function deleteAllTranslations(): int
    {
        $this->deleteCalls++;
        return 0;
    }
}

/** Generation seam preventing restore coverage from writing owner files. */
final class CreationRestoreGenerationSpy extends GenerationService
{
    public int $generateCalls = 0;

    public function generateAll(): array
    {
        $this->generateCalls++;
        return ['success' => true, 'results' => []];
    }
}
