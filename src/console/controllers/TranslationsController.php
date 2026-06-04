<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Console controller for translation management tasks
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\translationmanager\helpers\PhpTranslationsHelper;
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\services\TranslationsService;
use lindemannrock\translationmanager\TranslationManager;
use yii\console\ExitCode;

/**
 * Console Translations Controller
 *
 * @since 1.0.0
 */
class TranslationsController extends Controller
{
    /**
     * @var string The default action
     */
    public $defaultAction = 'capture-formie';

    /**
     * @var string|null Restrict `import` to a single language (the file's directory name).
     */
    public ?string $language = null;

    /**
     * @var string|null Restrict `import` to a single category (the file name without `.php`).
     */
    public ?string $category = null;

    /**
     * @var bool Required to import every discovered file when no --language/--category
     * filter is given. Guards against accidentally importing the whole translations
     * tree (including stray test fixtures) in one shot.
     */
    public bool $all = false;

    /**
     * @var int Maximum number of pending translations to process in `ai-draft`.
     * @since 5.25.0
     */
    public int $limit = 50;

    /**
     * @var string Translation type filter for `ai-draft`: all, forms, or site.
     * @since 5.25.0
     */
    public string $type = 'all';

    /**
     * @var string|null Provider handle for `ai-draft`; null uses settings default.
     * @since 5.25.0
     */
    public ?string $provider = null;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'import') {
            $options[] = 'language';
            $options[] = 'category';
            $options[] = 'all';
        }

        if ($actionID === 'ai-draft') {
            $options[] = 'limit';
            $options[] = 'type';
            $options[] = 'provider';
        }

        return $options;
    }

    /**
     * Translate pending rows into AI drafts (manual approval remains separate).
     *
     * Usage:
     * - php craft translation-manager/translations/ai-draft ar
     * - php craft translation-manager/translations/ai-draft de --limit=100 --type=site --provider=mock
     *
     * @since 5.22.0
     */
    public function actionAiDraft(string $language): int
    {
        $this->stdout("AI draft translation batch\n", Console::FG_YELLOW);
        $this->stdout("Language: {$language}\n", Console::FG_CYAN);
        $this->stdout("Limit: {$this->limit}\n", Console::FG_CYAN);
        $this->stdout("Type: {$this->type}\n", Console::FG_CYAN);
        $this->stdout("Provider: " . ($this->provider ?? 'settings default') . "\n\n", Console::FG_CYAN);

        if (!in_array($this->type, ['all', 'forms', 'site'], true)) {
            $this->stderr("Invalid type. Use: all, forms, or site.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        try {
            $result = TranslationManager::getInstance()->ai->translatePendingToDraft(
                targetLanguage: $language,
                limit: $this->limit,
                type: $this->type,
                providerHandle: $this->provider,
            );

            $this->stdout("Processed: {$result['processed']}\n", Console::FG_CYAN);
            $this->stdout("AI drafts created: {$result['translated']}\n", Console::FG_GREEN);
            $this->stdout("Skipped: {$result['skipped']}\n", Console::FG_YELLOW);
            $this->stdout("Failed: {$result['failed']}\n", $result['failed'] > 0 ? Console::FG_RED : Console::FG_GREEN);
            $this->stdout("Provider used: {$result['provider']}\n", Console::FG_CYAN);

            return $result['failed'] > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr("AI draft batch failed: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Capture all existing Formie form translations
     */
    public function actionCaptureFormie(): int
    {
        $this->stdout("Capturing Formie form translations...\n", Console::FG_YELLOW);
        
        // Check if Formie is installed and enabled
        if (!PluginHelper::isPluginEnabled('formie')) {
            $this->stderr("Formie plugin is not installed or enabled.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Check if Formie integration is enabled
        if (!TranslationManager::getInstance()->getSettings()->enableFormieIntegration) {
            $this->stderr("Formie integration is not enabled in settings.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        try {
            $forms = \verbb\formie\Formie::getInstance()->getForms()->getAllForms();
            $totalForms = count($forms);
            
            if ($totalForms === 0) {
                $this->stdout("No forms found.\n", Console::FG_YELLOW);
                return ExitCode::OK;
            }

            $this->stdout("Found {$totalForms} forms to process.\n\n", Console::FG_GREEN);

            /** @var IntegrationService $integrationService */
            $integrationService = TranslationManager::getInstance()->get('integrations');
            $formieIntegration = $integrationService->get('formie');
            if ($formieIntegration === null) {
                $this->stderr("Formie integration is not available.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $processed = 0;

            foreach ($forms as $form) {
                $this->stdout("Processing form: {$form->title} ({$form->handle})... ", Console::FG_CYAN);
                
                try {
                    $formieIntegration->captureTranslations($form);
                    $processed++;
                    $this->stdout("Done\n", Console::FG_GREEN);
                } catch (\Exception $e) {
                    $this->stdout("Failed\n", Console::FG_RED);
                    $this->stderr("  Error: {$e->getMessage()}\n", Console::FG_RED);
                }
            }

            $this->stdout("\n");
            $this->stdout("Processed {$processed} of {$totalForms} forms successfully.\n", Console::FG_GREEN);
            
            // Check for unused translations after capturing
            $this->stdout("Checking for unused translations...\n", Console::FG_YELLOW);
            $formieIntegration->checkUsage();
            $this->stdout("Usage check complete.\n", Console::FG_GREEN);

            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error capturing Formie translations: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Generate Formie translation files
     */
    public function actionGenerateFormie(): int
    {
        $this->stdout("Generating Formie translation files...\n", Console::FG_YELLOW);

        try {
            $generationService = TranslationManager::getInstance()->generate;
            $success = $generationService->generateFormieTranslations();

            if ($success) {
                // Get count matching actual generation criteria (translated only, all sites)
                $translations = TranslationManager::getInstance()->translations->getTranslations([
                    'type' => 'forms',
                    'status' => 'translated',
                    'allSites' => true,
                ]);
                $count = count($translations);
                $this->stdout("Generated {$count} translated entries into Formie translation files\n", Console::FG_GREEN);
                return ExitCode::OK;
            } else {
                $this->stderr("Generation failed\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        } catch (\Exception $e) {
            $this->stderr("Error generating Formie translation files: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Generate site translation files
     */
    public function actionGenerateSite(): int
    {
        $this->stdout("Generating site translation files...\n", Console::FG_YELLOW);

        try {
            $generationService = TranslationManager::getInstance()->generate;
            $category = TranslationManager::getInstance()->getSettings()->translationCategory;
            $result = $generationService->generateSiteTranslations();

            $this->stdout("Generated site translation files per language\n", Console::FG_GREEN);

            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error generating site translation files: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Generate all translation files (Formie + site)
     */
    public function actionGenerateAll(): int
    {
        $this->stdout("Generating all translation files...\n\n", Console::FG_YELLOW);

        // Generate Formie
        $this->stdout("1. Generating Formie translation files...\n", Console::FG_CYAN);
        $formieResult = $this->actionGenerateFormie();

        if ($formieResult !== ExitCode::OK) {
            return $formieResult;
        }

        $this->stdout("\n");

        // Generate site
        $this->stdout("2. Generating site translation files...\n", Console::FG_CYAN);
        $siteResult = $this->actionGenerateSite();

        if ($siteResult !== ExitCode::OK) {
            return $siteResult;
        }

        $this->stdout("\nAll translation files generated successfully!\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Import existing PHP translation files from disk into the database.
     *
     * Mirrors the CP PHP import: discovers every {language}/{category}.php file
     * under the generation path and creates/updates rows for all languages,
     * preserving the translated values.
     *
     * Run with no scope to see a dry-run summary of what could be imported
     * (nothing is written). Pass --language and/or --category to import a
     * subset, or --all to import every discovered file.
     *
     * Usage:
     * - php craft translation-manager/translations/import                      (dry-run summary)
     * - php craft translation-manager/translations/import --all                (import everything)
     * - php craft translation-manager/translations/import --language=ar
     * - php craft translation-manager/translations/import --category=formie
     * - php craft translation-manager/translations/import --language=ar --category=formie
     *
     * @since 5.25.0
     */
    public function actionImport(): int
    {
        $this->stdout("Importing PHP translation files...\n", Console::FG_YELLOW);

        $grouped = PhpTranslationsHelper::findFiles();

        if (empty($grouped)) {
            $this->stdout("No translation files found under the generation path.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $service = TranslationManager::getInstance()->translations;

        // Require an explicit scope: a --language/--category filter, or --all.
        // With no scope, print a dry-run summary of what could be imported and
        // stop — importing the entire translations tree (stray test fixtures
        // included) in one shot is rarely what's intended.
        $explicitScope = $this->language !== null || $this->category !== null;
        if (!$explicitScope && !$this->all) {
            $this->printImportSummary($grouped, $service);
            return ExitCode::OK;
        }

        // Flatten to a list of file descriptors, applying --language / --category filters.
        $targets = [];
        foreach ($grouped as $fileLanguage => $files) {
            if ($this->language !== null && $fileLanguage !== $this->language) {
                continue;
            }
            foreach ($files as $info) {
                if ($this->category !== null && $info['category'] !== $this->category) {
                    continue;
                }
                $targets[] = $info;
            }
        }

        if (empty($targets)) {
            $this->stdout("No matching translation files found.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $settings = TranslationManager::getInstance()->getSettings();

        // Mirror the CP import: back up first when backups-on-import is enabled.
        if ($settings->backupEnabled && $settings->backupOnImport) {
            $backupPath = TranslationManager::getInstance()->backup->createBackup('before_php_import');
            if ($backupPath) {
                $this->stdout("Created backup: " . basename($backupPath) . "\n", Console::FG_GREY);
            }
        }

        $totalImported = 0;
        $totalUpdated = 0;
        $totalErrors = 0;

        foreach ($targets as $info) {
            $fileLanguage = $info['language'];
            $category = $info['category'];
            $filePath = $info['value'];

            $this->stdout("\n{$fileLanguage}/{$category}.php\n", Console::FG_CYAN);

            // Validate / auto-register the category, same rules as the CP import.
            $status = $service->getImportCategoryStatus($category);
            if (isset($status['error'])) {
                $this->stderr("  Skipped: {$status['error']}\n", Console::FG_RED);
                continue;
            }
            if (($status['requiresRegistration'] ?? false) && !$service->registerImportCategory($category)) {
                $this->stderr("  Skipped: category \"{$category}\" is not enabled and cannot be added automatically (configured in config/translation-manager.php).\n", Console::FG_RED);
                continue;
            }

            // Parse without executing the file.
            $parsed = PhpTranslationsHelper::safeParseFileForConsole($filePath);
            if ($parsed === null) {
                $this->stderr("  Could not parse file (may contain unsafe code).\n", Console::FG_RED);
                continue;
            }

            $entries = [];
            foreach ($parsed as $source => $translation) {
                $entries[] = ['key' => (string)$source, 'value' => (string)$translation];
            }

            $result = $service->importPhpEntries($entries, $fileLanguage, $category, null);

            $errorCount = count($result['errors']);
            $this->stdout("  Imported {$result['imported']} new, updated {$result['updated']} existing.\n", Console::FG_GREEN);
            if ($errorCount > 0) {
                $this->stderr("  {$errorCount} error(s).\n", Console::FG_RED);
                foreach ($result['errors'] as $error) {
                    $this->stderr("    - {$error}\n", Console::FG_RED);
                }
            }

            $totalImported += $result['imported'];
            $totalUpdated += $result['updated'];
            $totalErrors += $errorCount;
        }

        $this->stdout("\nDone. Imported {$totalImported} new, updated {$totalUpdated} existing across " . count($targets) . " file(s).\n", Console::FG_GREEN);
        if ($totalErrors > 0) {
            $this->stderr("{$totalErrors} error(s) — see above.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Print a dry-run summary of what a full import would touch: per-file key
     * counts and whether each file would import or be skipped (reserved
     * category, unreadable/unsafe file). Shown when `import` runs with no
     * --language/--category scope and no --all. Nothing is written.
     *
     * @param array<string, array<string, array{value: string, label: string, language: string, category: string}>> $grouped
     */
    private function printImportSummary(array $grouped, TranslationsService $service): void
    {
        $this->stdout("\nNo scope given — showing what could be imported (dry run; nothing changed).\n\n", Console::FG_CYAN);

        $totalFiles = 0;
        $importable = 0;
        $skipped = 0;
        $totalKeys = 0;

        foreach ($grouped as $fileLanguage => $files) {
            $this->stdout("{$fileLanguage}/\n", Console::FG_YELLOW);

            foreach ($files as $info) {
                $totalFiles++;
                $category = $info['category'];
                $label = '  ' . str_pad("{$category}.php", 26);

                $status = $service->getImportCategoryStatus($category);
                if (isset($status['error'])) {
                    $this->stdout($label . "skip — {$status['error']}\n", Console::FG_GREY);
                    $skipped++;
                    continue;
                }

                $parsed = PhpTranslationsHelper::safeParseFileForConsole($info['value']);
                if ($parsed === null) {
                    $this->stdout($label . "skip — unreadable (possibly unsafe code)\n", Console::FG_GREY);
                    $skipped++;
                    continue;
                }

                $keys = count($parsed);
                $totalKeys += $keys;
                $importable++;
                $note = ($status['requiresRegistration'] ?? false) ? ' (category would be added)' : '';
                $this->stdout($label . "{$keys} key(s){$note}\n", Console::FG_GREEN);
            }
        }

        $this->stdout("\nSummary: {$totalFiles} file(s) — {$importable} importable (~{$totalKeys} key(s)), {$skipped} would be skipped.\n", Console::FG_CYAN);
        $this->stdout("Re-run with --all to import everything, or narrow with --language / --category.\n", Console::FG_CYAN);
    }
}
