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
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Pins that the "Generate All Files" path (generateSiteTranslations) and the
 * "Generate <category> Only" path (generateCategoryTranslations) produce
 * identical files for the same rows. Also guards the language-based grouping:
 * each language's value lands in its own language folder rather than collapsing
 * by siteId.
 *
 * @since 5.25.1
 */
final class GenerationServiceCategoryParityTest extends TestCase
{
    private ?string $originalTranslationsAlias = null;

    private string $allPath = '';

    private string $categoryPath = '';

    private bool $originalAutoGenerate;

    private bool $originalRequireApproval;

    private string $originalGenerationPath;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = TranslationManager::getInstance()->getSettings();
        $this->originalAutoGenerate = $settings->autoGenerate;
        $this->originalRequireApproval = $settings->requireApproval;
        $this->originalGenerationPath = $settings->generationPath;
        $this->originalTranslationsAlias = Craft::getAlias('@translations', false) ?: null;

        // Two separate output dirs: each generation path is `require`d when read
        // back, and PHP caches require by absolute path — writing both runs to
        // the same path would return the first run's cached array.
        $base = Craft::$app->getPath()->getTempPath() . '/translation-manager-test-' . bin2hex(random_bytes(6));
        $this->allPath = $base . '/all';
        $this->categoryPath = $base . '/category';
        FileHelper::createDirectory($this->allPath);
        FileHelper::createDirectory($this->categoryPath);

        $settings->autoGenerate = true;
        $settings->requireApproval = false;
        $settings->generationPath = '@translations';
    }

    protected function tearDown(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $settings->autoGenerate = $this->originalAutoGenerate;
        $settings->requireApproval = $this->originalRequireApproval;
        $settings->generationPath = $this->originalGenerationPath;

        if ($this->originalTranslationsAlias !== null) {
            Craft::setAlias('@translations', $this->originalTranslationsAlias);
        }

        foreach ([$this->allPath, $this->categoryPath] as $path) {
            $parent = dirname($path);
            if (is_dir($parent)) {
                FileHelper::removeDirectory($parent);
                break;
            }
        }

        parent::tearDown();
    }

    public function testGenerateAllAndGenerateCategoryProduceIdenticalFiles(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $settings = TranslationManager::getInstance()->getSettings();
        $category = $settings->getPrimaryCategory();

        // Seed two site source strings and translate each in a non-source
        // language so the generated file has real content to compare.
        $sources = [];
        for ($i = 0; $i < 2; $i++) {
            $source = self::MARKER . 'parity_' . $i . '_' . bin2hex(random_bytes(4));
            $value = 'Parity value ' . $i . ' ' . bin2hex(random_bytes(4));

            $created = $this->translations->createOrUpdateTranslation($source, 'site.parity-template');
            self::assertNotNull($created, 'Site source string should create translation rows.');

            $row = $this->findRowToTranslate($source, $category);
            $row->translation = $value;
            self::assertTrue($this->translations->saveTranslation($row), 'Saving the site translation row should succeed.');

            $sources[$source] = $value;
        }

        // Run "Generate All" into allPath.
        Craft::setAlias('@translations', $this->allPath);
        $allResult = TranslationManager::getInstance()->generate->generateSiteTranslations();
        self::assertTrue((bool)($allResult['success'] ?? false));
        $allFiles = $this->readCategoryFiles($this->allPath, $category);

        // Run "Generate <category> Only" into categoryPath.
        Craft::setAlias('@translations', $this->categoryPath);
        $categoryResult = TranslationManager::getInstance()->generate->generateCategoryTranslations($category);
        self::assertTrue((bool)($categoryResult['success'] ?? false));
        $categoryFiles = $this->readCategoryFiles($this->categoryPath, $category);

        // Both paths must produce the same language folders with the same values.
        self::assertNotEmpty($allFiles, 'Generate All should write at least one language file for the category.');
        self::assertSame(
            array_keys($allFiles),
            array_keys($categoryFiles),
            'Both generation paths should write the same set of language folders.',
        );
        self::assertEquals(
            $allFiles,
            $categoryFiles,
            'Generate All and Generate Category should write identical translation values.',
        );

        // Each seeded translation must land in some language file (not silently
        // dropped). The source-language file echoes the key as its value, so the
        // translated value is asserted across all language files rather than one.
        foreach ($sources as $source => $value) {
            $found = false;
            foreach ($allFiles as $messages) {
                if (($messages[$source] ?? null) === $value) {
                    $found = true;
                    break;
                }
            }
            self::assertTrue($found, "Generated files should contain the translated value for {$source}.");
        }
    }

    /**
     * Read every `<lang>/<category>.php` under $basePath into [lang => messages].
     *
     * @return array<string, array<string, string>>
     */
    private function readCategoryFiles(string $basePath, string $category): array
    {
        $out = [];
        foreach (glob($basePath . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $file = $dir . '/' . $category . '.php';
            if (!is_file($file)) {
                continue;
            }

            $messages = require $file;
            if (is_array($messages)) {
                $out[basename($dir)] = $messages;
            }
        }

        ksort($out);

        return $out;
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
