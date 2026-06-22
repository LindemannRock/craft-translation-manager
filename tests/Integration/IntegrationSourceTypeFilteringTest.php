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
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\services\SourceService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use lindemannrock\translationmanager\variables\TranslationManagerVariable;

/**
 * Pins generic source/provider filtering before adding another forms provider.
 *
 * @since 5.26.0
 */
final class IntegrationSourceTypeFilteringTest extends TestCase
{
    public function testRegisteredFormsProviderUsesItsOwnCategoryAndTypeFilters(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        TranslationManager::getInstance()->integrations->register(
            TestFormsProviderIntegration::NAME,
            new TestFormsProviderIntegration(),
        );

        $providerSource = self::MARKER . 'provider_filter_' . bin2hex(random_bytes(4));
        $siteSource = self::MARKER . 'site_filter_' . bin2hex(random_bytes(4));

        $providerCreated = $this->translations->createOrUpdateTranslation(
            $providerSource,
            TestFormsProviderIntegration::CONTEXT_PREFIX . '.fixture.label',
        );
        $siteCreated = $this->translations->createOrUpdateTranslation($siteSource, 'site.fixture');

        self::assertNotNull($providerCreated);
        self::assertNotNull($siteCreated);

        $providerRows = $this->fetchRowsForSource($providerSource);
        self::assertNotEmpty($providerRows);
        foreach ($providerRows as $row) {
            self::assertSame(TestFormsProviderIntegration::CATEGORY, $row['category']);
            self::assertStringStartsWith(TestFormsProviderIntegration::CONTEXT_PREFIX . '.', $row['context']);
        }

        $formsSources = array_column(
            $this->translations->getTranslations(['type' => 'forms', 'allSites' => true]),
            'source',
        );
        self::assertContains($providerSource, $formsSources);
        self::assertNotContains($siteSource, $formsSources);

        $siteSources = array_column(
            $this->translations->getTranslations(['type' => 'site', 'allSites' => true]),
            'source',
        );
        self::assertContains($siteSource, $siteSources);
        self::assertNotContains($providerSource, $siteSources);
    }

    public function testBuiltInFreeformIntegrationMetadataIsRegistered(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');
        $freeformIntegration = $integrationService->get('freeform');

        self::assertNotNull($freeformIntegration);
        self::assertSame('forms', $freeformIntegration->getSourceType());
        self::assertSame('freeform', $freeformIntegration->getContextPrefix());
        self::assertSame('freeform', $freeformIntegration->getCategory());
        self::assertSame('freeform', $integrationService->getCategoryForContext('freeform.contact.label'));

        // Provider actions are gated by source-based permissions keyed on the
        // integration's category (the source id), not legacy provider handles.
        /** @var SourceService $sourceService */
        $sourceService = TranslationManager::getInstance()->get('sources');
        $freeformCategory = $freeformIntegration->getCategory();
        self::assertSame(
            'translationManager:generateSource:freeform',
            $sourceService->getSourcePermission(SourceService::ACTION_GENERATE, $freeformCategory),
        );
        self::assertSame(
            'translationManager:captureTranslations:freeform',
            $sourceService->getSourcePermission(SourceService::ACTION_CAPTURE, $freeformCategory),
        );
        self::assertSame(
            'translationManager:deleteSourceTranslations:freeform',
            $sourceService->getSourcePermission(SourceService::ACTION_DELETE, $freeformCategory),
        );
        self::assertNotSame(
            $sourceService->getSourcePermission(SourceService::ACTION_GENERATE, 'formie'),
            $sourceService->getSourcePermission(SourceService::ACTION_GENERATE, $freeformCategory),
            'Source permission handles must stay source-specific.',
        );

        $providerSource = self::MARKER . 'freeform_provider_' . bin2hex(random_bytes(4));
        $siteSource = self::MARKER . 'freeform_site_' . bin2hex(random_bytes(4));

        $providerCreated = $this->translations->createOrUpdateTranslation($providerSource, 'freeform.contact.label');
        self::assertNotNull($providerCreated, 'Freeform-context source string should create translation rows.');

        $siteCreated = $this->translations->createOrUpdateTranslation($siteSource, 'site.freeform-control');
        self::assertNotNull($siteCreated, 'Site source string should create translation rows.');

        $formsSources = array_column(
            $this->translations->getTranslations(['type' => 'forms', 'allSites' => true]),
            'source',
        );
        self::assertContains($providerSource, $formsSources);
        self::assertNotContains($siteSource, $formsSources);

        $siteSources = array_column(
            $this->translations->getTranslations(['type' => 'site', 'allSites' => true]),
            'source',
        );
        self::assertContains($siteSource, $siteSources);
        self::assertNotContains($providerSource, $siteSources);
    }

    public function testProviderMaintenanceCountsAndDeleteUseIntegrationMetadata(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');
        $integrationService->register(
            TestFormsProviderIntegration::NAME,
            new TestFormsProviderIntegration(),
        );

        $providerSource = self::MARKER . 'provider_maintenance_' . bin2hex(random_bytes(4));
        $siteSource = self::MARKER . 'site_maintenance_' . bin2hex(random_bytes(4));

        $providerCreated = $this->translations->createOrUpdateTranslation(
            $providerSource,
            TestFormsProviderIntegration::CONTEXT_PREFIX . '.fixture.label',
        );
        $siteCreated = $this->translations->createOrUpdateTranslation($siteSource, 'site.fixture');

        self::assertNotNull($providerCreated);
        self::assertNotNull($siteCreated);

        \Craft::$app->getDb()->createCommand()
            ->update(
                '{{%translationmanager_translations}}',
                ['status' => 'unused'],
                ['source' => $providerSource],
            )
            ->execute();

        $counts = (new TranslationManagerVariable())->getUnusedTranslationCounts();
        self::assertArrayHasKey('providers', $counts);
        self::assertGreaterThanOrEqual(1, $counts['providers'][TestFormsProviderIntegration::NAME] ?? 0);

        $deleted = $this->translations->deleteProviderTranslations(TestFormsProviderIntegration::NAME);
        self::assertGreaterThanOrEqual(1, $deleted);

        self::assertSame([], $this->fetchRowsForSource($providerSource));
        self::assertNotEmpty(
            $this->fetchRowsForSource($siteSource),
            'Provider delete must not delete site translation rows.',
        );
    }
}

final class TestFormsProviderIntegration extends BaseIntegration
{
    public const NAME = 'test-forms-provider';
    public const CONTEXT_PREFIX = 'testforms';
    public const CATEGORY = 'testforms';

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
