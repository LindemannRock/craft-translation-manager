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
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\services\GenerationService;
use lindemannrock\translationmanager\tests\Support\BackupManifestTestCase;
use RuntimeException;
use yii\db\Transaction;

/**
 * Pins complete, atomic backup restore behavior for local and volume storage.
 *
 * @since 5.35.0
 */
final class BackupRestoreSafetyTest extends BackupManifestTestCase
{
    private Transaction $catalogueTransaction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalogueTransaction = Craft::$app->getDb()->beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->catalogueTransaction->getIsActive()) {
                $this->catalogueTransaction->rollBack();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testLocalHistoricalProductBackupNormalizesStatusesBeforeAtomicRestore(): void
    {
        $this->seedCurrentTranslation('historical_local_current');
        $historicalFiles = $this->historicalBackupFiles();
        $this->assertHistoricalFixtureProvenance($historicalFiles);
        $this->writeLocalBackup($historicalFiles);
        $output = $this->createOwnedGeneratedOutput('historical-local');
        $generation = new RestoreLifecycleGenerationSpy($output, 'generated-after-historical-local');
        $this->replacePluginComponent('generate', $generation);
        $this->settings()->backupEnabled = true;

        $result = $this->backup()->restoreBackup($this->backupName);

        self::assertTrue($result['success'], $result['message']);
        self::assertSame(2, $result['imported']);
        self::assertSame('Restored 2 translations from backup', $result['message']);
        self::assertIsString($result['preRestoreBackup']);
        self::assertFileExists($output);
        self::assertSame('generated-after-historical-local', file_get_contents($output));
        self::assertSame(1, $generation->generateCalls);
        self::assertSame(1, $generation->transactionLevel);
        $backupRows = $this->decodedBackupRows($historicalFiles);
        $restoredRows = $this->semanticRows($this->catalogueRows());
        self::assertSame(
            [
                $this->expectedHistoricalSemanticRow($backupRows[0], 'translated'),
                $this->expectedHistoricalSemanticRow($backupRows[1], 'draft'),
            ],
            $restoredRows,
        );
        self::assertSame(
            [['status'], ['status']],
            $this->backedUpFieldDifferences($backupRows, $restoredRows),
        );
    }

    public function testLocalRestorePreservesCompleteCurrentProductSemanticsAfterCommit(): void
    {
        $expected = $this->seedSemanticCurrentTranslations('current_local');
        $sources = array_column($expected, 'source');
        $this->settings()->backupEnabled = false;

        $createdPath = $this->backup()->createBackup('manual');
        self::assertIsString($createdPath);
        $name = substr($createdPath, strlen(rtrim($this->localRoot, '/')) + 1);
        $backupRows = $this->currentBackupRows($createdPath . '/site-translations.json', $sources);
        self::assertSame($expected, $this->semanticRows($backupRows));
        $this->mutateSemanticRows($sources);

        $output = $this->createOwnedGeneratedOutput('current-local');
        $generation = new RestoreLifecycleGenerationSpy($output, 'generated-after-current-local');
        $this->replacePluginComponent('generate', $generation);
        $result = $this->backup()->restoreBackup($name);

        self::assertTrue($result['success'], $result['message']);
        self::assertGreaterThanOrEqual(2, $result['imported']);
        self::assertSame("Restored {$result['imported']} translations from backup", $result['message']);
        self::assertSame($expected, $this->semanticRows($this->catalogueRows($sources)));
        self::assertSame(1, $generation->generateCalls);
        self::assertSame(1, $generation->transactionLevel);
        self::assertSame('generated-after-current-local', file_get_contents($output));
    }

    public function testCanonicalVolumeRestorePreservesCompleteCurrentProductSemanticsAfterCommit(): void
    {
        [$volumeRoot, $filesystem] = $this->localFilesystem('restore-current-volume-');
        $this->installVolume($filesystem);
        $expected = $this->seedSemanticCurrentTranslations('current_volume');
        $sources = array_column($expected, 'source');
        $this->settings()->backupEnabled = false;

        $createdPath = $this->backup()->createBackup('manual');
        self::assertIsString($createdPath);
        $name = substr($createdPath, strlen(self::VOLUME_ROOT) + 1);
        self::assertDirectoryExists($volumeRoot . '/' . self::SUBPATH . '/' . $createdPath);
        $backupRows = $this->currentBackupRows(
            $volumeRoot . '/' . self::SUBPATH . '/' . $createdPath . '/site-translations.json',
            $sources,
        );
        self::assertSame($expected, $this->semanticRows($backupRows));
        $this->mutateSemanticRows($sources);

        $output = $this->createOwnedGeneratedOutput('current-volume');
        $generation = new RestoreLifecycleGenerationSpy($output, 'generated-after-current-volume');
        $this->replacePluginComponent('generate', $generation);
        $result = $this->backup()->restoreBackup($name);

        self::assertTrue($result['success'], $result['message']);
        self::assertGreaterThanOrEqual(2, $result['imported']);
        self::assertSame("Restored {$result['imported']} translations from volume backup", $result['message']);
        self::assertSame($expected, $this->semanticRows($this->catalogueRows($sources)));
        self::assertSame(1, $generation->generateCalls);
        self::assertSame(1, $generation->transactionLevel);
        self::assertSame('generated-after-current-volume', file_get_contents($output));
    }

    public function testInvalidPreflightPreservesCatalogueAndGeneratedOutput(): void
    {
        $this->seedCurrentTranslation('invalid_preflight_current');
        $before = $this->catalogueBytes();
        $files = $this->backupFilesForRows([
            array_replace($this->restoreRow('invalid-preflight', 'translated'), [
                'translationOrigin' => 'invalid-origin',
            ]),
        ]);
        $this->writeLocalBackup($files);
        $output = $this->createOwnedGeneratedOutput('invalid-preflight');
        $outputHash = hash_file('sha256', $output);
        $generation = new RestoreLifecycleGenerationSpy($output, 'must-not-be-written');
        $this->replacePluginComponent('generate', $generation);
        $this->settings()->backupEnabled = true;

        $result = $this->backup()->restoreBackup($this->backupName);

        self::assertFalse($result['success']);
        self::assertStringContainsString('failed validation', $result['message']);
        self::assertSame(0, $result['imported']);
        self::assertNull($result['preRestoreBackup']);
        self::assertSame($before, $this->catalogueBytes());
        self::assertSame(0, $generation->generateCalls);
        self::assertSame($outputHash, hash_file('sha256', $output));
        self::assertDirectoryDoesNotExist($this->localRoot . '/maintenance');
    }

    public function testInvalidReviewedAtPreflightPreservesCatalogueWithBackupsDisabled(): void
    {
        $this->seedCurrentTranslation('invalid_reviewed_at_current');
        $before = $this->catalogueBytes();
        $files = $this->backupFilesForRows([
            array_replace($this->restoreRow('invalid-reviewed-at', 'translated'), [
                'translationOrigin' => 'manual',
                'reviewedAt' => 'not-a-date',
            ]),
        ]);
        $this->writeLocalBackup($files);
        $output = $this->createOwnedGeneratedOutput('invalid-reviewed-at');
        $outputHash = hash_file('sha256', $output);
        $generation = new RestoreLifecycleGenerationSpy($output, 'must-not-be-written');
        $this->replacePluginComponent('generate', $generation);
        $this->settings()->backupEnabled = false;

        $result = $this->backup()->restoreBackup($this->backupName);

        self::assertFalse($result['success']);
        self::assertStringContainsString('reviewedAt', $result['message']);
        self::assertSame(0, $result['imported']);
        self::assertNull($result['preRestoreBackup']);
        self::assertSame($before, $this->catalogueBytes());
        self::assertSame(0, $generation->generateCalls);
        self::assertSame($outputHash, hash_file('sha256', $output));
    }

    public function testMidInsertFailureRollsBackCanonicalVolumeRestore(): void
    {
        [$volumeRoot, $filesystem] = $this->localFilesystem('restore-failure-volume-');
        $this->installVolume($filesystem);
        $this->seedCurrentTranslation('mid_insert_current');
        $before = $this->catalogueBytes();
        $files = $this->backupFilesForRows([
            $this->restoreRow('first-transaction-row', 'translated'),
            $this->restoreRow('second-transaction-row', 'draft'),
        ]);
        $this->writeVolumeBackup($filesystem, self::SUBPATH . '/' . self::VOLUME_ROOT, $files);
        self::assertDirectoryExists($volumeRoot . '/' . self::SUBPATH . '/' . self::VOLUME_ROOT . '/' . $this->backupName);
        $this->settings()->backupEnabled = false;
        $output = $this->createOwnedGeneratedOutput('mid-insert');
        $outputHash = hash_file('sha256', $output);
        $generation = new RestoreLifecycleGenerationSpy($output, 'must-not-be-written');
        $service = new FailingRestorePersistenceBackupService();
        $service->failOnPersistenceCall = 2;
        $this->replacePluginComponent('backup', $service);
        $this->replacePluginComponent('generate', $generation);

        $result = $service->restoreBackup($this->backupName);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Injected restore persistence failure.', $result['message']);
        self::assertSame(0, $result['imported']);
        self::assertSame($before, $this->catalogueBytes());
        self::assertSame(0, $generation->generateCalls);
        self::assertSame($outputHash, hash_file('sha256', $output));
    }

    public function testSafetyBackupFailureAbortsBeforeReplacement(): void
    {
        $this->seedCurrentTranslation('safety_backup_current');
        $before = $this->catalogueBytes();
        $this->writeLocalBackup($this->historicalBackupFiles());
        $output = $this->createOwnedGeneratedOutput('safety-backup');
        $outputHash = hash_file('sha256', $output);
        $generation = new RestoreLifecycleGenerationSpy($output, 'must-not-be-written');
        $service = new FailingRestoreSafetyBackupService();
        $this->replacePluginComponent('backup', $service);
        $this->replacePluginComponent('generate', $generation);
        $this->settings()->backupEnabled = true;

        $result = $service->restoreBackup($this->backupName);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Injected safety backup failure.', $result['message']);
        self::assertSame($before, $this->catalogueBytes());
        self::assertSame(0, $generation->generateCalls);
        self::assertSame($outputHash, hash_file('sha256', $output));
    }

    public function testGenerationFailureIsReportedAfterCommittedReplacement(): void
    {
        $this->seedCurrentTranslation('generation_failure_current');
        $this->writeLocalBackup($this->historicalBackupFiles());
        $output = $this->createOwnedGeneratedOutput('generation-failure');
        $outputHash = hash_file('sha256', $output);
        $generation = new RestoreLifecycleGenerationSpy($output, 'must-not-be-written');
        $generation->succeed = false;
        $this->replacePluginComponent('generate', $generation);
        $this->settings()->backupEnabled = false;

        $result = $this->backup()->restoreBackup($this->backupName);

        self::assertFalse($result['success']);
        self::assertSame(Craft::t('translation-manager', 'Failed to generate translation files.'), $result['message']);
        self::assertSame(2, $result['imported']);
        self::assertCount(2, $this->catalogueRows());
        self::assertSame(1, $generation->generateCalls);
        self::assertSame(1, $generation->transactionLevel);
        self::assertSame($outputHash, hash_file('sha256', $output));
    }

    /** @return array<string, string> */
    private function historicalBackupFiles(): array
    {
        $root = dirname(__DIR__) . '/Fixtures/Backups/product-generated-historical-statuses';
        $files = [];
        foreach (['metadata.json', 'formie-translations.json', 'site-translations.json'] as $name) {
            $content = file_get_contents($root . '/' . $name);
            self::assertIsString($content);
            $files[$name] = $content;
        }

        return $files;
    }

    /** @param array<string, string> $files */
    private function assertHistoricalFixtureProvenance(array $files): void
    {
        $metadata = Json::decode($files['metadata.json']);
        self::assertSame('5.21.3', $metadata['pluginVersion']);
        self::assertSame('sha256', $metadata['checksumAlgorithm']);
        self::assertSame(
            hash('sha256', $files['formie-translations.json'] . $files['site-translations.json']),
            $metadata['checksum'],
        );
        self::assertSame('ffaa31c2fde5379350cb74ae56ea2fbda13c06dbff2f8879430bc14b5d467dd4', hash('sha256', $files['formie-translations.json']));
        self::assertSame('0436624366900fa17b63ba8fd8cdd788d24a62cd693e3c0ada2fb7dade171710', hash('sha256', $files['site-translations.json']));

        $provenance = file_get_contents(dirname(__DIR__) . '/Fixtures/Backups/product-generated-historical-statuses/PROVENANCE.md');
        self::assertIsString($provenance);
        self::assertStringContainsString('1cf468858d68862925115febf531fdcb7fa07844', $provenance);
        self::assertStringContainsString('BackupService::_createLocalBackup()', $provenance);
    }

    /** @param array<string, string> $files @return list<array<string, mixed>> */
    private function decodedBackupRows(array $files): array
    {
        return [
            ...Json::decode($files['formie-translations.json']),
            ...Json::decode($files['site-translations.json']),
        ];
    }

    /** @param array<string, mixed> $backupRow @return array<string, mixed> */
    private function expectedHistoricalSemanticRow(array $backupRow, string $normalizedStatus): array
    {
        $backupRow['status'] = $normalizedStatus;
        $backupRow['translationOrigin'] = 'system';
        $backupRow['createdByUserId'] = null;
        $backupRow['reviewedByUserId'] = null;
        $backupRow['reviewedAt'] = null;
        return $this->semanticRow($backupRow);
    }

    /**
     * @param list<array<string, mixed>> $backupRows
     * @param list<array<string, mixed>> $restoredRows
     * @return list<list<string>>
     */
    private function backedUpFieldDifferences(array $backupRows, array $restoredRows): array
    {
        $differences = [];
        foreach ($backupRows as $index => $backupRow) {
            unset($backupRow['id']);
            $actual = array_intersect_key($restoredRows[$index], $backupRow);
            $differences[] = array_keys(array_diff_assoc($actual, $backupRow));
        }
        return $differences;
    }

    /** @param list<string> $sources @return list<array<string, mixed>> */
    private function currentBackupRows(string $path, array $sources): array
    {
        $content = file_get_contents($path);
        self::assertIsString($content);
        $rows = array_values(array_filter(
            Json::decode($content),
            static fn(array $row): bool => in_array($row['source'] ?? null, $sources, true),
        ));
        usort($rows, static fn(array $left, array $right): int => strcmp((string)$left['source'], (string)$right['source']));
        self::assertCount(count($sources), $rows);
        return $rows;
    }

    /** @param list<array<string, mixed>> $rows @return array<string, string> */
    private function backupFilesForRows(array $rows): array
    {
        $site = Json::encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return [
            'metadata.json' => Json::encode([
                'date' => basename($this->backupName),
                'timestamp' => time(),
                'reason' => 'manual',
                'translationCount' => count($rows),
                'checksum' => hash('sha256', $site),
                'checksumAlgorithm' => 'sha256',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'site-translations.json' => $site,
        ];
    }

    /** @return array<string, mixed> */
    private function restoreRow(string $suffix, string $status, ?string $sourceHash = null): array
    {
        $source = self::MARKER . $suffix;
        $now = Db::prepareDateForDb(DateTimeHelper::toDateTime('2026-08-20 12:00:00'));
        return [
            'source' => $source,
            'sourceHash' => $sourceHash ?? md5($source),
            'context' => 'site.restore-safety',
            'category' => 'messages',
            'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
            'language' => Craft::$app->getSites()->getPrimarySite()->language,
            'translationKey' => $source,
            'translation' => 'restored ' . $suffix,
            'status' => $status,
            'translationOrigin' => 'system',
            'createdByUserId' => null,
            'reviewedByUserId' => null,
            'reviewedAt' => null,
            'usageCount' => 1,
            'lastUsed' => $now,
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid' => StringHelper::UUID(),
        ];
    }

    private function seedCurrentTranslation(string $suffix): string
    {
        $data = $this->restoreRow($suffix, 'translated');
        $record = new TranslationRecord();
        $record->setAttributes($data, false);
        $record->translation = 'current translation';
        self::assertTrue($record->save());
        return (string)$record->source;
    }

    /** @return list<array<string, mixed>> */
    private function seedSemanticCurrentTranslations(string $prefix): array
    {
        $reviewedAt = Db::prepareDateForDb(DateTimeHelper::toDateTime('2026-08-21 13:14:15'));
        $rows = [
            array_replace($this->restoreRow($prefix . '_ai', 'draft'), [
                'translation' => 'current AI translation',
                'translationOrigin' => 'ai',
                'createdByUserId' => 72_001,
                'reviewedByUserId' => 72_002,
                'reviewedAt' => $reviewedAt,
                'usageCount' => 8,
            ]),
            array_replace($this->restoreRow($prefix . '_manual', 'translated'), [
                'translation' => 'current manual translation',
                'translationOrigin' => 'manual',
                'createdByUserId' => 71_001,
                'reviewedByUserId' => 71_002,
                'reviewedAt' => $reviewedAt,
                'usageCount' => 9,
            ]),
        ];

        foreach ($rows as $data) {
            $record = new TranslationRecord();
            $record->setAttributes($data, false);
            self::assertTrue($record->save());
        }

        return $this->semanticRows($rows);
    }

    /** @param list<string> $sources */
    private function mutateSemanticRows(array $sources): void
    {
        Craft::$app->getDb()->createCommand()
            ->update(TranslationRecord::tableName(), [
                'translation' => 'mutated after backup',
                'status' => 'pending',
                'translationOrigin' => 'system',
                'createdByUserId' => null,
                'reviewedByUserId' => null,
                'reviewedAt' => null,
                'usageCount' => 0,
            ], ['source' => $sources])
            ->execute();
    }

    /** @return list<array<string, mixed>> */
    private function catalogueRows(?array $sources = null): array
    {
        $query = (new Query())
            ->from(TranslationRecord::tableName())
            ->orderBy(['source' => SORT_ASC, 'language' => SORT_ASC, 'category' => SORT_ASC, 'id' => SORT_ASC]);
        if ($sources !== null) {
            $query->andWhere(['source' => $sources]);
        }
        return $query->all();
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function semanticRows(array $rows): array
    {
        return array_map($this->semanticRow(...), $rows);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function semanticRow(array $row): array
    {
        return [
            'source' => $row['source'],
            'sourceHash' => $row['sourceHash'],
            'context' => $row['context'],
            'category' => $row['category'],
            'siteId' => $row['siteId'],
            'language' => $row['language'],
            'translationKey' => $row['translationKey'],
            'translation' => $row['translation'],
            'status' => $row['status'],
            'translationOrigin' => $row['translationOrigin'],
            'createdByUserId' => $row['createdByUserId'],
            'reviewedByUserId' => $row['reviewedByUserId'],
            'reviewedAt' => $row['reviewedAt'],
            'usageCount' => $row['usageCount'],
            'lastUsed' => $row['lastUsed'],
            'dateCreated' => $row['dateCreated'],
            'dateUpdated' => $row['dateUpdated'],
            'uid' => $row['uid'],
        ];
    }

    private function catalogueBytes(): string
    {
        return Json::encode((new Query())
            ->from(TranslationRecord::tableName())
            ->orderBy(['id' => SORT_ASC])
            ->all());
    }

    private function createOwnedGeneratedOutput(string $suffix): string
    {
        $path = $this->settings()->getGenerationPath() . '/' . self::MARKER . $suffix . '.php';
        FileHelper::createDirectory(dirname($path));
        self::assertNotFalse(file_put_contents($path, 'generated-before-restore'));
        $this->trackTempPath($path);
        return $path;
    }
}

/**
 * Injects a deterministic failure after at least one replacement row is inserted.
 *
 * @since 5.35.0
 */
final class FailingRestorePersistenceBackupService extends BackupService
{
    public int $failOnPersistenceCall = 0;
    private int $persistenceCalls = 0;

    protected function persistRestoreRecord(TranslationRecord $translation): bool
    {
        $this->persistenceCalls++;
        if ($this->persistenceCalls === $this->failOnPersistenceCall) {
            throw new RuntimeException('Injected restore persistence failure.');
        }

        return parent::persistRestoreRecord($translation);
    }
}

/**
 * Injects a fail-closed safety-backup failure before catalogue replacement.
 *
 * @since 5.35.0
 */
final class FailingRestoreSafetyBackupService extends BackupService
{
    public function createBackup(?string $reason = null): ?string
    {
        if ($reason === 'before_restore') {
            throw new RuntimeException('Injected safety backup failure.');
        }

        return parent::createBackup($reason);
    }
}

/**
 * Observes generation timing without writing any non-test translation file.
 *
 * @since 5.35.0
 */
final class RestoreLifecycleGenerationSpy extends GenerationService
{
    public int $generateCalls = 0;
    public ?int $transactionLevel = null;
    public bool $succeed = true;

    public function __construct(private readonly string $outputPath, private readonly string $successContent)
    {
        parent::__construct();
    }

    public function generateAll(): array
    {
        $this->generateCalls++;
        $this->transactionLevel = Craft::$app->getDb()->getTransaction()?->getLevel() ?? 0;
        if ($this->succeed) {
            if (file_put_contents($this->outputPath, $this->successContent) === false) {
                throw new RuntimeException('Unable to write owned generation fixture.');
            }
        }

        return ['success' => $this->succeed, 'results' => []];
    }
}
