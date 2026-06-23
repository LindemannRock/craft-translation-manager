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
use craft\console\Request as CraftConsoleRequest;
use craft\console\User as CraftConsoleUser;
use lindemannrock\translationmanager\controllers\GenerateController;
use lindemannrock\translationmanager\controllers\MaintenanceController;
use lindemannrock\translationmanager\controllers\SettingsController;
use lindemannrock\translationmanager\controllers\TranslationsController;
use lindemannrock\translationmanager\integrations\BaseIntegration;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\services\GenerationService;
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\services\SourceService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\Attributes\CoversClass;
use yii\base\InlineAction;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

#[CoversClass(GenerateController::class)]
#[CoversClass(MaintenanceController::class)]
#[CoversClass(SettingsController::class)]
#[CoversClass(TranslationsController::class)]
final class SourcePermissionControllerGateTest extends TestCase
{
    private object $originalRequest;
    private object $originalUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalRequest = Craft::$app->get('request');
        $this->originalUser = Craft::$app->get('user');

        $integrationService = new IntegrationService();
        $integrationService->register(
            PermissionGateProviderIntegration::NAME,
            new PermissionGateProviderIntegration(),
        );
        $this->swapPluginComponent('translation-manager', 'integrations', $integrationService);
    }

    protected function tearDown(): void
    {
        Craft::$app->set('request', $this->originalRequest);
        Craft::$app->set('user', $this->originalUser);

        parent::tearDown();
    }

    public function testDeleteCategoryDoesNotFallBackToEditSettings(): void
    {
        $category = $this->primaryCategory();
        $this->installRequest(['category' => $category]);
        $this->installUser(['translationManager:editSettings']);

        $this->expectException(ForbiddenHttpException::class);

        $this->runBeforeAction(new SettingsController('settings', TranslationManager::getInstance()), 'delete-category', 'actionDeleteCategory');
    }

    public function testMaintenanceParentPermissionDoesNotGrantMaintenanceAccess(): void
    {
        $this->installRequest([]);
        $this->installUser(['translationManager:maintenance']);

        $this->expectException(ForbiddenHttpException::class);

        $this->runBeforeAction(new MaintenanceController('maintenance', TranslationManager::getInstance()), 'index', 'actionIndex');
    }

    public function testDeleteUnusedPermissionWithoutMaintenanceDoesNotGrantMaintenanceAccess(): void
    {
        $sourceService = $this->sourceService();
        $this->installRequest([]);
        $this->installUser([$sourceService->getAllPermission(SourceService::ACTION_DELETE_UNUSED)]);

        $this->expectException(ForbiddenHttpException::class);

        $this->runBeforeAction(new MaintenanceController('maintenance', TranslationManager::getInstance()), 'index', 'actionIndex');
    }

    public function testMaintenanceWithDeleteUnusedPermissionGrantsMaintenanceAccess(): void
    {
        $sourceService = $this->sourceService();
        $this->installRequest([]);
        $this->installUser([
            'translationManager:maintenance',
            $sourceService->getAllPermission(SourceService::ACTION_DELETE_UNUSED),
        ]);

        self::assertTrue($this->runBeforeAction(new MaintenanceController('maintenance', TranslationManager::getInstance()), 'index', 'actionIndex'));
    }

    public function testDeleteAllUnusedPermissionDoesNotGrantMaintenanceArtifactCleanup(): void
    {
        $sourceService = $this->sourceService();
        $this->installRequest([]);
        $this->installUser([
            'translationManager:maintenance',
            $sourceService->getAllPermission(SourceService::ACTION_DELETE_UNUSED),
        ]);

        $this->expectException(ForbiddenHttpException::class);

        $this->runBeforeAction(new MaintenanceController('maintenance', TranslationManager::getInstance()), 'clean-categories', 'actionCleanCategories');
    }

    public function testMaintenanceArtifactPermissionGrantsMaintenanceArtifactCleanup(): void
    {
        $this->installRequest([]);
        $this->installUser([
            'translationManager:maintenance',
            'translationManager:cleanMaintenanceArtifacts',
        ]);

        self::assertTrue($this->runBeforeAction(new MaintenanceController('maintenance', TranslationManager::getInstance()), 'clean-categories', 'actionCleanCategories'));
    }

    public function testGenerateParentPermissionDoesNotGrantGenerateAccess(): void
    {
        $this->installRequest([]);
        $this->installUser(['translationManager:generateTranslations']);

        $this->expectException(ForbiddenHttpException::class);

        $this->runBeforeAction(new GenerateController('generate', TranslationManager::getInstance()), 'index', 'actionIndex');
    }

    public function testDeleteCategoryAllowsSourceOrAllPermission(): void
    {
        $category = $this->primaryCategory();
        $sourceService = $this->sourceService();

        $this->installRequest(['category' => $category]);
        $this->installUser([
            'translationManager:maintenance',
            $sourceService->getSourcePermission(SourceService::ACTION_DELETE, $sourceService->categorySourceId($category)),
        ]);
        self::assertTrue($this->runBeforeAction(new SettingsController('settings', TranslationManager::getInstance()), 'delete-category', 'actionDeleteCategory'));

        $this->installRequest(['category' => $category]);
        $this->installUser([
            'translationManager:maintenance',
            $sourceService->getAllPermission(SourceService::ACTION_DELETE),
        ]);
        self::assertTrue($this->runBeforeAction(new SettingsController('settings', TranslationManager::getInstance()), 'delete-category', 'actionDeleteCategory'));
    }

    public function testDeletePermissionWithoutMaintenanceDoesNotGrantDeleteAccess(): void
    {
        $category = $this->primaryCategory();
        $sourceService = $this->sourceService();

        $this->installRequest(['category' => $category]);
        $this->installUser([$sourceService->getSourcePermission(SourceService::ACTION_DELETE, $sourceService->categorySourceId($category))]);

        $this->expectException(ForbiddenHttpException::class);

        $this->runBeforeAction(new SettingsController('settings', TranslationManager::getInstance()), 'delete-category', 'actionDeleteCategory');
    }

    public function testDeleteProviderUsesProviderSourcePermission(): void
    {
        $sourceService = $this->sourceService();
        $this->installRequest(['provider' => PermissionGateProviderIntegration::NAME]);
        $this->installUser([
            'translationManager:maintenance',
            $sourceService->getSourcePermission(SourceService::ACTION_DELETE, $sourceService->providerSourceId(PermissionGateProviderIntegration::NAME)),
        ]);

        self::assertTrue($this->runBeforeAction(new SettingsController('settings', TranslationManager::getInstance()), 'delete-provider', 'actionDeleteProvider'));

        $this->installRequest(['provider' => PermissionGateProviderIntegration::NAME]);
        $this->installUser([
            'translationManager:maintenance',
            $sourceService->getSourcePermission(SourceService::ACTION_DELETE, $sourceService->categorySourceId($this->primaryCategory())),
        ]);

        $this->expectException(ForbiddenHttpException::class);
        $this->runBeforeAction(new SettingsController('settings', TranslationManager::getInstance()), 'delete-provider', 'actionDeleteProvider');
    }

    public function testGenerateGatesUseAllCategoryAndProviderSourcePermissions(): void
    {
        $sourceService = $this->sourceService();
        $category = $this->primaryCategory();

        $this->installRequest([]);
        $this->installUser([$sourceService->getAllPermission(SourceService::ACTION_GENERATE)]);
        self::assertTrue($this->runBeforeAction(new GenerateController('generate', TranslationManager::getInstance()), 'files', 'actionFiles'));

        $this->installRequest(['category' => $category]);
        $this->installUser([$sourceService->getSourcePermission(SourceService::ACTION_GENERATE, $sourceService->categorySourceId($category))]);
        self::assertTrue($this->runBeforeAction(new GenerateController('generate', TranslationManager::getInstance()), 'category-files', 'actionCategoryFiles'));

        $this->installRequest(['provider' => PermissionGateProviderIntegration::NAME]);
        $this->installUser([$sourceService->getSourcePermission(SourceService::ACTION_GENERATE, $sourceService->providerSourceId(PermissionGateProviderIntegration::NAME))]);
        self::assertTrue($this->runBeforeAction(new GenerateController('generate', TranslationManager::getInstance()), 'provider-files', 'actionProviderFiles'));
    }

    public function testCaptureGatesUseAllCategoryAndProviderSourcePermissions(): void
    {
        $sourceService = $this->sourceService();
        $category = $this->primaryCategory();

        $this->installRequest([]);
        $this->installUser([
            'translationManager:maintenance',
            $sourceService->getAllPermission(SourceService::ACTION_CAPTURE),
        ]);
        self::assertTrue($this->runBeforeAction(new MaintenanceController('maintenance', TranslationManager::getInstance()), 'scan-templates-action', 'actionScanTemplatesAction'));

        $this->installRequest(['category' => $category]);
        $this->installUser([
            'translationManager:maintenance',
            $sourceService->getSourcePermission(SourceService::ACTION_CAPTURE, $sourceService->categorySourceId($category)),
        ]);
        self::assertTrue($this->runBeforeAction(new MaintenanceController('maintenance', TranslationManager::getInstance()), 'scan-templates-action', 'actionScanTemplatesAction'));

        $this->installRequest(['provider' => PermissionGateProviderIntegration::NAME]);
        $this->installUser([
            'translationManager:maintenance',
            $sourceService->getSourcePermission(SourceService::ACTION_CAPTURE, $sourceService->providerSourceId(PermissionGateProviderIntegration::NAME)),
        ]);
        self::assertTrue($this->runBeforeAction(new MaintenanceController('maintenance', TranslationManager::getInstance()), 'capture-provider', 'actionCaptureProvider'));
    }

    public function testCleanUnusedTypeUsesAllCategoryAndProviderSourcePermissions(): void
    {
        $sourceService = $this->sourceService();
        $category = $this->primaryCategory();

        $this->installRequest(['type' => 'all']);
        $this->installUser([
            'translationManager:maintenance',
            $sourceService->getAllPermission(SourceService::ACTION_DELETE_UNUSED),
        ]);
        self::assertTrue($this->runBeforeAction(new MaintenanceController('maintenance', TranslationManager::getInstance()), 'clean-unused-type', 'actionCleanUnusedType'));

        $this->installRequest(['type' => 'category:' . $category]);
        $this->installUser([
            'translationManager:maintenance',
            $sourceService->getSourcePermission(SourceService::ACTION_DELETE_UNUSED, $sourceService->categorySourceId($category)),
        ]);
        self::assertTrue($this->runBeforeAction(new MaintenanceController('maintenance', TranslationManager::getInstance()), 'clean-unused-type', 'actionCleanUnusedType'));

        $this->installRequest(['type' => 'provider:' . PermissionGateProviderIntegration::NAME]);
        $this->installUser([
            'translationManager:maintenance',
            $sourceService->getSourcePermission(SourceService::ACTION_DELETE_UNUSED, $sourceService->providerSourceId(PermissionGateProviderIntegration::NAME)),
        ]);
        self::assertTrue($this->runBeforeAction(new MaintenanceController('maintenance', TranslationManager::getInstance()), 'clean-unused-type', 'actionCleanUnusedType'));
    }

    public function testResolveStatusForSaveUsesApproveSourcePermission(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $originalRequireApproval = $settings->requireApproval;
        $settings->requireApproval = true;

        $record = new TranslationRecord();
        $record->category = $this->primaryCategory();
        $record->context = 'site';
        $record->status = 'pending';

        $controller = new TranslationsController('translations', TranslationManager::getInstance());
        $method = new \ReflectionMethod($controller, 'resolveStatusForSave');
        $sourceService = $this->sourceService();

        try {
            $this->installUser([]);
            self::assertSame('draft', $method->invoke($controller, $record, 'Approved text'));

            $this->installUser([$sourceService->getSourcePermission(SourceService::ACTION_APPROVE, $sourceService->categorySourceId($this->primaryCategory()))]);
            self::assertSame('translated', $method->invoke($controller, $record, 'Approved text'));

            $this->installUser([$sourceService->getAllPermission(SourceService::ACTION_APPROVE)]);
            self::assertSame('translated', $method->invoke($controller, $record, 'Approved text'));
        } finally {
            $settings->requireApproval = $originalRequireApproval;
        }
    }

    public function testResolveStatusForSaveHonorsApproveAllWhenSourceIsUnresolved(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $originalRequireApproval = $settings->requireApproval;
        $settings->requireApproval = true;

        $record = new TranslationRecord();
        $record->category = '__missing_category__';
        $record->context = 'site';
        $record->status = 'pending';

        $controller = new TranslationsController('translations', TranslationManager::getInstance());
        $method = new \ReflectionMethod($controller, 'resolveStatusForSave');
        $sourceService = $this->sourceService();

        try {
            $this->installUser([$sourceService->getAllPermission(SourceService::ACTION_APPROVE)]);

            self::assertSame('translated', $method->invoke($controller, $record, 'Approved text'));
        } finally {
            $settings->requireApproval = $originalRequireApproval;
        }
    }

    public function testSetStatusTranslatedUsesApprovePermissionWithoutEditPermission(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $originalRequireApproval = $settings->requireApproval;
        $settings->requireApproval = true;

        $category = $this->primaryCategory();
        $record = $this->seedTranslationRecord($category);
        $record->translation = 'Existing translated text';
        self::assertTrue($record->save(), json_encode($record->getErrors()));
        $sourceService = $this->sourceService();

        try {
            $this->installRequest([
                'ids' => [$record->id],
                'status' => 'translated',
            ]);
            $this->installUser([$sourceService->getAllPermission(SourceService::ACTION_APPROVE)]);

            $response = (new PermissionGateTranslationsController('translations', TranslationManager::getInstance()))->actionSetStatus();

            self::assertSame(200, $response->statusCode);
            self::assertSame(1, $response->data['updated'] ?? null);
            self::assertSame(0, $response->data['skipped'] ?? null);
            self::assertSame('translated', TranslationRecord::findOne($record->id)?->status);
        } finally {
            $settings->requireApproval = $originalRequireApproval;
        }
    }

    public function testSaveDraftDoesNotTriggerAutoGenerate(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $originalRequireApproval = $settings->requireApproval;
        $originalAutoGenerate = $settings->autoGenerate;
        $settings->requireApproval = true;
        $settings->autoGenerate = true;

        $category = $this->primaryCategory();
        $record = $this->seedTranslationRecord($category);
        $generationSpy = new PermissionGateGenerationSpyService();
        $this->swapPluginComponent('translation-manager', 'generate', $generationSpy);
        $sourceService = $this->sourceService();

        try {
            $this->installRequest([
                'id' => $record->id,
                'translation' => 'Draft text',
            ]);
            $this->installUser([$sourceService->getSourcePermission(SourceService::ACTION_EDIT, $sourceService->categorySourceId($category))]);

            $response = (new PermissionGateTranslationsController('translations', TranslationManager::getInstance()))->actionSave();

            self::assertSame(200, $response->statusCode);
            self::assertSame(0, $generationSpy->generateAllCalls);
            self::assertSame([], $generationSpy->generateSourcesCalls);
            self::assertSame('draft', TranslationRecord::findOne($record->id)?->status);
        } finally {
            $settings->requireApproval = $originalRequireApproval;
            $settings->autoGenerate = $originalAutoGenerate;
        }
    }

    public function testSaveTranslatedWithoutGeneratePermissionDoesNotTriggerAutoGenerate(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $originalRequireApproval = $settings->requireApproval;
        $originalAutoGenerate = $settings->autoGenerate;
        $settings->requireApproval = true;
        $settings->autoGenerate = true;

        $category = $this->primaryCategory();
        $record = $this->seedTranslationRecord($category);
        $generationSpy = new PermissionGateGenerationSpyService();
        $this->swapPluginComponent('translation-manager', 'generate', $generationSpy);
        $sourceService = $this->sourceService();

        try {
            $this->installRequest([
                'id' => $record->id,
                'translation' => 'Approved text',
            ]);
            $this->installUser([
                $sourceService->getSourcePermission(SourceService::ACTION_EDIT, $sourceService->categorySourceId($category)),
                $sourceService->getSourcePermission(SourceService::ACTION_APPROVE, $sourceService->categorySourceId($category)),
            ]);

            $response = (new PermissionGateTranslationsController('translations', TranslationManager::getInstance()))->actionSave();

            self::assertSame(200, $response->statusCode);
            self::assertSame(0, $generationSpy->generateAllCalls);
            self::assertSame([], $generationSpy->generateSourcesCalls);
            self::assertSame('translated', TranslationRecord::findOne($record->id)?->status);
        } finally {
            $settings->requireApproval = $originalRequireApproval;
            $settings->autoGenerate = $originalAutoGenerate;
        }
    }

    public function testSaveTranslatedWithGeneratePermissionTriggersAutoGenerateForEditedSourceOnly(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $originalRequireApproval = $settings->requireApproval;
        $originalAutoGenerate = $settings->autoGenerate;
        $settings->requireApproval = true;
        $settings->autoGenerate = true;

        $category = $this->primaryCategory();
        $record = $this->seedTranslationRecord($category);
        $generationSpy = new PermissionGateGenerationSpyService();
        $this->swapPluginComponent('translation-manager', 'generate', $generationSpy);
        $sourceService = $this->sourceService();

        try {
            $this->installRequest([
                'id' => $record->id,
                'translation' => 'Approved text',
            ]);
            $this->installUser([
                $sourceService->getSourcePermission(SourceService::ACTION_EDIT, $sourceService->categorySourceId($category)),
                $sourceService->getSourcePermission(SourceService::ACTION_APPROVE, $sourceService->categorySourceId($category)),
                $sourceService->getSourcePermission(SourceService::ACTION_GENERATE, $sourceService->categorySourceId($category)),
            ]);

            $response = (new PermissionGateTranslationsController('translations', TranslationManager::getInstance()))->actionSave();

            self::assertSame(200, $response->statusCode);
            self::assertSame(0, $generationSpy->generateAllCalls);
            self::assertSame([[$sourceService->categorySourceId($category)]], $generationSpy->generateSourcesCalls);
            self::assertSame('translated', TranslationRecord::findOne($record->id)?->status);
        } finally {
            $settings->requireApproval = $originalRequireApproval;
            $settings->autoGenerate = $originalAutoGenerate;
        }
    }

    /**
     * @param array<string,mixed> $bodyParams
     */
    private function installRequest(array $bodyParams): void
    {
        Craft::$app->set('request', new PermissionGateRequest($bodyParams));
    }

    /**
     * @param string[] $permissions
     */
    private function installUser(array $permissions): void
    {
        Craft::$app->set('user', new PermissionGateUser($permissions));
    }

    private function runBeforeAction(object $controller, string $id, string $method): bool
    {
        if (property_exists($controller, 'enableCsrfValidation')) {
            $controller->enableCsrfValidation = false;
        }

        return $controller->beforeAction(new InlineAction($id, $controller, $method));
    }

    private function primaryCategory(): string
    {
        return TranslationManager::getInstance()->getSettings()->getPrimaryCategory();
    }

    private function sourceService(): SourceService
    {
        /** @var SourceService $sourceService */
        $sourceService = TranslationManager::getInstance()->get('sources');

        return $sourceService;
    }

    private function seedTranslationRecord(string $category): TranslationRecord
    {
        $source = self::MARKER . 'permission_gate_save_' . bin2hex(random_bytes(4));
        $site = Craft::$app->getSites()->getPrimarySite();

        $record = new TranslationRecord();
        $record->source = $source;
        $record->sourceHash = md5($source);
        $record->context = 'site';
        $record->category = $category;
        $record->translationKey = $source;
        $record->translation = null;
        $record->siteId = (int)$site->id;
        $record->language = $site->language;
        $record->status = 'pending';
        $record->translationOrigin = 'system';
        $record->usageCount = 1;

        self::assertTrue($record->save(), json_encode($record->getErrors()));

        return $record;
    }
}

final class PermissionGateTranslationsController extends TranslationsController
{
    public function asJson($data): Response
    {
        return new Response([
            'format' => Response::FORMAT_JSON,
            'data' => $data,
        ]);
    }
}

final class PermissionGateGenerationSpyService extends GenerationService
{
    public int $generateAllCalls = 0;

    /**
     * @var list<array<int,string>>
     */
    public array $generateSourcesCalls = [];

    public function generateAll(): array
    {
        $this->generateAllCalls++;
        return ['success' => true, 'results' => []];
    }

    public function generateSources(array $sourceIds): array
    {
        $this->generateSourcesCalls[] = $sourceIds;
        return [];
    }
}

final class PermissionGateRequest extends CraftConsoleRequest
{
    /**
     * @param array<string,mixed> $bodyParams
     */
    public function __construct(private readonly array $bodyParams, array $config = [])
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
            throw new \yii\web\BadRequestHttpException("Missing required body param: {$name}");
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

    public function getIsOptions(): bool
    {
        return false;
    }

    public function hasValidSiteToken(): bool
    {
        return false;
    }
}

final class PermissionGateUser extends CraftConsoleUser
{
    /**
     * @param string[] $permissions
     */
    public function __construct(private readonly array $permissions, array $config = [])
    {
        parent::__construct($config);
    }

    public function checkPermission(string $permissionName): bool
    {
        return in_array(strtolower($permissionName), array_map('strtolower', $this->permissions), true);
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

final class PermissionGateProviderIntegration extends BaseIntegration
{
    public const NAME = 'permission-gate-provider';
    public const CATEGORY = 'permission-gate-provider';
    public const CONTEXT_PREFIX = 'permissiongate';

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
        return self::CONTEXT_PREFIX;
    }

    public function getCategory(): string
    {
        return self::CATEGORY;
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
