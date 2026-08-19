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
use lindemannrock\translationmanager\console\controllers\BackupController as ConsoleBackupController;
use lindemannrock\translationmanager\console\controllers\TranslationsController as ConsoleTranslationsController;
use lindemannrock\translationmanager\controllers\BackupController;
use lindemannrock\translationmanager\controllers\MaintenanceController;
use lindemannrock\translationmanager\controllers\PhpImportController;
use lindemannrock\translationmanager\controllers\SettingsController;
use lindemannrock\translationmanager\integrations\BaseIntegration;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\services\GenerationService;
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\services\TranslationsService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use RuntimeException;
use Throwable;
use yii\console\ExitCode;
use yii\web\Response;

/**
 * Pins required safety backups ahead of every destructive effect family.
 *
 * @since 5.36.0
 */
final class DestructiveBackupGuardTest extends TestCase
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

    public function testCsvImportGuardPrecedesRowsGenerationHistoryAndSuccessCleanup(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/controllers/ImportController.php');
        self::assertIsString($source);
        $backup = strpos($source, "createBackup('before_import')");
        $import = strpos($source, 'importTranslations($translations, false)');
        $generate = strpos($source, 'triggerAutoGenerate()');
        $history = strpos($source, '$history->save()');
        $success = strpos($source, 'setNotice($message)');

        self::assertIsInt($backup);
        self::assertIsInt($import);
        self::assertIsInt($generate);
        self::assertIsInt($history);
        self::assertIsInt($success);
        self::assertLessThan($import, $backup);
        self::assertLessThan($generate, $import);
        self::assertLessThan($history, $generate);
        self::assertLessThan($success, $history);
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
