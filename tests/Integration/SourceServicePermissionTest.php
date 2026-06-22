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
            'messages',
            static fn(string $permission): bool => $permission === $allPermission,
        ));
    }

    public function testIndividualSourcePermissionDoesNotLeakAcrossSources(): void
    {
        /** @var SourceService $sourceService */
        $sourceService = TranslationManager::getInstance()->get('sources');
        $messagesPermission = $sourceService->getSourcePermission(SourceService::ACTION_GENERATE, 'messages');

        self::assertTrue($sourceService->hasPermission(
            SourceService::ACTION_GENERATE,
            'messages',
            static fn(string $permission): bool => $permission === $messagesPermission,
        ));
        self::assertFalse($sourceService->hasPermission(
            SourceService::ACTION_GENERATE,
            'emails',
            static fn(string $permission): bool => $permission === $messagesPermission,
        ));
    }

    public function testDeleteSourcePermissionUsesDeletePermissionName(): void
    {
        /** @var SourceService $sourceService */
        $sourceService = TranslationManager::getInstance()->get('sources');

        self::assertSame(
            'translationManager:deleteSourceTranslations:messages',
            $sourceService->getSourcePermission(SourceService::ACTION_DELETE, 'messages'),
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
            'translationManager:captureTranslations:messages',
            $sourceService->getSourcePermission(SourceService::ACTION_CAPTURE, 'messages'),
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
        self::assertArrayHasKey($primaryCategory, $sourcesById);
        self::assertArrayHasKey(PermissionTestProviderIntegration::CATEGORY, $sourcesById);
        self::assertTrue($sourcesById[PermissionTestProviderIntegration::CATEGORY]->isProvider());
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
        self::assertSame(PermissionTestProviderIntegration::CATEGORY, $source->id);
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
