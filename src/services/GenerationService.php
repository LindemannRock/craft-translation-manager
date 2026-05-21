<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Service for generating PHP translation files that Craft CMS uses for
 * multi-language support
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\services;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Generation Service
 *
 * @since 1.0.0
 */
class GenerationService extends Component
{
    use LoggingTrait;

    /**
     * Get the generation language for a site (applies locale mapping)
     */
    private function getGenerationLanguage(\craft\models\Site $site): string
    {
        $settings = TranslationManager::getInstance()->getSettings();
        return $settings->mapLanguage($site->language);
    }

    /**
     * Generate all translation files (Formie + site)
     */
    public function generateAll(): array
    {
        $this->logInfo('Starting generateAll()');
        $results = [];

        $this->logInfo('About to generate Formie translations');
        $results['formie'] = $this->generateFormieTranslations();
        $this->logInfo('Formie generation result', ['success' => $results['formie']]);

        $this->logInfo('About to generate site translations');
        $results['site'] = $this->generateSiteTranslations();
        $this->logInfo('Site generation result', ['success' => $results['site']]);

        $this->logInfo('generateAll() completed');
        return $results;
    }

    /**
     * Generate Formie translation files
     */
    public function generateFormieTranslations(): bool
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $translationsService = TranslationManager::getInstance()->translations;

        $this->logInfo('Starting Formie translation generation');

        // NEW: Get Formie translations for ALL sites
        $translations = $translationsService->getTranslations([
            'type' => 'forms',
            'status' => 'translated',
            'allSites' => true,  // Get from all sites
        ]);

        $this->logInfo('Found Formie translations to generate', ['count' => count($translations)]);

        if (empty($translations)) {
            $this->logInfo('No Formie translations to generate');

            // Delete existing files if they exist to prevent stale translations
            $basePath = $settings->getGenerationPath();

            // Get actual site languages dynamically
            $sites = TranslationManager::getInstance()->getAllowedSites();
            foreach ($sites as $site) {
                $generationLanguage = $this->getGenerationLanguage($site);
                $file = $basePath . '/' . $generationLanguage . '/formie.php';
                if (file_exists($file)) {
                    @unlink($file);
                    $this->logInfo("Deleted stale Formie file", ['file' => $file]);
                }
            }

            return true;
        }

        // NEW: Group translations by actual sites (not hardcoded languages)
        $translationsBySite = [];

        foreach ($translations as $translation) {
            $siteId = $translation['siteId'];
            if (!isset($translationsBySite[$siteId])) {
                $translationsBySite[$siteId] = [];
            }

            // Only include if there's a translation (not empty)
            if (!empty($translation['translation'])) {
                $translationsBySite[$siteId][$translation['translationKey']] = $translation['translation'];
            }
        }

        // Create translation files for each site
        $basePath = $settings->getGenerationPath();

        foreach ($translationsBySite as $siteId => $siteTranslations) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            if ($site) {
                $generationLanguage = $this->getGenerationLanguage($site);
                $sitePath = $basePath . '/' . $generationLanguage;
                FileHelper::createDirectory($sitePath);

                // Write translation file for this site
                $this->writeTranslationFile($sitePath . '/formie.php', $siteTranslations, $site->name);
                $this->logInfo("Generated Formie translations", [
                    'count' => count($siteTranslations),
                    'site' => $site->name,
                    'language' => $generationLanguage,
                ]);
            }
        }

        // Don't clear caches - let them refresh naturally

        return true;
    }

    /**
     * Generate site translation files (per category)
     */
    public function generateSiteTranslations(): bool
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $translationsService = TranslationManager::getInstance()->translations;
        $categories = $settings->getEnabledCategories();

        $this->logInfo('Starting site translation generation', ['categories' => $categories]);

        // Get site translations for ALL sites and ALL enabled categories
        $translations = $translationsService->getTranslations([
            'type' => 'site',
            'status' => 'translated',
            'allSites' => true,  // Get from all sites
        ]);

        $this->logInfo('Found site translations to generate', ['count' => count($translations)]);

        $basePath = $settings->getGenerationPath();
        $sites = TranslationManager::getInstance()->getAllowedSites();

        if (empty($translations)) {
            $this->logInfo('No site translations to generate');

            // Delete existing files for all categories to prevent stale translations
            foreach ($categories as $category) {
                $filename = $category . '.php';
                foreach ($sites as $site) {
                    $generationLanguage = $this->getGenerationLanguage($site);
                    $file = $basePath . '/' . $generationLanguage . '/' . $filename;
                    if (file_exists($file)) {
                        @unlink($file);
                        $this->logInfo("Deleted stale file", ['file' => $file, 'category' => $category]);
                    }
                }
            }

            return true;
        }

        // Group translations by category, then by MAPPED language (not siteId)
        // This ensures translations saved under mapped language (e.g., 'en') are generated correctly
        $translationsByCategoryAndLanguage = [];

        foreach ($translations as $translation) {
            $category = $translation['category'] ?? $settings->getPrimaryCategory();
            // Use the translation's language field, then apply mapping
            $language = $translation['language'] ?? 'en';
            $mappedLanguage = $settings->mapLanguage($language);

            if (!isset($translationsByCategoryAndLanguage[$category])) {
                $translationsByCategoryAndLanguage[$category] = [];
            }
            if (!isset($translationsByCategoryAndLanguage[$category][$mappedLanguage])) {
                $translationsByCategoryAndLanguage[$category][$mappedLanguage] = [];
            }

            // Only include if there's a translation (not empty)
            if (!empty($translation['translation'])) {
                $translationsByCategoryAndLanguage[$category][$mappedLanguage][$translation['translationKey']] = $translation['translation'];
            }
        }

        // Create translation files for each category and language
        foreach ($translationsByCategoryAndLanguage as $category => $translationsByLanguage) {
            $filename = $category . '.php';

            foreach ($translationsByLanguage as $generationLanguage => $langTranslations) {
                $sitePath = $basePath . '/' . $generationLanguage;
                FileHelper::createDirectory($sitePath);

                // Write translation file for this language and category
                $this->writeTranslationFile($sitePath . '/' . $filename, $langTranslations, $generationLanguage);
                $this->logInfo("Generated site translations", [
                    'count' => count($langTranslations),
                    'language' => $generationLanguage,
                    'category' => $category,
                ]);
            }
        }

        // Clean up stale files for categories that have no translations
        foreach ($categories as $category) {
            if (!isset($translationsByCategoryAndLanguage[$category])) {
                $filename = $category . '.php';
                foreach ($sites as $site) {
                    $generationLanguage = $this->getGenerationLanguage($site);
                    $file = $basePath . '/' . $generationLanguage . '/' . $filename;
                    if (file_exists($file)) {
                        @unlink($file);
                        $this->logInfo("Deleted stale file (no translations)", ['file' => $file, 'category' => $category]);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Generate a single category's translation files
     *
     * @since 5.0.0
     */
    public function generateCategoryTranslations(string $category): bool
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $translationsService = TranslationManager::getInstance()->translations;

        $this->logInfo('Starting single category translation generation', ['category' => $category]);

        // Get translations for this specific category across ALL sites
        $translations = $translationsService->getTranslations([
            'type' => 'site',
            'category' => $category,
            'status' => 'translated',
            'allSites' => true,
        ]);

        $this->logInfo('Found translations to generate', ['count' => count($translations), 'category' => $category]);

        $basePath = $settings->getGenerationPath();
        $sites = TranslationManager::getInstance()->getAllowedSites();
        $filename = $category . '.php';

        if (empty($translations)) {
            $this->logInfo('No translations to generate for category', ['category' => $category]);

            // Delete existing files for this category to prevent stale translations
            foreach ($sites as $site) {
                $generationLanguage = $this->getGenerationLanguage($site);
                $file = $basePath . '/' . $generationLanguage . '/' . $filename;
                if (file_exists($file)) {
                    @unlink($file);
                    $this->logInfo("Deleted stale file", ['file' => $file, 'category' => $category]);
                }
            }

            return true;
        }

        // Group translations by site
        $translationsBySite = [];

        foreach ($translations as $translation) {
            $siteId = $translation['siteId'];

            if (!isset($translationsBySite[$siteId])) {
                $translationsBySite[$siteId] = [];
            }

            // Only include if there's a translation (not empty)
            if (!empty($translation['translation'])) {
                $translationsBySite[$siteId][$translation['translationKey']] = $translation['translation'];
            }
        }

        // Create translation files for each site
        foreach ($translationsBySite as $siteId => $siteTranslations) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            if ($site) {
                $generationLanguage = $this->getGenerationLanguage($site);
                $sitePath = $basePath . '/' . $generationLanguage;
                FileHelper::createDirectory($sitePath);

                // Write translation file for this site and category
                $this->writeTranslationFile($sitePath . '/' . $filename, $siteTranslations, $site->name);
                $this->logInfo("Generated category translations", [
                    'count' => count($siteTranslations),
                    'site' => $site->name,
                    'language' => $generationLanguage,
                    'category' => $category,
                ]);
            }
        }

        return true;
    }

    /**
     * Write translation file
     */
    private function writeTranslationFile(string $path, array $translations, string $language): void
    {
        $this->logInfo("Writing translation file", [
            'language' => $language,
            'path' => $path,
            'count' => count($translations),
        ]);

        $content = "<?php\n/**\n * {$language} translations\n * Auto-generated: " . date('Y-m-d H:i:s') . "\n */\nreturn [\n";

        foreach ($translations as $key => $value) {
            // Force keys to always be strings (especially important for numeric strings)
            // to prevent PHP from treating them as integer keys
            $exportedKey = var_export((string)$key, true);
            $exportedValue = var_export($value, true);
            $content .= "    {$exportedKey} => {$exportedValue},\n";
        }

        $content .= "];\n";

        // Secure file writing with atomic operation
        $tempFile = $path . '.tmp';

        if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
            $this->logError("Failed to write translation file", ['tempFile' => $tempFile]);
            throw new \Exception('Failed to write translation file');
        }

        if (!rename($tempFile, $path)) {
            @unlink($tempFile);
            $this->logError("Failed to move translation file", [
                'from' => $tempFile,
                'to' => $path,
            ]);
            throw new \Exception('Failed to move translation file');
        }

        $this->logInfo("Successfully wrote translation file", ['path' => $path]);
    }
}
