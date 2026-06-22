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
use craft\helpers\FileHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\translationmanager\helpers\FeatureGate;
use lindemannrock\translationmanager\helpers\PhpTranslationsHelper;
use lindemannrock\translationmanager\records\GenerationStatusRecord;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\services\TranslationsService;
use lindemannrock\translationmanager\TranslationManager;
use yii\console\ExitCode;
use yii\db\Expression;

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
    public $defaultAction = 'generate-all';

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
     * @var int Seconds to wait before running `generate-all`.
     * @since 5.28.0
     */
    public int $delay = 0;

    /**
     * @var bool Verify generated files and runtime message-source resolution after `generate-all`.
     * @since 5.28.0
     */
    public bool $verify = false;

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

        if ($actionID === 'generate-all') {
            $options[] = 'delay';
            $options[] = 'verify';
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
        if (!FeatureGate::aiTranslationsEnabled()) {
            $this->stderr("AI translation is not available.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

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
     * Capture all existing translations for a form provider.
     */
    public function actionCaptureProvider(string $provider): int
    {
        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');
        $integration = $integrationService->get($provider);

        if ($integration === null || $integration->getSourceType() !== 'forms') {
            $this->stderr("Provider \"{$provider}\" is not a registered form integration.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $pluginName = PluginHelper::getPluginName($integration->getPluginHandle(), ucfirst($integration->getName()));
        $this->stdout("Capturing {$pluginName} form translations...\n", Console::FG_YELLOW);

        if (!$integration->isAvailable()) {
            $this->stderr("{$pluginName} integration is not available.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!$integrationService->isIntegrationEnabled($provider)) {
            $this->stderr("{$pluginName} integration is not enabled in settings.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        try {
            $result = $integration->captureAll();

            $this->stdout("Processed {$result['processed']} form(s).\n", Console::FG_CYAN);
            $this->stdout("Captured {$result['captured']} translation row(s).\n", Console::FG_GREEN);

            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error capturing {$pluginName} translations: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Generate translation files for a form provider.
     */
    public function actionGenerateProvider(string $provider): int
    {
        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');
        $integration = $integrationService->get($provider);

        if ($integration === null || $integration->getSourceType() !== 'forms') {
            $this->stderr("Provider \"{$provider}\" is not a registered form integration.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $pluginName = PluginHelper::getPluginName($integration->getPluginHandle(), ucfirst($integration->getName()));
        $this->stdout("Generating {$pluginName} translation files...\n", Console::FG_YELLOW);

        if (!$integration->isAvailable() || !$integrationService->isIntegrationEnabled($provider)) {
            $this->stderr("{$pluginName} integration is not available.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        try {
            $result = TranslationManager::getInstance()->generate->generateProviderTranslations($provider);

            if (!($result['success'] ?? false)) {
                $this->stderr("Generation failed\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $count = (int)($result['translationCount'] ?? 0);
            $this->stdout("Generated {$count} translated entries into {$pluginName} translation files\n", Console::FG_GREEN);

            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error generating {$pluginName} translation files: {$e->getMessage()}\n", Console::FG_RED);
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
            $result = $generationService->generateSiteTranslations();
            $count = (int)($result['translationCount'] ?? 0);

            $this->stdout("Generated {$count} translated entries into site translation files\n", Console::FG_GREEN);

            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error generating site translation files: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Generate site translation files for one category.
     *
     * @since 5.25.1
     */
    public function actionGenerateCategory(string $category): int
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $enabledCategories = $settings->getEnabledCategories();

        if (!in_array($category, $enabledCategories, true)) {
            $this->stderr("Category \"{$category}\" is not enabled.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Generating {$category} translation files...\n", Console::FG_YELLOW);

        try {
            $result = TranslationManager::getInstance()->generate->generateCategoryTranslations($category);

            if (!($result['success'] ?? false)) {
                $this->stderr("Generation failed\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $count = (int)($result['translationCount'] ?? 0);
            $this->stdout("Generated {$count} translated entries into {$category} translation files\n", Console::FG_GREEN);

            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error generating {$category} translation files: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Generate all translation files (enabled form providers + site)
     */
    public function actionGenerateAll(): int
    {
        $this->stdout("Generating all translation files...\n\n", Console::FG_YELLOW);

        if ($this->delay < 0 || $this->delay > 300) {
            $this->stderr("Invalid delay. Use a value between 0 and 300 seconds.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($this->delay > 0) {
            $this->stdout("Waiting {$this->delay} second(s) before generation...\n", Console::FG_YELLOW);
            $this->sleepBeforeGenerate($this->delay);
        }

        $settings = TranslationManager::getInstance()->getSettings();
        $generationService = TranslationManager::getInstance()->generate;
        $this->stdout("Generation path: {$settings->getGenerationPath()}\n", Console::FG_CYAN);

        $result = $generationService->generateAll();

        foreach (($result['results'] ?? []) as $name => $scopeResult) {
            if (!is_array($scopeResult)) {
                continue;
            }

            $success = (bool)($scopeResult['success'] ?? false);
            $count = (int)($scopeResult['translationCount'] ?? 0);
            $written = (int)($scopeResult['writtenFileCount'] ?? 0);
            $deleted = (int)($scopeResult['deletedFileCount'] ?? 0);
            $categories = implode(', ', (array)($scopeResult['categories'] ?? []));
            $status = $success
                ? "Done ({$count} translations, {$written} file(s) written, {$deleted} stale file(s) deleted)"
                : 'Failed';
            $color = $success ? Console::FG_GREEN : Console::FG_RED;
            $this->stdout("{$name}: {$status}\n", $color);
            if ($categories !== '') {
                $this->stdout("  Categories: {$categories}\n", Console::FG_GREY);
            }

            if (!$success) {
                $result['success'] = false;
                TranslationManager::getInstance()->generationStatus->recordGenerationResult(
                    $result,
                    GenerationStatusRecord::REASON_CLI,
                    GenerationStatusRecord::TRIGGER_CLI,
                );
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $this->stdout(
            "\nTotal: {$result['translationCount']} translations, {$result['writtenFileCount']} file(s) written, {$result['deletedFileCount']} stale file(s) deleted\n",
            Console::FG_CYAN
        );

        $verificationPassed = !$this->verify || $this->verifyGeneratedTranslationRuntime($result);
        if (!$verificationPassed) {
            $result['success'] = false;
        }

        TranslationManager::getInstance()->generationStatus->recordGenerationResult(
            $result,
            GenerationStatusRecord::REASON_CLI,
            GenerationStatusRecord::TRIGGER_CLI,
        );

        if (!$verificationPassed) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\nAll translation files generated successfully!\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Wait before generation. Extracted so tests can assert delay handling
     * without slowing the suite down.
     */
    protected function sleepBeforeGenerate(int $seconds): void
    {
        sleep($seconds);
    }

    /**
     * Verify that a small sample of generated rows exists on disk and resolves
     * through Craft's message source in this runtime.
     *
     * @param array<string,mixed> $result
     */
    protected function verifyGeneratedTranslationRuntime(array $result): bool
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $basePath = $settings->getGenerationPath();
        $categories = [];

        foreach (($result['results'] ?? []) as $scopeResult) {
            if (!is_array($scopeResult) || !($scopeResult['success'] ?? false)) {
                continue;
            }
            foreach ((array)($scopeResult['categories'] ?? []) as $category) {
                if (is_string($category) && $category !== '') {
                    $categories[] = $category;
                }
            }
        }

        $categories = array_values(array_unique($categories));
        if ($categories === []) {
            $this->stdout("Verification skipped: no generated categories in result.\n", Console::FG_YELLOW);
            return true;
        }

        $this->stdout("\nVerifying generated translations...\n", Console::FG_YELLOW);

        /** @var TranslationRecord[] $records */
        $records = TranslationRecord::find()
            ->where([
                'category' => $categories,
                'status' => 'translated',
            ])
            ->andWhere(['not', ['translation' => null]])
            ->andWhere(['<>', 'translation', ''])
            ->andWhere(new Expression('[[translation]] <> [[source]]'))
            ->orderBy(['category' => SORT_ASC, 'language' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(20)
            ->all();

        if ($records === []) {
            $this->stdout("Verification skipped: no translated sample rows with non-source values were found.\n", Console::FG_YELLOW);
            return true;
        }

        $checked = 0;
        foreach ($records as $record) {
            $language = $settings->mapLanguage((string) $record->language);
            $category = (string) $record->category;
            $file = $this->resolveGeneratedFilePath($basePath, $language, $category);

            if ($file === null || !is_file($file)) {
                $displayFile = $file ?? FileHelper::normalizePath($basePath . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . $category . '.php');
                $this->stderr("Verification failed: missing generated file {$displayFile}\n", Console::FG_RED);
                return false;
            }

            $messages = require $file;
            if (!is_array($messages) || ($messages[$record->source] ?? null) !== $record->translation) {
                $this->stderr("Verification failed: {$file} does not contain the expected value for row {$record->id}.\n", Console::FG_RED);
                return false;
            }

            $resolved = \Craft::t($category, (string) $record->source, [], (string) $record->language);
            if ($resolved !== $record->translation) {
                $this->stderr("Verification failed: Craft::t({$category}, row {$record->id}, {$record->language}) resolved a different value.\n", Console::FG_RED);
                return false;
            }

            $checked++;
        }

        $this->stdout("Verification passed: {$checked} generated translation sample(s) resolved through Craft::t().\n", Console::FG_GREEN);
        return true;
    }

    private function resolveGeneratedFilePath(string $basePath, string $language, string $category): ?string
    {
        $realBasePath = realpath($basePath);
        if ($realBasePath === false) {
            return null;
        }

        $realBasePath = FileHelper::normalizePath($realBasePath);
        $targetFile = FileHelper::normalizePath($realBasePath . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . $category . '.php');
        if (!$this->isPathInside($targetFile, $realBasePath)) {
            return null;
        }

        $realTargetFile = realpath($targetFile);
        if ($realTargetFile === false) {
            return $targetFile;
        }

        $realTargetFile = FileHelper::normalizePath($realTargetFile);
        if (!$this->isPathInside($realTargetFile, $realBasePath)) {
            return null;
        }

        return $realTargetFile;
    }

    private function isPathInside(string $path, string $basePath): bool
    {
        $path = FileHelper::normalizePath($path);
        $basePath = rtrim(FileHelper::normalizePath($basePath), '/\\');

        return $path !== $basePath && str_starts_with($path, $basePath . DIRECTORY_SEPARATOR);
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
