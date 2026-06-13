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
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;

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
