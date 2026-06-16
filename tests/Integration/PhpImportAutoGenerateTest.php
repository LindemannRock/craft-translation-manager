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
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Covers the PHP-file-import -> auto-generation path that
 * {@see \lindemannrock\translationmanager\controllers\PhpImportController::actionImport()}
 * wires up: after rows are imported, the generation funnel must run so the
 * on-disk PHP translation files reflect the imported values (matching CSV/XLSX
 * import in ImportController).
 *
 * Driven through the service + generation funnel rather than the controller
 * action because the integration harness runs under a console request, so
 * `requirePostRequest()` / `getRequiredBodyParam()` cannot be satisfied
 * directly (same constraint the rest of the controller suite works around).
 *
 * @since 5.25.1
 */
final class PhpImportAutoGenerateTest extends TestCase
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

    public function testPhpImportFollowedByAutoGenerateWritesPhpFile(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $settings = TranslationManager::getInstance()->getSettings();
        $category = $settings->getPrimaryCategory();

        // Pick a non-source target language so its imported value is the one
        // generation writes out (the source language row just echoes the key).
        $sourceLanguage = $settings->sourceLanguage;
        $sourceBaseLanguage = explode('-', $sourceLanguage)[0];
        $importLanguage = null;
        foreach (TranslationManager::getInstance()->getUniqueLanguages() as $language) {
            $baseLanguage = explode('-', $language)[0];
            if ($language !== $sourceLanguage && $baseLanguage !== $sourceBaseLanguage) {
                $importLanguage = $language;
                break;
            }
        }

        if ($importLanguage === null) {
            self::markTestSkipped('Test requires a non-source site language.');
        }

        $key = self::MARKER . 'php_import_auto_generate_' . bin2hex(random_bytes(4));
        $value = 'Imported PHP value ' . bin2hex(random_bytes(4));

        $result = TranslationManager::getInstance()->translations->importPhpEntries(
            [['key' => $key, 'value' => $value]],
            $importLanguage,
            $category,
            null,
        );

        self::assertSame(1, (int) $result['imported'], 'PHP import should create one new key.');

        // Mirror the controller: import, then run the auto-generate funnel.
        self::assertTrue(
            TranslationManager::getInstance()->generate->triggerAutoGenerate(),
            'Auto-generation should run when the setting is enabled.',
        );

        $folder = $settings->mapLanguage($importLanguage);
        $file = $this->tempTranslationsPath . '/' . $folder . '/' . $category . '.php';

        self::assertFileExists($file, "Auto-generation should write {$category}.php for {$folder}.");

        $messages = require $file;
        self::assertIsArray($messages);
        self::assertArrayHasKey($key, $messages);
        self::assertSame($value, $messages[$key]);
    }
}
