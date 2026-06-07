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
 * Covers the real auto-generation path from saved translation rows to
 * generated PHP translation files.
 *
 * @since 5.25.0
 */
final class GenerationServiceFormieAutoGenerateTest extends TestCase
{
    private ?string $originalTranslationsAlias = null;

    private ?string $tempTranslationsPath = null;

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

        $this->tempTranslationsPath = Craft::$app->getPath()->getTempPath()
            . '/translation-manager-test-' . bin2hex(random_bytes(6));
        FileHelper::createDirectory($this->tempTranslationsPath);

        Craft::setAlias('@translations', $this->tempTranslationsPath);

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

        if ($this->tempTranslationsPath !== null && is_dir($this->tempTranslationsPath)) {
            FileHelper::removeDirectory($this->tempTranslationsPath);
        }

        parent::tearDown();
    }

    public function testSavedFormieAndSiteTranslationsAutoGeneratePhpFiles(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $settings = TranslationManager::getInstance()->getSettings();
        $siteCategory = $settings->getPrimaryCategory();

        $formieSource = self::MARKER . 'formie_auto_generate_' . bin2hex(random_bytes(4));
        $formieTranslationText = 'Generated Formie value ' . bin2hex(random_bytes(4));
        $siteSource = self::MARKER . 'site_auto_generate_' . bin2hex(random_bytes(4));
        $siteTranslationText = 'Generated site value ' . bin2hex(random_bytes(4));

        $formieCreated = $this->translations->createOrUpdateTranslation($formieSource, 'formie.test-form');
        self::assertNotNull($formieCreated, 'Formie source string should create translation rows.');

        $siteCreated = $this->translations->createOrUpdateTranslation($siteSource, 'site.test-template');
        self::assertNotNull($siteCreated, 'Site source string should create translation rows.');

        $formieTranslation = $this->findRowToTranslate($formieSource, 'formie');
        $formieTranslation->translation = $formieTranslationText;

        self::assertTrue(
            $this->translations->saveTranslation($formieTranslation),
            'Saving the Formie translation row should succeed.',
        );

        $siteTranslation = $this->findRowToTranslate($siteSource, $siteCategory);
        $siteTranslation->translation = $siteTranslationText;

        self::assertTrue(
            $this->translations->saveTranslation($siteTranslation),
            'Saving the site translation row should succeed.',
        );

        self::assertTrue(
            TranslationManager::getInstance()->generate->triggerAutoGenerate(),
            'Auto-generation should run when the setting is enabled.',
        );

        $this->assertGeneratedTranslation(
            $formieTranslation,
            'formie.php',
            $formieSource,
            $formieTranslationText,
        );

        $this->assertGeneratedTranslation(
            $siteTranslation,
            $siteCategory . '.php',
            $siteSource,
            $siteTranslationText,
        );
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

    private function assertGeneratedTranslation(
        TranslationRecord $translation,
        string $filename,
        string $source,
        string $translationText,
    ): void {
        $site = Craft::$app->getSites()->getSiteById((int) $translation->siteId);
        self::assertNotNull($site, 'The generated translation row should reference an existing site.');

        $language = TranslationManager::getInstance()
            ->getSettings()
            ->mapLanguage($site->language);
        $file = $this->tempTranslationsPath . '/' . $language . '/' . $filename;

        self::assertFileExists($file, "Auto-generation should write {$filename}.");

        $messages = require $file;
        self::assertIsArray($messages);
        self::assertArrayHasKey($source, $messages);
        self::assertSame($translationText, $messages[$source]);
    }
}
