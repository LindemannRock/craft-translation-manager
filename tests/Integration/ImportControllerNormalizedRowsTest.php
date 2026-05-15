<?php

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use Craft;
use lindemannrock\translationmanager\controllers\ImportController;
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

    private function createImportController(): ImportController
    {
        return new ImportController('import', TranslationManager::getInstance());
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
