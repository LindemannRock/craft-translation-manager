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
use craft\elements\User;
use craft\helpers\Json;
use lindemannrock\translationmanager\controllers\BackupController;
use lindemannrock\translationmanager\jobs\CreateBackupJob;
use lindemannrock\translationmanager\tests\Support\BackupManifestTestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\Attributes\DataProvider;
use yii\web\Response;

/**
 * Pins backup creator attribution at storage and presentation boundaries.
 *
 * @since 5.35.0
 */
final class BackupAttributionTest extends BackupManifestTestCase
{
    private const HISTORICAL_USER = 'historical-queue-user@example.test';
    private const HISTORICAL_USER_ID = 4242;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireLatinSourceLanguage();
        self::assertNotNull($this->translations->createOrUpdateTranslation(
            self::MARKER . 'backup_attribution_' . bin2hex(random_bytes(4)),
            'site.backup-attribution',
        ));
    }

    public function testAuthenticatedQueueExecutionStoresScheduledBackupAsSystemLocally(): void
    {
        $this->actingAs($this->createAttributionUser());

        $this->executeScheduledJob();

        $backups = $this->backup()->getBackups();
        self::assertCount(1, $backups);
        $metadataContent = file_get_contents($backups[0]['path'] . '/metadata.json');
        self::assertIsString($metadataContent);
        $metadata = $this->decodeMetadata($metadataContent);
        self::assertSame('scheduled', $metadata['reason']);
        self::assertSame('system', $metadata['user']);
        self::assertNull($metadata['userId']);
        self::assertSame($metadataContent, $this->backup()->getDownloadFiles($backups[0]['name'])['metadata.json']);
        self::assertSame(
            $metadata['checksum'],
            hash(
                'sha256',
                ($this->backup()->getDownloadFiles($backups[0]['name'])['formie-translations.json'] ?? '')
                    . ($this->backup()->getDownloadFiles($backups[0]['name'])['site-translations.json'] ?? ''),
            ),
        );
        $presented = $this->presentedBackup();
        self::assertSame(Craft::t('translation-manager', 'System'), $presented['user']);
        self::assertNull($presented['userId']);
    }

    public function testAuthenticatedQueueExecutionStoresScheduledBackupAsSystemOnCanonicalVolume(): void
    {
        [$volumeRoot, $filesystem] = $this->localFilesystem('translation-attribution-volume-');
        $this->installVolume($filesystem);
        $this->actingAs($this->createAttributionUser());

        $this->executeScheduledJob();

        $backups = $this->backup()->getBackups();
        self::assertCount(1, $backups);
        self::assertSame('canonical-volume', $backups[0]['storageType']);
        $metadataPath = $volumeRoot . '/' . self::SUBPATH . '/' . self::VOLUME_ROOT . '/' . $backups[0]['name'] . '/metadata.json';
        $metadataContent = file_get_contents($metadataPath);
        self::assertIsString($metadataContent);
        $metadata = $this->decodeMetadata($metadataContent);
        self::assertSame('scheduled', $metadata['reason']);
        self::assertSame('system', $metadata['user']);
        self::assertNull($metadata['userId']);
        self::assertSame($metadataContent, $this->backup()->getDownloadFiles($backups[0]['name'])['metadata.json']);
    }

    public function testAnonymousScheduledExecutionStoresTheSystemFallback(): void
    {
        self::assertNull(Craft::$app->getUser()->getIdentity());

        $this->executeScheduledJob();

        $backups = $this->backup()->getBackups();
        self::assertCount(1, $backups);
        $metadataContent = file_get_contents($backups[0]['path'] . '/metadata.json');
        self::assertIsString($metadataContent);
        $metadata = $this->decodeMetadata($metadataContent);
        self::assertSame('system', $metadata['user']);
        self::assertNull($metadata['userId']);
    }

    #[DataProvider('nonScheduledReasonProvider')]
    public function testNonScheduledProducersPreserveTheirAuthenticatedCreator(?string $reason, string $storedReason): void
    {
        $user = $this->createAttributionUser();
        $this->actingAs($user);

        $path = $this->backup()->createBackup($reason);

        self::assertIsString($path);
        self::assertStringContainsString('/' . $this->expectedFolder($storedReason) . '/', $path);
        $metadataContent = file_get_contents($path . '/metadata.json');
        self::assertIsString($metadataContent);
        $metadata = $this->decodeMetadata($metadataContent);
        self::assertSame($storedReason, $metadata['reason']);
        self::assertSame($user->username, $metadata['user']);
        self::assertSame($user->id, $metadata['userId']);
    }

    public function testAnonymousNonScheduledBackupRetainsTheSystemFallback(): void
    {
        self::assertNull(Craft::$app->getUser()->getIdentity());

        $path = $this->backup()->createBackup('manual');

        self::assertIsString($path);
        $metadataContent = file_get_contents($path . '/metadata.json');
        self::assertIsString($metadataContent);
        $metadata = $this->decodeMetadata($metadataContent);
        self::assertSame('manual', $metadata['reason']);
        self::assertSame('system', $metadata['user']);
        self::assertNull($metadata['userId']);
    }

    public function testHistoricalScheduledAttributionIsNormalizedWithoutMutatingLocalMetadata(): void
    {
        $this->backupName = 'scheduled/2026-08-30_09-18-27';
        $metadataContent = $this->historicalFiles()['metadata.json'];
        $metadataPath = $this->writeLocalBackup($this->historicalFiles()) . '/metadata.json';
        $metadataHash = hash_file('sha256', $metadataPath);

        $presented = $this->presentedBackup();

        self::assertSame(Craft::t('translation-manager', 'System'), $presented['user']);
        self::assertNull($presented['userId']);
        self::assertSame('local', $presented['storageType']);
        self::assertSame($metadataHash, hash_file('sha256', $metadataPath));
        self::assertSame($metadataContent, file_get_contents($metadataPath));
    }

    public function testHistoricalScheduledAttributionIsNormalizedWithoutMutatingVolumeMetadata(): void
    {
        $this->backupName = 'scheduled/2026-08-30_09-18-27';
        [$volumeRoot, $filesystem] = $this->localFilesystem('translation-historical-attribution-volume-');
        $this->installVolume($filesystem);
        $files = $this->historicalFiles();
        $metadataContent = $files['metadata.json'];
        $this->writeVolumeBackup($filesystem, self::SUBPATH . '/' . self::VOLUME_ROOT, $files);
        $metadataPath = $volumeRoot . '/' . self::SUBPATH . '/' . self::VOLUME_ROOT . '/' . $this->backupName . '/metadata.json';
        $metadataHash = hash_file('sha256', $metadataPath);

        $presented = $this->presentedBackup();

        self::assertSame(Craft::t('translation-manager', 'System'), $presented['user']);
        self::assertNull($presented['userId']);
        self::assertSame('canonical-volume', $presented['storageType']);
        self::assertSame($metadataHash, hash_file('sha256', $metadataPath));
        self::assertSame($metadataContent, file_get_contents($metadataPath));
    }

    /** @return iterable<string, array{?string, string}> */
    public static function nonScheduledReasonProvider(): iterable
    {
        yield 'service default' => [null, 'manual'];
        yield 'manual web backup' => ['manual', 'manual'];
        yield 'console backup' => ['console', 'console'];
        yield 'web-supplied reason' => ['custom_web_reason', 'custom_web_reason'];
        yield 'CSV import safety' => ['before_import', 'before_import'];
        yield 'PHP import safety' => ['before_php_import', 'before_php_import'];
        yield 'restore safety' => ['before_restore', 'before_restore'];
        yield 'general cleanup safety' => ['before_cleanup', 'before_cleanup'];
        yield 'type cleanup safety' => ['before_cleanup_Formie', 'before_cleanup_Formie'];
        yield 'language cleanup safety' => ['before_cleanup_languages', 'before_cleanup_languages'];
        yield 'category cleanup safety' => ['before_cleanup_categories', 'before_cleanup_categories'];
        yield 'provider delete safety' => ['before_delete_formie', 'before_delete_formie'];
        yield 'site delete safety' => ['before_delete_site', 'before_delete_site'];
        yield 'all delete safety' => ['before_delete_all', 'before_delete_all'];
        yield 'category delete safety' => ['before_delete_messages', 'before_delete_messages'];
    }

    private function createAttributionUser(): User
    {
        return $this->createTestUser('tm-backup-attribution-', [
            'username' => 'queue-browser-' . bin2hex(random_bytes(4)) . '@example.test',
        ]);
    }

    private function executeScheduledJob(): void
    {
        (new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => false,
        ]))->execute(Craft::$app->getQueue());
    }

    /** @return array<string, mixed> */
    private function presentedBackup(): array
    {
        Craft::$app->set('request', new class() extends \craft\web\Request {
            public function getAcceptsJson(): bool
            {
                return true;
            }
        });
        $controller = new AttributionBackupController('backup', TranslationManager::getInstance());

        $response = $controller->actionGetBackups();

        self::assertIsArray($response->data);
        self::assertTrue($response->data['success']);
        self::assertCount(1, $response->data['backups']);
        return $response->data['backups'][0];
    }

    /** @return array<string, string> */
    private function historicalFiles(): array
    {
        $siteTranslations = Json::encode([
            ['source' => self::MARKER . 'historical_attribution', 'context' => 'site.backup-attribution'],
        ], JSON_PRETTY_PRINT);
        return [
            'metadata.json' => Json::encode([
                'date' => basename($this->backupName),
                'timestamp' => 1_788_070_707,
                'reason' => 'scheduled',
                'user' => self::HISTORICAL_USER,
                'userId' => self::HISTORICAL_USER_ID,
                'translationCount' => 1,
                'checksum' => hash('sha256', $siteTranslations),
                'checksumAlgorithm' => 'sha256',
            ], JSON_PRETTY_PRINT),
            'site-translations.json' => $siteTranslations,
        ];
    }

    /** @return array<string, mixed> */
    private function decodeMetadata(string $content): array
    {
        $metadata = Json::decode($content);
        self::assertIsArray($metadata);
        return $metadata;
    }

    private function expectedFolder(string $reason): string
    {
        if (str_contains($reason, 'cleanup') || str_contains($reason, 'delete') || str_contains($reason, 'restore')) {
            return 'maintenance';
        }
        if (str_contains($reason, 'import')) {
            return 'imports';
        }
        if (in_array($reason, ['manual', 'console'], true)) {
            return 'manual';
        }
        return 'other';
    }
}

/** Web-response seam for historical attribution output in the console fixture. */
final class AttributionBackupController extends BackupController
{
    public function asJson($data)
    {
        $response = new Response();
        $response->format = Response::FORMAT_JSON;
        $response->data = $data;
        return $response;
    }
}
