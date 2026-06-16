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
use craft\helpers\FileHelper;
use lindemannrock\translationmanager\integrations\BaseIntegration;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Proves provider generation is isolated by integration category.
 *
 * @since 5.26.0
 */
final class GenerationServiceProviderGenerationTest extends TestCase
{
    private ?string $originalTranslationsAlias = null;

    private ?string $tempTranslationsPath = null;

    private string $originalGenerationPath;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = TranslationManager::getInstance()->getSettings();
        $this->originalGenerationPath = $settings->generationPath;
        $this->originalTranslationsAlias = Craft::getAlias('@translations', false) ?: null;

        $this->tempTranslationsPath = Craft::$app->getPath()->getTempPath()
            . '/translation-manager-provider-generation-test-' . bin2hex(random_bytes(6));
        FileHelper::createDirectory($this->tempTranslationsPath);

        Craft::setAlias('@translations', $this->tempTranslationsPath);
        $settings->generationPath = '@translations';

        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');
        $integrationService->register(
            ProviderGenerationTestIntegration::NAME,
            new ProviderGenerationTestIntegration(),
        );
    }

    protected function tearDown(): void
    {
        TranslationManager::getInstance()->getSettings()->generationPath = $this->originalGenerationPath;

        if ($this->originalTranslationsAlias !== null) {
            Craft::setAlias('@translations', $this->originalTranslationsAlias);
        }

        if ($this->tempTranslationsPath !== null && is_dir($this->tempTranslationsPath)) {
            FileHelper::removeDirectory($this->tempTranslationsPath);
        }

        parent::tearDown();
    }

    public function testGenerateAllWritesProviderCategoryFile(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $source = self::MARKER . 'provider_generate_' . bin2hex(random_bytes(4));
        $translationText = 'Generated provider value ' . bin2hex(random_bytes(4));

        $created = $this->translations->createOrUpdateTranslation(
            $source,
            ProviderGenerationTestIntegration::CONTEXT_PREFIX . '.fixture.label',
        );

        self::assertNotNull($created, 'Provider source string should create translation rows.');

        $translation = $this->findRowToTranslate($source, ProviderGenerationTestIntegration::CATEGORY);
        $translation->translation = $translationText;

        self::assertTrue(
            $this->translations->saveTranslation($translation),
            'Saving the provider translation row should succeed.',
        );

        $results = TranslationManager::getInstance()->generate->generateAll();

        self::assertTrue((bool)($results['success'] ?? false));
        self::assertArrayHasKey(ProviderGenerationTestIntegration::NAME, $results['results'] ?? []);
        self::assertTrue((bool)($results['results'][ProviderGenerationTestIntegration::NAME]['success'] ?? false));

        $site = Craft::$app->getSites()->getSiteById((int) $translation->siteId);
        self::assertNotNull($site, 'The generated translation row should reference an existing site.');

        $language = TranslationManager::getInstance()
            ->getSettings()
            ->mapLanguage($site->language);
        $providerFile = $this->tempTranslationsPath . '/' . $language . '/' . ProviderGenerationTestIntegration::CATEGORY . '.php';

        self::assertFileExists($providerFile, 'Provider generation should write its category file.');

        $providerMessages = require $providerFile;
        self::assertIsArray($providerMessages);
        self::assertArrayHasKey($source, $providerMessages);
        self::assertSame($translationText, $providerMessages[$source]);
        self::assertStringContainsString(
            " * {$language} translations",
            (string)file_get_contents($providerFile),
            'Provider files should use the same mapped-language header convention as site/category files.',
        );

        $formieFile = $this->tempTranslationsPath . '/' . $language . '/formie.php';
        if (is_file($formieFile)) {
            $formieMessages = require $formieFile;
            self::assertIsArray($formieMessages);
            self::assertArrayNotHasKey($source, $formieMessages);
        }
    }

    private function findRowToTranslate(string $source, string $category): TranslationRecord
    {
        $sourceLanguage = TranslationManager::getInstance()->getSettings()->sourceLanguage;
        $sourceBaseLanguage = explode('-', $sourceLanguage)[0];

        /** @var TranslationRecord[] $rows */
        $rows = TranslationRecord::find()
            ->where(['source' => $source, 'category' => $category])
            ->orderBy(['language' => SORT_ASC])
            ->all();

        self::assertNotEmpty($rows, "Expected {$category} rows for the marker source.");

        foreach ($rows as $row) {
            $rowLanguage = (string) $row->language;
            $rowBaseLanguage = explode('-', $rowLanguage)[0];

            if ($rowLanguage !== $sourceLanguage && $rowBaseLanguage !== $sourceBaseLanguage) {
                return $row;
            }
        }

        return $rows[0];
    }
}

final class ProviderGenerationTestIntegration extends BaseIntegration
{
    public const NAME = 'provider-generation-test';

    public const CONTEXT_PREFIX = 'testformsgen';

    public const CATEGORY = 'testformsgen';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getPluginHandle(): string
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

    public function getContextPrefix(): string
    {
        return self::CONTEXT_PREFIX;
    }

    public function getCategory(): string
    {
        return self::CATEGORY;
    }

    protected function getTranslationType(): string
    {
        return 'forms';
    }
}
