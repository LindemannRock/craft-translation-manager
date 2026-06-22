<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\integrations\BaseIntegration;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\services\SourceService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SourceService::class)]
final class SourceServicePermissionTest extends TestCase
{
    public function testAllPermissionOverridesIndividualSourcePermission(): void
    {
        /** @var SourceService $sourceService */
        $sourceService = TranslationManager::getInstance()->get('sources');
        $allPermission = $sourceService->getAllPermission(SourceService::ACTION_DELETE);

        self::assertSame('translationManager:deleteAllSourceTranslations', $allPermission);

        self::assertTrue($sourceService->hasPermission(
            SourceService::ACTION_DELETE,
            $sourceService->categorySourceId('messages'),
            static fn(string $permission): bool => $permission === $allPermission,
        ));
    }

    public function testIndividualSourcePermissionDoesNotLeakAcrossSources(): void
    {
        /** @var SourceService $sourceService */
        $sourceService = TranslationManager::getInstance()->get('sources');
        $messagesPermission = $sourceService->getSourcePermission(
            SourceService::ACTION_GENERATE,
            $sourceService->categorySourceId('messages'),
        );

        self::assertTrue($sourceService->hasPermission(
            SourceService::ACTION_GENERATE,
            $sourceService->categorySourceId('messages'),
            static fn(string $permission): bool => $permission === $messagesPermission,
        ));
        self::assertFalse($sourceService->hasPermission(
            SourceService::ACTION_GENERATE,
            $sourceService->categorySourceId('emails'),
            static fn(string $permission): bool => $permission === $messagesPermission,
        ));
    }

    public function testCategoryAndProviderSourceIdsDoNotCollide(): void
    {
        /** @var SourceService $sourceService */
        $sourceService = TranslationManager::getInstance()->get('sources');

        // A user category named 'formie' must not collide with the Formie provider source (10.5).
        self::assertNotSame(
            $sourceService->categorySourceId('formie'),
            $sourceService->providerSourceId('formie'),
        );
        self::assertSame('category:formie', $sourceService->categorySourceId('formie'));
        self::assertSame('provider:formie', $sourceService->providerSourceId('formie'));
    }

    public function testDeleteSourcePermissionUsesDeletePermissionName(): void
    {
        /** @var SourceService $sourceService */
        $sourceService = TranslationManager::getInstance()->get('sources');

        self::assertSame(
            'translationManager:deleteSourceTranslations:category:messages',
            $sourceService->getSourcePermission(SourceService::ACTION_DELETE, $sourceService->categorySourceId('messages')),
        );
    }

    public function testCapturePermissionsUseTranslationPermissionNames(): void
    {
        /** @var SourceService $sourceService */
        $sourceService = TranslationManager::getInstance()->get('sources');

        self::assertSame(
            'translationManager:captureAllTranslations',
            $sourceService->getAllPermission(SourceService::ACTION_CAPTURE),
        );
        self::assertSame(
            'translationManager:captureTranslations:category:messages',
            $sourceService->getSourcePermission(SourceService::ACTION_CAPTURE, $sourceService->categorySourceId('messages')),
        );
    }

    public function testRegistryIncludesConfiguredAndProviderSources(): void
    {
        TranslationManager::getInstance()->integrations->register(
            PermissionTestProviderIntegration::NAME,
            new PermissionTestProviderIntegration(),
        );

        /** @var SourceService $sourceService */
        $sourceService = TranslationManager::getInstance()->get('sources');
        $sourcesById = [];
        foreach ($sourceService->getAllSources() as $source) {
            $sourcesById[$source->id] = $source;
        }

        $primaryCategory = TranslationManager::getInstance()->getSettings()->getPrimaryCategory();
        $providerSourceId = $sourceService->providerSourceId(PermissionTestProviderIntegration::NAME);
        self::assertArrayHasKey($sourceService->categorySourceId($primaryCategory), $sourcesById);
        self::assertArrayHasKey($providerSourceId, $sourcesById);
        self::assertTrue($sourcesById[$providerSourceId]->isProvider());
    }

    public function testRecordSourceResolutionPrefersProviderContext(): void
    {
        TranslationManager::getInstance()->integrations->register(
            PermissionTestProviderIntegration::NAME,
            new PermissionTestProviderIntegration(),
        );

        $record = new TranslationRecord();
        $record->category = TranslationManager::getInstance()->getSettings()->getPrimaryCategory();
        $record->context = PermissionTestProviderIntegration::CONTEXT_PREFIX . '.fixture.label';

        /** @var SourceService $sourceService */
        $sourceService = TranslationManager::getInstance()->get('sources');
        $source = $sourceService->getSourceForRecord($record);

        self::assertNotNull($source);
        self::assertSame($sourceService->providerSourceId(PermissionTestProviderIntegration::NAME), $source->id);
    }

    public function testRecordSourceResolutionKeepsConfiguredCategoryWhenNameMatchesProvider(): void
    {
        TranslationManager::getInstance()->integrations->register(
            CollisionProviderIntegration::NAME,
            new CollisionProviderIntegration(),
        );

        $settings = TranslationManager::getInstance()->getSettings();
        $originalCategories = $settings->translationCategories;
        $settings->translationCategories = [
            [
                'key' => CollisionProviderIntegration::CATEGORY,
                'enabled' => true,
            ],
        ];

        try {
            $record = new TranslationRecord();
            $record->category = CollisionProviderIntegration::CATEGORY;
            $record->context = 'templates.fixture';

            /** @var SourceService $sourceService */
            $sourceService = TranslationManager::getInstance()->get('sources');
            $source = $sourceService->getSourceForRecord($record);

            self::assertNotNull($source);
            self::assertSame($sourceService->categorySourceId(CollisionProviderIntegration::CATEGORY), $source->id);
        } finally {
            $settings->translationCategories = $originalCategories;
        }
    }
}

final class PermissionTestProviderIntegration extends BaseIntegration
{
    public const NAME = 'permission-test-provider';
    public const CONTEXT_PREFIX = 'permissiontest';
    public const CATEGORY = 'permissiontest';

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

final class CollisionProviderIntegration extends BaseIntegration
{
    public const NAME = 'formie';
    public const CONTEXT_PREFIX = 'formie';
    public const CATEGORY = 'formie';

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
