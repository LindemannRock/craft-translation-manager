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
use lindemannrock\translationmanager\helpers\GeneratedFileCleanupHelper;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.25.1
 */
#[CoversClass(GeneratedFileCleanupHelper::class)]
final class GeneratedFileCleanupHelperTest extends TestCase
{
    private ?string $originalTranslationsAlias = null;

    private string $tempTranslationsPath = '';

    private string $originalGenerationPath;

    /**
     * @var array<int,array<string,mixed>>
     */
    private array $originalLocaleMapping;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = TranslationManager::getInstance()->getSettings();
        $this->originalGenerationPath = $settings->generationPath;
        $this->originalLocaleMapping = $settings->localeMapping;
        $this->originalTranslationsAlias = Craft::getAlias('@translations', false) ?: null;

        $this->tempTranslationsPath = Craft::$app->getPath()->getTempPath() . '/translation-manager-generated-cleanup-' . bin2hex(random_bytes(6));
        FileHelper::createDirectory($this->tempTranslationsPath);

        Craft::setAlias('@translations', $this->tempTranslationsPath);
        $settings->generationPath = '@translations';
        $settings->localeMapping = [
            ['source' => 'en-US', 'destination' => 'en', 'enabled' => true],
        ];
    }

    protected function tearDown(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $settings->generationPath = $this->originalGenerationPath;
        $settings->localeMapping = $this->originalLocaleMapping;

        if ($this->originalTranslationsAlias !== null) {
            Craft::setAlias('@translations', $this->originalTranslationsAlias);
        }

        if (is_dir($this->tempTranslationsPath)) {
            FileHelper::removeDirectory($this->tempTranslationsPath);
        }

        parent::tearDown();
    }

    public function testCandidatesIncludeGeneratedFilesOutsideCurrentTargets(): void
    {
        $this->requireAtLeastOneSite();

        $settings = TranslationManager::getInstance()->getSettings();
        $validLanguage = $settings->mapLanguage(TranslationManager::getInstance()->getAllowedSites()[0]->language);
        $validCategory = $settings->getPrimaryCategory();
        $staleCategory = 'old-generated-category-' . bin2hex(random_bytes(4));

        $validPath = $validLanguage . '/' . $validCategory . '.php';
        $staleCategoryPath = $validLanguage . '/' . $staleCategory . '.php';
        $staleLanguagePath = 'en-US/' . $validCategory . '.php';
        $staleBothPath = 'zz/' . $staleCategory . '.php';

        $this->writeGeneratedFile($validPath);
        $this->writeGeneratedFile($staleCategoryPath);
        $this->writeGeneratedFile($staleLanguagePath);
        $this->writeGeneratedFile($staleBothPath);

        $result = GeneratedFileCleanupHelper::getCandidates();
        $byPath = [];
        foreach ($result['files'] as $file) {
            $byPath[$file['path']] = $file;
        }

        self::assertSame(3, $result['totalCandidates']);
        self::assertArrayNotHasKey($validPath, $byPath, 'A generated file with a current language and category must not be listed.');
        self::assertSame('category', $byPath[$staleCategoryPath]['reason'] ?? null);
        self::assertSame('language', $byPath[$staleLanguagePath]['reason'] ?? null);
        self::assertSame('language-category', $byPath[$staleBothPath]['reason'] ?? null);

        self::assertFalse(
            GeneratedFileCleanupHelper::deleteCandidate($validPath),
            'The helper must not delete a generated file that is not currently listed as a cleanup candidate.',
        );
        self::assertFileExists($this->tempTranslationsPath . '/' . $validPath);

        self::assertTrue(GeneratedFileCleanupHelper::deleteCandidate($staleLanguagePath));
        self::assertFileDoesNotExist($this->tempTranslationsPath . '/' . $staleLanguagePath);
    }

    private function writeGeneratedFile(string $relativePath): void
    {
        $path = $this->tempTranslationsPath . '/' . $relativePath;
        FileHelper::createDirectory(dirname($path));

        file_put_contents($path, "<?php\nreturn [];\n");
    }
}
