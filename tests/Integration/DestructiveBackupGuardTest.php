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
use craft\console\Request as ConsoleRequest;
use craft\console\User as ConsoleUser;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\web\Session;
use lindemannrock\translationmanager\console\controllers\BackupController as ConsoleBackupController;
use lindemannrock\translationmanager\console\controllers\TranslationsController as ConsoleTranslationsController;
use lindemannrock\translationmanager\controllers\BackupController;
use lindemannrock\translationmanager\controllers\ImportController;
use lindemannrock\translationmanager\controllers\MaintenanceController;
use lindemannrock\translationmanager\controllers\PhpImportController;
use lindemannrock\translationmanager\controllers\SettingsController;
use lindemannrock\translationmanager\integrations\BaseIntegration;
use lindemannrock\translationmanager\records\ImportHistoryRecord;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\services\GenerationService;
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\services\TranslationsService;
use lindemannrock\translationmanager\tests\Support\BackupManifestTestCase;
use lindemannrock\translationmanager\TranslationManager;
use RuntimeException;
use Throwable;
use yii\console\ExitCode;
use yii\web\Response;

/**
 * Pins required safety backups ahead of every destructive effect family.
 *
 * @since 5.35.0
 */
final class DestructiveBackupGuardTest extends BackupManifestTestCase
{
    private string $backupRoot;
    private GuardTranslationsService $translationsSpy;
    private GuardGenerationService $generationSpy;
    private GuardBackupService $backupSpy;
    private object $originalUser;
    private GuardUser $guardUser;

    protected function setUp(): void
    {
        parent::setUp();
        $relativeRoot = 'translation-manager/' . $this->nextTestMarker('destructive-guard-', 'path');
        $this->backupRoot = Craft::getAlias('@storage/' . $relativeRoot);
        self::assertIsString($this->backupRoot);
        FileHelper::createDirectory($this->backupRoot);
        $this->trackTempPath($this->backupRoot);
        $this->settings()->backupPath = '@storage/' . $relativeRoot;
        $this->settings()->backupVolumeUid = null;
        $this->settings()->backupEnabled = true;
        $this->settings()->backupOnImport = true;

        $this->translationsSpy = new GuardTranslationsService();
        $this->generationSpy = new GuardGenerationService();
        $this->backupSpy = new GuardBackupService();
        $this->replacePluginComponent('translations', $this->translationsSpy);
        $this->replacePluginComponent('generate', $this->generationSpy);
        $this->replacePluginComponent('backup', $this->backupSpy);
        Craft::$app->set('request', new GuardRequest());
        Craft::$app->set('response', new Response());
        $this->originalUser = Craft::$app->getUser();
        $this->guardUser = new GuardUser();
        Craft::$app->set('user', $this->guardUser);
    }

    protected function tearDown(): void
    {
        Craft::$app->set('user', $this->originalUser);
        parent::tearDown();
    }

    public function testInvalidRestoreDoesNotAttemptSafetyBackupOrDestructiveWork(): void
    {
        $result = $this->backupSpy->restoreBackup('manual/not-a-backup');

        self::assertFalse($result['success']);
        self::assertSame([], $this->backupSpy->reasons);
        $this->assertNoDestructiveEffects();
    }

    public function testValidRestoreStopsBeforeDeletionWhenSafetyBackupFails(): void
    {
        $name = $this->writeValidRestoreBackup();
        $this->backupSpy->outcome = GuardBackupService::THROW;

        $result = $this->backupSpy->restoreBackup($name);

        self::assertFalse($result['success']);
        self::assertSame(['before_restore'], $this->backupSpy->reasons);
        $this->assertNoDestructiveEffects();
    }

    public function testValidRestoreAcceptsEmptyNoOpAndBackupsDisabledBehavior(): void
    {
        $name = $this->writeValidRestoreBackup();
        $this->backupSpy->outcome = GuardBackupService::EMPTY;

        $emptyResult = $this->backupSpy->restoreBackup($name);
        self::assertTrue($emptyResult['success']);
        self::assertNull($emptyResult['preRestoreBackup']);
        self::assertSame(1, $this->translationsSpy->deleteAllCalls);
        self::assertSame(1, $this->generationSpy->generateAllCalls);

        $this->translationsSpy->deleteAllCalls = 0;
        $this->generationSpy->generateAllCalls = 0;
        $this->backupSpy->reasons = [];
        $this->settings()->backupEnabled = false;
        $this->backupSpy->outcome = GuardBackupService::THROW;

        $disabledResult = $this->backupSpy->restoreBackup($name);
        self::assertTrue($disabledResult['success']);
        self::assertSame([], $this->backupSpy->reasons);
        self::assertSame(1, $this->translationsSpy->deleteAllCalls);
        self::assertSame(1, $this->generationSpy->generateAllCalls);
    }

    public function testPhpImportAndConsolePhpImportAbortBeforeImportRegistrationAndGeneration(): void
    {
        $this->backupSpy->outcome = GuardBackupService::THROW;
        $this->translationsSpy->categoryStatus = ['requiresRegistration' => true, 'canAutoRegister' => true];
        Craft::$app->set('request', new GuardRequest([
            'translations' => [['key' => 'Source', 'value' => 'Translation']],
            'language' => 'en',
            'category' => 'guard-category',
            'file' => 'en/guard-category.php',
            'createBackup' => true,
        ]));

        $response = (new PhpImportController('php-import', TranslationManager::getInstance()))->actionImport();
        self::assertIsArray($response->data);
        self::assertFalse($response->data['success']);
        self::assertSame(['before_php_import'], $this->backupSpy->reasons);
        self::assertSame(0, $this->translationsSpy->registerCalls);
        self::assertSame(0, $this->translationsSpy->importCalls);
        self::assertSame(0, $this->generationSpy->autoGenerateCalls);

        $this->backupSpy->reasons = [];
        $generated = $this->createConsoleImportFile();
        $controller = new ConsoleTranslationsController('translations', TranslationManager::getInstance());
        $controller->language = $generated['language'];
        $controller->category = $generated['category'];

        self::assertSame(ExitCode::UNSPECIFIED_ERROR, $controller->actionImport());
        self::assertSame(['before_php_import'], $this->backupSpy->reasons);
        self::assertSame(0, $this->translationsSpy->registerCalls);
        self::assertSame(0, $this->translationsSpy->importCalls);
    }

    public function testCsvImportBackupFailurePreservesRowsHistoryNoticeAndSessionState(): void
    {
        $this->requireAtLeastOneSite();
        $this->requireLatinSourceLanguage();
        $admin = Craft::$app->getUsers()->getUserById(1);
        self::assertNotNull($admin, 'The integration fixture must provide its canonical user ID 1.');
        $originalIdentity = $this->guardUser->getIdentity();
        $this->guardUser->setIdentity($admin);

        $language = Craft::$app->getSites()->getPrimarySite()->getLanguage();
        $category = $this->settings()->getPrimaryCategory();
        $existingSource = self::MARKER . 'csv_guard_existing_' . bin2hex(random_bytes(4));
        $newSource = self::MARKER . 'csv_guard_new_' . bin2hex(random_bytes(4));
        $filename = self::MARKER . 'csv_guard_' . bin2hex(random_bytes(4)) . '.csv';
        $existing = $this->translationsSpy->createOrUpdateTranslation($existingSource, 'site.csv-guard', $category);
        self::assertNotNull($existing);
        $existingRows = $this->fetchRowsForSource($existingSource);
        self::assertNotSame([], $existingRows);

        $importData = [
            'headers' => ['translationKey', 'translation'],
            'allRows' => [[$existingSource, 'updated value'], [$newSource, 'new value']],
            'rowCount' => 2,
            'delimiter' => ',',
            'filename' => $filename,
            'filesize' => 128,
            'createBackup' => true,
        ];
        $previewData = [
            'summary' => ['totalRows' => 2, 'toImport' => 1, 'toUpdate' => 1],
            'toImport' => [$this->csvPreviewRow($newSource, 'new value', $language, $category, 2)],
            'toUpdate' => [$this->csvPreviewRow($existingSource, 'updated value', $language, $category, 3)],
            'unchanged' => [],
            'malicious' => [],
            'errors' => [],
            'createBackup' => true,
        ];
        $session = new GuardSession();
        self::assertFalse($session->has('translation-import'));
        self::assertFalse($session->has('translation-preview'));
        $session->set('translation-import', $importData);
        $session->set('translation-preview', $previewData);
        $this->backupSpy->outcome = GuardBackupService::THROW;
        $controller = new GuardCsvImportController('import', TranslationManager::getInstance(), $session);

        try {
            self::assertSame(0, (int)ImportHistoryRecord::find()->where(['filename' => $filename])->count());

            $response = $controller->actionIndex();

            self::assertSame(302, $response->getStatusCode());
            self::assertStringContainsString('translation-manager/import-export', (string)$response->getHeaders()->get('Location'));
            self::assertSame(['before_import'], $this->backupSpy->reasons);
            self::assertSame($existingRows, $this->fetchRowsForSource($existingSource));
            self::assertSame([], $this->fetchRowsForSource($newSource));
            self::assertSame(0, $this->generationSpy->autoGenerateCalls);
            self::assertSame(0, (int)ImportHistoryRecord::find()->where(['filename' => $filename])->count());
            self::assertSame($importData, $session->get('translation-import'));
            self::assertSame($previewData, $session->get('translation-preview'));
            self::assertSame('Import failed: Injected required backup failure.', $session->getError());
            self::assertNull($session->getNotice());
        } finally {
            ImportHistoryRecord::deleteAll(['filename' => $filename]);
            $session->remove('translation-import');
            $session->remove('translation-preview');
            $session->clearFlashes();
            self::assertFalse($session->has('translation-import'));
            self::assertFalse($session->has('translation-preview'));
            self::assertSame([], $session->getAllFlashes());
            $this->guardUser->setIdentity($originalIdentity);
        }
    }

    public function testCanonicalVolumeRestoreBackupFailurePreventsEveryDestructiveEffect(): void
    {
        [$root, $delegate] = $this->localFilesystem('guard-volume-restore-');
        $operations = [];
        $filesystem = $this->remoteLikeFilesystem(
            $delegate,
            static function(string $operation, string $path) use (&$operations): void {
                $operations[] = [$operation, $path];
            },
        );
        $this->installVolume($filesystem);

        $name = 'manual/2026-08-19_12-00-00_' . str_repeat('e', 32);
        $source = self::MARKER . 'volume_restore_guard_' . bin2hex(random_bytes(4));
        $formie = '[]';
        $site = Json::encode([[
            'source' => $source,
            'sourceHash' => md5($source),
            'context' => 'site.volume-restore-guard',
            'category' => $this->settings()->getPrimaryCategory(),
            'siteId' => Craft::$app->getSites()->getPrimarySite()->getId(),
            'language' => Craft::$app->getSites()->getPrimarySite()->getLanguage(),
            'translationKey' => $source,
            'translation' => 'restored value',
            'status' => 'translated',
        ]]);
        $files = [
            'formie-translations.json' => $formie,
            'site-translations.json' => $site,
            'metadata.json' => Json::encode([
                'date' => basename($name),
                'timestamp' => 1_755_604_800,
                'reason' => 'manual',
                'translationCount' => 1,
                'checksum' => hash('sha256', $formie . $site),
                'checksumAlgorithm' => 'sha256',
            ]),
        ];
        $canonicalRoot = $root . '/' . self::SUBPATH . '/' . self::VOLUME_ROOT . '/' . $name;
        foreach ($files as $relativePath => $content) {
            $path = $canonicalRoot . '/' . $relativePath;
            FileHelper::createDirectory(dirname($path));
            self::assertNotFalse(file_put_contents($path, $content));
        }
        $targetHashes = array_map('hash', array_fill(0, count($files), 'sha256'), array_values($files));
        $operations = [];
        $this->backupSpy->outcome = GuardBackupService::THROW;

        $result = $this->backupSpy->restoreBackup($name);

        self::assertFalse($result['success']);
        self::assertSame(['before_restore'], $this->backupSpy->reasons);
        $this->assertNoDestructiveEffects();
        self::assertSame([], $this->fetchRowsForSource($source));
        foreach ($files as $relativePath => $content) {
            self::assertSame(hash('sha256', $content), hash_file('sha256', $canonicalRoot . '/' . $relativePath));
        }
        self::assertSame($targetHashes, array_map(
            static fn(string $relativePath): string => hash_file('sha256', $canonicalRoot . '/' . $relativePath),
            array_keys($files),
        ));
        self::assertDirectoryDoesNotExist($this->backupRoot . '/' . $name);
        self::assertSame([], array_values(array_filter(
            $operations,
            static fn(array $operation): bool => str_starts_with(ltrim($operation[1], '/'), self::VOLUME_ROOT . '/'),
        )));
    }

    public function testMaintenanceCleanupAbortsBeforeDeletionAndSuccessResponse(): void
    {
        $this->backupSpy->outcome = GuardBackupService::THROW;
        Craft::$app->set('request', new GuardRequest());

        $response = (new MaintenanceController('maintenance', TranslationManager::getInstance()))->actionCleanUnused();
        $data = $response->data;

        self::assertIsArray($data);
        self::assertFalse($data['success']);
        self::assertSame(['before_cleanup'], $this->backupSpy->reasons);
        self::assertSame(0, $this->translationsSpy->cleanUnusedCalls);
        self::assertSame(0, $this->generationSpy->autoGenerateCalls);
    }

    public function testSiteAllCategoryAndProviderDeletionAbortBeforeTheirEffects(): void
    {
        $this->backupSpy->outcome = GuardBackupService::THROW;
        $category = $this->settings()->getPrimaryCategory();
        $integrationService = new IntegrationService();
        $integrationService->register(GuardProviderIntegration::NAME, new GuardProviderIntegration());
        $this->replacePluginComponent('integrations', $integrationService);

        $actions = [
            ['before_delete_site', [], fn() => (new SettingsController('settings', TranslationManager::getInstance()))->actionDeleteSite()],
            ['before_delete_all', [], fn() => (new SettingsController('settings', TranslationManager::getInstance()))->actionDeleteAll()],
            ["before_delete_{$category}", ['category' => $category], fn() => (new SettingsController('settings', TranslationManager::getInstance()))->actionDeleteCategory()],
            ['before_delete_' . GuardProviderIntegration::NAME, ['provider' => GuardProviderIntegration::NAME], fn() => (new SettingsController('settings', TranslationManager::getInstance()))->actionDeleteProvider()],
        ];

        foreach ($actions as [$reason, $params, $action]) {
            $this->backupSpy->reasons = [];
            Craft::$app->set('request', new GuardRequest($params));
            try {
                $action();
            } catch (Throwable) {
                // Console integration tests have no session; production web
                // requests receive the controller's translated session error.
            }
            self::assertSame([$reason], $this->backupSpy->reasons);
            self::assertSame(0, $this->translationsSpy->deleteSiteCalls);
            self::assertSame(0, $this->translationsSpy->deleteAllCalls);
            self::assertSame(0, $this->translationsSpy->deleteCategoryCalls);
            self::assertSame(0, $this->translationsSpy->deleteProviderCalls);
        }
    }

    public function testManualCpAndConsoleCreationDistinguishEmptyNoOpAndFailure(): void
    {
        $this->backupSpy->outcome = GuardBackupService::EMPTY;
        Craft::$app->set('request', new GuardRequest(['reason' => 'manual']));
        $cpResponse = (new BackupController('backup', TranslationManager::getInstance()))->actionCreate();
        self::assertIsArray($cpResponse->data);
        self::assertTrue($cpResponse->data['success']);
        self::assertTrue($cpResponse->data['isEmpty']);

        $console = new ConsoleBackupController('backup', TranslationManager::getInstance());
        self::assertSame(ExitCode::OK, $console->actionCreate());

        $this->backupSpy->outcome = GuardBackupService::THROW;
        Craft::$app->set('request', new GuardRequest(['reason' => 'manual']));
        $failedCp = (new BackupController('backup', TranslationManager::getInstance()))->actionCreate();
        self::assertFalse($failedCp->data['success']);
        self::assertSame(ExitCode::UNSPECIFIED_ERROR, $console->actionCreate());
    }

    private function writeValidRestoreBackup(): string
    {
        $name = 'manual/2026-08-19_12-00-00';
        $root = $this->backupRoot . '/' . $name;
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
        return $name;
    }

    /** @return array<string, mixed> */
    private function csvPreviewRow(string $source, string $translation, string $language, string $category, int $rowNumber): array
    {
        return [
            'rowNumber' => $rowNumber,
            'translationKey' => $source,
            'translation' => $translation,
            'context' => 'site.csv-guard',
            'category' => $category,
            'language' => $language,
            'type' => 'site',
            'status' => 'translated',
            'origin' => 'import',
            'siteId' => Craft::$app->getSites()->getPrimarySite()->getId(),
        ];
    }

    /** @return array{language: string, category: string} */
    private function createConsoleImportFile(): array
    {
        $this->requireAtLeastOneSite();
        $language = Craft::$app->getSites()->getPrimarySite()->getLanguage();
        $category = self::MARKER . 'guard_console_' . bin2hex(random_bytes(4));
        $path = $this->settings()->getGenerationPath() . "/{$language}/{$category}.php";
        self::assertFileDoesNotExist($path);
        FileHelper::createDirectory(dirname($path));
        self::assertNotFalse(file_put_contents($path, "<?php\nreturn ['Guard source' => 'Guard value'];\n"));
        $this->trackTempPath($path);
        return ['language' => $language, 'category' => $category];
    }

    private function assertNoDestructiveEffects(): void
    {
        self::assertSame(0, $this->translationsSpy->deleteAllCalls);
        self::assertSame(0, $this->translationsSpy->importCalls);
        self::assertSame(0, $this->generationSpy->generateAllCalls);
        self::assertSame(0, $this->generationSpy->autoGenerateCalls);
    }
}

/** Runs the supported CSV action with an isolated request-owned session. */
final class GuardCsvImportController extends ImportController
{
    public function __construct(string $id, $module, private readonly GuardSession $guardSession, array $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    protected function getImportSession(): Session
    {
        return $this->guardSession;
    }
}

/** In-memory session owned exclusively by one CSV guard test. */
final class GuardSession extends Session
{
    /** @var array<string, mixed> */
    private array $values = [];
    /** @var array<string, mixed> */
    private array $flashes = [];

    public function get($key, $defaultValue = null)
    {
        return $this->values[(string)$key] ?? $defaultValue;
    }

    public function set($key, $value): void
    {
        $this->values[(string)$key] = $value;
    }

    public function remove($key)
    {
        $key = (string)$key;
        $value = $this->values[$key] ?? null;
        unset($this->values[$key]);
        return $value;
    }

    public function has($key): bool
    {
        return array_key_exists((string)$key, $this->values);
    }

    public function setNotice(string $message, array $settings = []): void
    {
        $this->flashes['notice'] = $message;
    }

    public function setError(string $message, array $settings = []): void
    {
        $this->flashes['error'] = $message;
    }

    public function getNotice(): ?string
    {
        $notice = $this->flashes['notice'] ?? null;
        return is_string($notice) ? $notice : null;
    }

    public function getError(): ?string
    {
        $error = $this->flashes['error'] ?? null;
        return is_string($error) ? $error : null;
    }

    public function getAllFlashes($delete = false): array
    {
        $flashes = $this->flashes;
        if ($delete) {
            $this->flashes = [];
        }
        return $flashes;
    }

    public function clearFlashes(): void
    {
        $this->flashes = [];
    }
}

/** Backup outcome seam preserving real restore validation and orchestration. */
final class GuardBackupService extends BackupService
{
    public const SUCCESS = 'success';
    public const EMPTY = 'empty';
    public const THROW = 'throw';

    public string $outcome = self::SUCCESS;
    /** @var list<string> */
    public array $reasons = [];

    public function createBackup(?string $reason = null): ?string
    {
        $this->reasons[] = $reason ?? 'manual';
        return match ($this->outcome) {
            self::SUCCESS => '/owned/completed-backup',
            self::EMPTY => null,
            self::THROW => throw new RuntimeException('Injected required backup failure.'),
            default => throw new RuntimeException('Unknown backup outcome.'),
        };
    }
}

/** Destructive-effect and import-registration spy. */
final class GuardTranslationsService extends TranslationsService
{
    public int $deleteAllCalls = 0;
    public int $deleteSiteCalls = 0;
    public int $deleteCategoryCalls = 0;
    public int $deleteProviderCalls = 0;
    public int $cleanUnusedCalls = 0;
    public int $registerCalls = 0;
    public int $importCalls = 0;
    /** @var array<string, mixed> */
    public array $categoryStatus = ['requiresRegistration' => false, 'canAutoRegister' => true];

    public function deleteAllTranslations(): int
    {
        $this->deleteAllCalls++;
        return 0;
    }

    public function deleteSiteTranslations(): int
    {
        $this->deleteSiteCalls++;
        return 0;
    }

    public function deleteCategoryTranslations(string $category): int
    {
        $this->deleteCategoryCalls++;
        return 0;
    }

    public function deleteProviderTranslations(string $provider): int
    {
        $this->deleteProviderCalls++;
        return 0;
    }

    public function cleanUnusedTranslations(): int
    {
        $this->cleanUnusedCalls++;
        return 0;
    }

    public function getImportCategoryStatus(string $category): array
    {
        return $this->categoryStatus;
    }

    public function registerImportCategory(string $category): bool
    {
        $this->registerCalls++;
        return true;
    }

    public function importPhpEntries(array $entries, string $importLanguage, string $category, ?int $userId = null): array
    {
        $this->importCalls++;
        return ['imported' => count($entries), 'updated' => 0, 'errors' => []];
    }
}

/** Prevents guarded actions from writing generated files. */
final class GuardGenerationService extends GenerationService
{
    public int $generateAllCalls = 0;
    public int $autoGenerateCalls = 0;

    public function generateAll(): array
    {
        $this->generateAllCalls++;
        return ['success' => true, 'results' => []];
    }

    public function triggerAutoGenerate(?array $sourceIds = null): bool
    {
        $this->autoGenerateCalls++;
        return true;
    }
}

/** Minimal POST/JSON request with exact owned body parameters. */
final class GuardRequest extends ConsoleRequest
{
    /** @param array<string, mixed> $bodyParams */
    public function __construct(private readonly array $bodyParams = [], array $config = [])
    {
        parent::__construct($config);
    }

    public function getBodyParam($name, $defaultValue = null): mixed
    {
        return $this->bodyParams[$name] ?? $defaultValue;
    }

    public function getRequiredBodyParam(string $name): mixed
    {
        if (!array_key_exists($name, $this->bodyParams)) {
            throw new RuntimeException("Missing required body parameter: {$name}.");
        }
        return $this->bodyParams[$name];
    }

    public function getIsPost(): bool
    {
        return true;
    }

    public function getAcceptsJson(): bool
    {
        return true;
    }

    public function getIsAjax(): bool
    {
        return false;
    }

    public function getIsPjax(): bool
    {
        return false;
    }
}

/** Grants only the action-local permissions exercised by guard tests. */
final class GuardUser extends ConsoleUser
{
    public function checkPermission(string $permissionName): bool
    {
        return true;
    }

    public function getId(): ?int
    {
        return 1;
    }

    public function getIsGuest(): bool
    {
        return false;
    }
}

/** Available forms integration used to reach provider deletion safely. */
final class GuardProviderIntegration extends BaseIntegration
{
    public const NAME = 'guard-provider';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getPluginHandle(): string
    {
        return self::NAME;
    }

    public function getContextPrefix(): string
    {
        return 'guardprovider';
    }

    public function getCategory(): string
    {
        return self::NAME;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function registerHooks(): void
    {
    }

    public function captureTranslations($element): array
    {
        return [];
    }

    public function checkUsage(): void
    {
    }

    public function getSupportedContentTypes(): array
    {
        return [];
    }

    protected function getTranslationType(): string
    {
        return 'forms';
    }
}
