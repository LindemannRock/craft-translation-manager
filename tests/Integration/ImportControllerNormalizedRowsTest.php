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
use craft\helpers\Db;
use craft\helpers\StringHelper;
use lindemannrock\translationmanager\controllers\ImportController;
use lindemannrock\translationmanager\integrations\BaseIntegration;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Pins the CSV import normalization contract after the pre-release cleanup:
 * mapped rows use current domain keys (`translationKey`, `translation`,
 * `language`) all the way through preview analysis and final import.
 *
 * @since 5.24.0
 */
final class ImportControllerNormalizedRowsTest extends TestCase
{
    public function testMappedRowsUseNeutralTranslationKeys(): void
    {
        $controller = $this->createImportController();

        $rows = [[
            self::MARKER . 'mapped_' . bin2hex(random_bytes(4)),
            'Mapped translation',
            Craft::$app->getSites()->getPrimarySite()->language,
            TranslationManager::getInstance()->getSettings()->getPrimaryCategory(),
            'site',
            'translated',
            'manual',
        ]];

        $translations = $this->invokePrivate($controller, 'buildTranslationsFromRows', [
            $rows,
            [
                0 => 'translationKey',
                1 => 'translation',
                2 => 'language',
                3 => 'category',
                4 => 'context',
                5 => 'status',
                6 => 'origin',
            ],
        ]);

        self::assertCount(1, $translations);
        self::assertArrayHasKey('translationKey', $translations[0]);
        self::assertArrayHasKey('translation', $translations[0]);
        self::assertArrayHasKey('language', $translations[0]);
        self::assertArrayNotHasKey('english', $translations[0]);
        self::assertArrayNotHasKey('arabic', $translations[0]);
        self::assertSame($rows[0][0], $translations[0]['translationKey']);
        self::assertSame('Mapped translation', $translations[0]['translation']);
        self::assertSame('manual', $translations[0]['origin']);
    }

    public function testAnalyzeTranslationsKeepsNeutralPreviewPayload(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $controller = $this->createImportController();
        $source = self::MARKER . 'preview_' . bin2hex(random_bytes(4));
        $settings = TranslationManager::getInstance()->getSettings();
        $language = Craft::$app->getSites()->getPrimarySite()->language;
        $expectedLanguage = $settings->mapLanguage($language);
        $category = $settings->getPrimaryCategory();

        $analysis = $this->invokePrivate($controller, 'analyzeTranslations', [[[
            'translationKey' => $source,
            'translation' => 'Preview translation',
            'language' => $language,
            'category' => $category,
            'context' => 'site',
            'status' => 'draft',
            'origin' => 'ai',
            '_rowNumber' => 2,
        ]]]);

        self::assertCount(1, $analysis['toImport']);
        self::assertSame($source, $analysis['toImport'][0]['translationKey']);
        self::assertSame('Preview translation', $analysis['toImport'][0]['translation']);
        self::assertSame('draft', $analysis['toImport'][0]['status']);
        self::assertSame('ai', $analysis['toImport'][0]['origin']);
        self::assertArrayNotHasKey('english', $analysis['toImport'][0]);
        self::assertArrayNotHasKey('arabic', $analysis['toImport'][0]);
    }

    public function testAnalyzeTranslationsClassifiesExistingRows(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $controller = $this->createImportController();
        $language = Craft::$app->getSites()->getPrimarySite()->language;
        $category = TranslationManager::getInstance()->getSettings()->getPrimaryCategory();
        $unchangedSource = self::MARKER . 'preview_unchanged_' . bin2hex(random_bytes(4));
        $updateSource = self::MARKER . 'preview_update_' . bin2hex(random_bytes(4));

        $this->createTranslationRecord($unchangedSource, 'Existing translation', $language, $category);
        $this->createTranslationRecord($updateSource, 'Before import', $language, $category);

        $analysis = $this->invokePrivate($controller, 'analyzeTranslations', [[
            [
                'translationKey' => $unchangedSource,
                'translation' => 'Existing translation',
                'language' => $language,
                'category' => $category,
                'context' => 'site',
                '_rowNumber' => 2,
            ],
            [
                'translationKey' => $updateSource,
                'translation' => 'After import',
                'language' => $language,
                'category' => $category,
                'context' => 'site',
                '_rowNumber' => 3,
            ],
        ]]);

        self::assertCount(1, $analysis['unchanged']);
        self::assertSame($unchangedSource, $analysis['unchanged'][0]['translationKey']);
        self::assertCount(1, $analysis['toUpdate']);
        self::assertSame($updateSource, $analysis['toUpdate'][0]['translationKey']);
        self::assertSame('Before import', $analysis['toUpdate'][0]['currentTranslation']);
        self::assertSame([], $analysis['toImport']);
    }

    public function testAnalyzeTranslationsKeepsMaliciousRowsOutOfImportBuckets(): void
    {
        $this->requireLatinSourceLanguage();

        $controller = $this->createImportController();

        $analysis = $this->invokePrivate($controller, 'analyzeTranslations', [[[
            'translationKey' => self::MARKER . 'malicious_' . bin2hex(random_bytes(4)),
            'translation' => '<script>alert(1)</script>',
            'language' => Craft::$app->getSites()->getPrimarySite()->language,
            'category' => TranslationManager::getInstance()->getSettings()->getPrimaryCategory(),
            'context' => 'site',
            '_rowNumber' => 2,
        ]]]);

        self::assertCount(1, $analysis['malicious']);
        self::assertSame([], $analysis['toImport']);
        self::assertSame([], $analysis['toUpdate']);
        self::assertSame([], $analysis['unchanged']);
    }

    public function testImportTranslationsCreatesRecordFromPreviewRows(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $controller = $this->createImportController();
        $source = self::MARKER . 'import_' . bin2hex(random_bytes(4));
        $settings = TranslationManager::getInstance()->getSettings();
        $language = Craft::$app->getSites()->getPrimarySite()->language;
        $expectedLanguage = $settings->mapLanguage($language);
        $category = $settings->getPrimaryCategory();

        $result = $this->invokePrivate($controller, 'importTranslations', [
            [[
                'translationKey' => $source,
                'translation' => 'Imported translation',
                'language' => $language,
                'category' => $category,
                'context' => 'site',
                'status' => 'draft',
                'origin' => 'ai',
                'rowNumber' => 2,
            ]],
            false,
        ]);

        self::assertSame(1, $result['imported']);
        self::assertSame(0, $result['updated']);
        self::assertSame(0, $result['skipped']);
        self::assertSame([], $result['errors']);

        $rows = $this->fetchRowsForSource($source);
        self::assertCount(1, $rows);
        self::assertSame('Imported translation', $rows[0]['translation']);
        self::assertSame('draft', $rows[0]['status']);
        self::assertSame('ai', $rows[0]['translationOrigin']);
        self::assertSame($expectedLanguage, $rows[0]['language']);
        self::assertSame($category, $rows[0]['category']);
    }

    public function testImportTranslationsUpdatesExistingRowsAndSkipsUnchangedRows(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $controller = $this->createImportController();
        $language = Craft::$app->getSites()->getPrimarySite()->language;
        $category = TranslationManager::getInstance()->getSettings()->getPrimaryCategory();
        $updateSource = self::MARKER . 'import_update_' . bin2hex(random_bytes(4));
        $skipSource = self::MARKER . 'import_skip_' . bin2hex(random_bytes(4));

        $this->createTranslationRecord($updateSource, 'Before import', $language, $category);
        $this->createTranslationRecord($skipSource, 'Same translation', $language, $category);

        $result = $this->invokePrivate($controller, 'importTranslations', [
            [
                [
                    'translationKey' => $updateSource,
                    'translation' => 'After import',
                    'language' => $language,
                    'category' => $category,
                    'context' => 'site',
                    'status' => 'translated',
                    'origin' => 'manual',
                    'rowNumber' => 2,
                ],
                [
                    'translationKey' => $skipSource,
                    'translation' => 'Same translation',
                    'language' => $language,
                    'category' => $category,
                    'context' => 'site',
                    'rowNumber' => 3,
                ],
            ],
            false,
        ]);

        self::assertSame(0, $result['imported']);
        self::assertSame(1, $result['updated']);
        self::assertSame(1, $result['skipped']);
        self::assertSame([], $result['errors']);

        $rows = $this->fetchRowsForSource($updateSource);
        self::assertCount(1, $rows);
        self::assertSame('After import', $rows[0]['translation']);
        self::assertSame('translated', $rows[0]['status']);
        self::assertSame('manual', $rows[0]['translationOrigin']);
    }

    public function testImportTranslationsDerivesFormieCategoryBeforeLookup(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $controller = $this->createImportController();
        $language = Craft::$app->getSites()->getPrimarySite()->language;
        $source = self::MARKER . 'formie_' . bin2hex(random_bytes(4));

        $this->createTranslationRecord($source, 'Before import', $language, 'formie', 'formie.field');

        $result = $this->invokePrivate($controller, 'importTranslations', [
            [[
                'translationKey' => $source,
                'translation' => 'After import',
                'language' => $language,
                'category' => '',
                'context' => 'formie.field',
                'type' => 'forms',
                'rowNumber' => 2,
            ]],
            false,
        ]);

        self::assertSame(0, $result['imported']);
        self::assertSame(1, $result['updated']);
        self::assertSame(0, $result['skipped']);

        $rows = $this->fetchRowsForSource($source);
        self::assertCount(1, $rows);
        self::assertSame('formie', $rows[0]['category']);
        self::assertSame('After import', $rows[0]['translation']);
    }

    public function testImportTranslationsDerivesRegisteredProviderCategoryBeforeLookup(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');
        $integrationService->register(
            CsvImportProviderTestIntegration::NAME,
            new CsvImportProviderTestIntegration(),
        );

        $controller = $this->createImportController();
        $language = Craft::$app->getSites()->getPrimarySite()->language;
        $source = self::MARKER . 'csv_provider_' . bin2hex(random_bytes(4));
        $context = CsvImportProviderTestIntegration::CONTEXT_PREFIX . '.field';

        $this->createTranslationRecord(
            $source,
            'Before provider import',
            $language,
            CsvImportProviderTestIntegration::CATEGORY,
            $context,
        );

        $result = $this->invokePrivate($controller, 'importTranslations', [
            [[
                'translationKey' => $source,
                'translation' => 'After provider import',
                'language' => $language,
                'category' => '',
                'context' => $context,
                'type' => 'forms',
                'status' => 'draft',
                'origin' => 'ai',
                'rowNumber' => 2,
            ]],
            false,
        ]);

        self::assertSame(0, $result['imported']);
        self::assertSame(1, $result['updated']);
        self::assertSame(0, $result['skipped']);

        $rows = $this->fetchRowsForSource($source);
        self::assertCount(1, $rows);
        self::assertSame(CsvImportProviderTestIntegration::CATEGORY, $rows[0]['category']);
        self::assertSame($context, $rows[0]['context']);
        self::assertSame('After provider import', $rows[0]['translation']);
        self::assertSame('draft', $rows[0]['status']);
        self::assertSame('ai', $rows[0]['translationOrigin']);
    }

    public function testImportTranslationsHandlesDuplicateRowsInSameImport(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $controller = $this->createImportController();
        $language = Craft::$app->getSites()->getPrimarySite()->language;
        $category = TranslationManager::getInstance()->getSettings()->getPrimaryCategory();
        $source = self::MARKER . 'duplicate_' . bin2hex(random_bytes(4));

        $result = $this->invokePrivate($controller, 'importTranslations', [
            [
                [
                    'translationKey' => $source,
                    'translation' => 'First import value',
                    'language' => $language,
                    'category' => $category,
                    'context' => 'site',
                    'rowNumber' => 2,
                ],
                [
                    'translationKey' => $source,
                    'translation' => 'Second import value',
                    'language' => $language,
                    'category' => $category,
                    'context' => 'site',
                    'rowNumber' => 3,
                ],
            ],
            false,
        ]);

        self::assertSame(1, $result['imported']);
        self::assertSame(1, $result['updated']);
        self::assertSame(0, $result['skipped']);
        self::assertSame([], $result['errors']);

        $rows = $this->fetchRowsForSource($source);
        self::assertCount(1, $rows);
        self::assertSame('Second import value', $rows[0]['translation']);
    }

    private function createImportController(): ImportController
    {
        return new ImportController('import', TranslationManager::getInstance());
    }

    private function createTranslationRecord(
        string $source,
        string $translation,
        string $language,
        string $category,
        string $context = 'site',
    ): TranslationRecord {
        $record = new TranslationRecord();
        $record->source = $source;
        $record->sourceHash = md5($source);
        $record->translationKey = $source;
        $record->translation = $translation;
        $record->language = TranslationManager::getInstance()->getSettings()->mapLanguage($language);
        $record->category = $category;
        $record->context = $context;
        $record->siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $record->status = 'translated';
        $record->translationOrigin = 'manual';
        $record->usageCount = 1;
        $record->lastUsed = Db::prepareDateForDb(new \DateTime());
        $record->dateCreated = Db::prepareDateForDb(new \DateTime());
        $record->dateUpdated = Db::prepareDateForDb(new \DateTime());
        $record->uid = StringHelper::UUID();

        self::assertTrue($record->save(), json_encode($record->getErrors()));

        return $record;
    }

    /**
     * @param array<int,mixed> $args
     * @return mixed
     */
    private function invokePrivate(ImportController $controller, string $method, array $args): mixed
    {
        $reflection = new \ReflectionMethod($controller, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($controller, $args);
    }
}

final class CsvImportProviderTestIntegration extends BaseIntegration
{
    public const NAME = 'csv-import-provider-test';

    public const CONTEXT_PREFIX = 'csvprovider';

    public const CATEGORY = 'csvprovider';

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
