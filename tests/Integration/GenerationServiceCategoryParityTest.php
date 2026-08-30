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
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function testCategoryGenerationReconcilesEachMappedLanguageWithoutTouchingOtherFiles(): void
    {
        $this->requireAtLeastOneSite();

        $settings = TranslationManager::getInstance()->getSettings();
        $settings->localeMapping = [[
            'source' => 'en-US',
            'destination' => 'fr',
            'enabled' => true,
        ]];
        $category = 'tm-test-stale-' . bin2hex(random_bytes(4));
        $unrelatedCategory = $category . '-unrelated';
        $settings->translationCategories = [['key' => $category, 'enabled' => true]];
        $settings->translationCategory = $category;
        $sourceA = self::MARKER . 'stale_a_' . bin2hex(random_bytes(4));
        $sourceB = self::MARKER . 'stale_b_' . bin2hex(random_bytes(4));

        Craft::setAlias('@translations', $this->categoryPath);
        $this->createTranslatedRow($sourceA, 'Arabic value', 'ar', $category);
        $rowB = $this->createTranslatedRow($sourceB, 'French value', 'fr', $category);

        FileHelper::createDirectory($this->categoryPath . '/ar');
        FileHelper::createDirectory($this->categoryPath . '/fr');
        $unrelatedCategoryFile = $this->categoryPath . '/ar/' . $unrelatedCategory . '.php';
        $unrelatedProviderFile = $this->categoryPath . '/fr/formie.php';
        $unrelatedCategoryBytes = "<?php\nreturn ['keep' => 'category'];\n";
        $unrelatedProviderBytes = "<?php\nreturn ['keep' => 'provider'];\n";
        self::assertNotFalse(file_put_contents($unrelatedCategoryFile, $unrelatedCategoryBytes));
        self::assertNotFalse(file_put_contents($unrelatedProviderFile, $unrelatedProviderBytes));

        $initial = TranslationManager::getInstance()->generate->generateCategoryTranslations($category);
        self::assertTrue((bool)($initial['success'] ?? false));

        $fileA = $this->categoryPath . '/ar/' . $category . '.php';
        $fileB = $this->categoryPath . '/fr/' . $category . '.php';
        self::assertFileExists($fileA);
        self::assertFileExists($fileB);

        $rowB->translation = '';
        self::assertTrue($this->translations->saveTranslation($rowB));
        self::assertSame('pending', $rowB->status);

        $reconciled = TranslationManager::getInstance()->generate->generateCategoryTranslations($category);
        self::assertTrue((bool)($reconciled['success'] ?? false));
        self::assertSame(1, $reconciled['deletedFileCount']);
        self::assertFileExists($fileA);
        self::assertFileDoesNotExist($fileB);
        self::assertSame($unrelatedCategoryBytes, file_get_contents($unrelatedCategoryFile));
        self::assertSame($unrelatedProviderBytes, file_get_contents($unrelatedProviderFile));

        $idempotent = TranslationManager::getInstance()->generate->generateCategoryTranslations($category);
        self::assertSame(0, $idempotent['deletedFileCount']);
        self::assertFileDoesNotExist($fileB);

        $rowB->translation = '0';
        self::assertTrue($this->translations->saveTranslation($rowB));
        self::assertSame('translated', $rowB->status);

        $zeroResult = TranslationManager::getInstance()->generate->generateCategoryTranslations($category);
        self::assertTrue((bool)($zeroResult['success'] ?? false));
        self::assertFileExists($fileB);
        $messages = require $fileB;
        self::assertSame('0', $messages[$sourceB] ?? null);
        self::assertSame($unrelatedCategoryBytes, file_get_contents($unrelatedCategoryFile));
        self::assertSame($unrelatedProviderBytes, file_get_contents($unrelatedProviderFile));

        $rowB->translation = '';
        self::assertTrue($this->translations->saveTranslation($rowB));
        $siteResult = TranslationManager::getInstance()->generate->generateSiteTranslations();
        self::assertTrue((bool)($siteResult['success'] ?? false));
        self::assertFileExists($fileA);
        self::assertFileDoesNotExist($fileB);
    }

    #[DataProvider('translationValueProvider')]
    public function testGeneratedFileMembershipUsesExplicitValuePresence(
        ?string $value,
        bool $expectedPresent,
    ): void {
        $category = 'tm-test-generation-value-' . bin2hex(random_bytes(4));
        $source = self::MARKER . 'generation_value_' . bin2hex(random_bytes(4));
        Craft::setAlias('@translations', $this->categoryPath);
        $this->createTranslatedRow($source, $value, 'ar', $category);

        $languagePath = $this->categoryPath . '/ar';
        FileHelper::createDirectory($languagePath);
        $file = $languagePath . '/' . $category . '.php';
        self::assertNotFalse(file_put_contents($file, "<?php\nreturn ['stale' => 'value'];\n"));

        $result = TranslationManager::getInstance()->generate->generateCategoryTranslations($category);
        self::assertTrue((bool)($result['success'] ?? false));
        self::assertSame($expectedPresent, is_file($file));

        if ($expectedPresent) {
            $messages = require $file;
            self::assertSame($value, $messages[$source] ?? null);
        }
    }

    public function testGenerationFailureLeavesUnrelatedOutputAndRemovesItsTemporaryFile(): void
    {
        $category = 'tm-test-generation-failure-' . bin2hex(random_bytes(4));
        $unrelatedCategory = $category . '-unrelated';
        $source = self::MARKER . 'generation_failure_' . bin2hex(random_bytes(4));
        Craft::setAlias('@translations', $this->categoryPath);
        $this->createTranslatedRow($source, 'Cannot replace directory', 'ar', $category);

        $languagePath = $this->categoryPath . '/ar';
        FileHelper::createDirectory($languagePath);
        $blockedTarget = $languagePath . '/' . $category . '.php';
        FileHelper::createDirectory($blockedTarget);
        $unrelatedFile = $languagePath . '/' . $unrelatedCategory . '.php';
        $unrelatedBytes = "<?php\nreturn ['keep' => 'unchanged'];\n";
        self::assertNotFalse(file_put_contents($unrelatedFile, $unrelatedBytes));

        try {
            TranslationManager::getInstance()->generate->generateCategoryTranslations($category);
            self::fail('A directory collision at the target file must fail generation.');
        } catch (\Throwable) {
            self::assertSame($unrelatedBytes, file_get_contents($unrelatedFile));
            self::assertDirectoryExists($blockedTarget);
            self::assertFileDoesNotExist($blockedTarget . '.tmp');
        }
    }

    /**
     * @return array<string,array{0:?string,1:bool}>
     */
    public static function translationValueProvider(): array
    {
        return [
            'zero' => ['0', true],
            'normal text' => ['Translated text', true],
            'empty string' => ['', false],
            'whitespace' => [" \t\n", false],
            'null' => [null, false],
        ];
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

    private function createTranslatedRow(
        string $source,
        ?string $translation,
        string $language,
        string $category,
    ): TranslationRecord {
        $site = Craft::$app->getSites()->getPrimarySite();
        $now = Db::prepareDateForDb(new \DateTimeImmutable());

        $record = new TranslationRecord();
        $record->source = $source;
        $record->sourceHash = md5($source);
        $record->context = 'site.generation-test';
        $record->category = $category;
        $record->translationKey = $source;
        $record->translation = $translation;
        $record->siteId = (int)$site->id;
        $record->language = $language;
        $record->status = 'translated';
        $record->translationOrigin = 'manual';
        $record->usageCount = 1;
        $record->lastUsed = $now;
        $record->dateCreated = $now;
        $record->dateUpdated = $now;
        $record->uid = StringHelper::UUID();

        self::assertTrue($record->save(), json_encode($record->getErrors()));

        return $record;
    }
}
