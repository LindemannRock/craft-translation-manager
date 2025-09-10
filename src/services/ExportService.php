<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Service for exporting translations to various formats
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\services;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use lindemannrock\translationmanager\TranslationManager;
use lindemannrock\translationmanager\traits\LoggingTrait;

/**
 * Export Service
 */
class ExportService extends Component
{
    use LoggingTrait;
    /**
     * Export all translations
     */
    public function exportAll(): array
    {
        Craft::info('Starting exportAll()', __METHOD__);
        $results = [];
        
        Craft::info('About to export Formie translations', __METHOD__);
        $results['formie'] = $this->exportFormieTranslations();
        Craft::info('Formie export result: ' . ($results['formie'] ? 'success' : 'failed'), __METHOD__);
        
        Craft::info('About to export site translations', __METHOD__);
        $results['site'] = $this->exportSiteTranslations();
        Craft::info('Site export result: ' . ($results['site'] ? 'success' : 'failed'), __METHOD__);
        
        Craft::info('exportAll() completed', __METHOD__);
        return $results;
    }

    /**
     * Export Formie translations to translation files
     */
    public function exportFormieTranslations(): bool
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $translationsService = TranslationManager::getInstance()->translations;
        
        Craft::info('Starting Formie translation export', __METHOD__);
        
        // NEW: Get Formie translations for ALL sites
        $translations = $translationsService->getTranslations([
            'type' => 'forms',
            'status' => 'translated',
            'allSites' => true  // Get from all sites
        ]);

        Craft::info('Found ' . count($translations) . ' Formie translations to export', __METHOD__);

        if (empty($translations)) {
            Craft::info('No Formie translations to export', __METHOD__);
            
            // Delete existing files if they exist to prevent stale translations
            $basePath = $settings->getExportPath();
            
            // Get actual site languages dynamically
            $sites = TranslationManager::getInstance()->getAllowedSites();
            foreach ($sites as $site) {
                $file = $basePath . '/' . $site->language . '/formie.php';
                if (file_exists($file)) {
                    @unlink($file);
                    Craft::info("Deleted stale Formie file: {$file}", __METHOD__);
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
        $basePath = $settings->getExportPath();
        
        foreach ($translationsBySite as $siteId => $siteTranslations) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            if ($site) {
                $sitePath = $basePath . '/' . $site->language;
                FileHelper::createDirectory($sitePath);
                
                // Write translation file for this site
                $this->writeTranslationFile($sitePath . '/formie.php', $siteTranslations, $site->name);
                Craft::info("Exported {count} Formie translations for {site} ({language})", [
                    'count' => count($siteTranslations),
                    'site' => $site->name,
                    'language' => $site->language
                ]);
            }
        }

        // Don't clear caches - let them refresh naturally

        return true;
    }

    /**
     * Export site translations to translation files
     */
    public function exportSiteTranslations(): bool
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $translationsService = TranslationManager::getInstance()->translations;
        $category = $settings->translationCategory;
        
        Craft::info('Starting site translation export for category: ' . $category, __METHOD__);
        
        // NEW: Get site translations for ALL sites
        $translations = $translationsService->getTranslations([
            'type' => 'site',
            'status' => 'translated',
            'allSites' => true  // Get from all sites
        ]);

        Craft::info('Found ' . count($translations) . ' site translations to export', __METHOD__);

        if (empty($translations)) {
            Craft::info('No site translations to export', __METHOD__);
            
            // Delete existing files if they exist to prevent stale translations
            $basePath = $settings->getExportPath();
            $filename = $category . '.php';
            
            // Get actual site languages dynamically
            $sites = TranslationManager::getInstance()->getAllowedSites();
            foreach ($sites as $site) {
                $file = $basePath . '/' . $site->language . '/' . $filename;
                if (file_exists($file)) {
                    @unlink($file);
                    Craft::info("Deleted stale file: {$file}", __METHOD__);
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
        $basePath = $settings->getExportPath();
        $filename = $category . '.php';
        
        foreach ($translationsBySite as $siteId => $siteTranslations) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            if ($site) {
                $sitePath = $basePath . '/' . $site->language;
                FileHelper::createDirectory($sitePath);
                
                // Write translation file for this site
                $this->writeTranslationFile($sitePath . '/' . $filename, $siteTranslations, $site->name);
                Craft::info("Exported {count} site translations for {site} ({language})", [
                    'count' => count($siteTranslations),
                    'site' => $site->name,
                    'language' => $site->language
                ]);
            }
        }

        // Don't clear caches - let them refresh naturally

        return true;
    }

    /**
     * Export selected translations
     */
    public function exportSelected(array $ids): string
    {
        $translationsService = TranslationManager::getInstance()->translations;
        $settings = TranslationManager::getInstance()->getSettings();
        
        $this->logInfo('Exporting selected translations', ['count' => count($ids)]);
        
        // Build CSV content with UTF-8 BOM for Excel compatibility
        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM
        
        // Build header based on showContext setting (Updated for multi-site)
        if ($settings->showContext) {
            $csv .= "Translation Key,Translation,Type,Context,Status,Site ID,Site Language\n";
        } else {
            $csv .= "Translation Key,Translation,Type,Status,Site ID,Site Language\n";
        }
        
        foreach ($ids as $id) {
            $translation = $translationsService->getTranslationById($id);
            if ($translation) {
                // Sanitize for CSV injection - preserve original spacing
                $translationKey = $this->sanitizeForCsv($translation->translationKey);
                $translationText = $this->sanitizeForCsv($translation->translation ?? '');
                $type = strpos($translation->context, 'formie.') === 0 ? TranslationManager::getFormiePluginName() : 'Site';
                
                // Get site information
                $site = Craft::$app->getSites()->getSiteById($translation->siteId);
                $siteLanguage = $site ? $site->language : 'unknown';
                
                if ($settings->showContext) {
                    $context = $this->sanitizeForCsv($translation->context);
                    $csv .= "\"{$translationKey}\",\"{$translationText}\",\"{$type}\",\"{$context}\",\"{$translation->status}\",\"{$translation->siteId}\",\"{$siteLanguage}\"\n";
                } else {
                    $csv .= "\"{$translationKey}\",\"{$translationText}\",\"{$type}\",\"{$translation->status}\",\"{$translation->siteId}\",\"{$siteLanguage}\"\n";
                }
            }
        }
        
        return $csv;
    }

    /**
     * Write translation file
     */
    private function writeTranslationFile(string $path, array $translations, string $language): void
    {
        Craft::info("Writing {$language} translation file to: {$path}", __METHOD__);
        Craft::info("Number of translations: " . count($translations), __METHOD__);
        
        $content = "<?php\n/**\n * {$language} translations\n * Auto-generated: " . date('Y-m-d H:i:s') . "\n */\nreturn [\n";
        
        foreach ($translations as $key => $value) {
            // Use var_export to properly escape PHP strings without double-escaping
            $exportedKey = var_export($key, true);
            $exportedValue = var_export($value, true);
            $content .= "    {$exportedKey} => {$exportedValue},\n";
        }
        
        $content .= "];\n";
        
        // Secure file writing with atomic operation
        $tempFile = $path . '.tmp';
        
        if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
            Craft::error("Failed to write translation file to: {$tempFile}", __METHOD__);
            throw new \Exception('Failed to write translation file');
        }
        
        if (!rename($tempFile, $path)) {
            @unlink($tempFile);
            Craft::error("Failed to move translation file from {$tempFile} to {$path}", __METHOD__);
            throw new \Exception('Failed to move translation file');
        }
        
        Craft::info("Successfully wrote translation file: {$path}", __METHOD__);
    }


    /**
     * Sanitize value for CSV to prevent injection attacks
     */
    private function sanitizeForCsv(string $value): string
    {
        // First escape quotes
        $value = str_replace('"', '""', $value);
        
        // Prevent CSV injection by prefixing dangerous characters
        $dangerous = ['=', '+', '-', '@', '|', '%'];
        $firstChar = substr($value, 0, 1);
        
        if (in_array($firstChar, $dangerous)) {
            $value = "'" . $value;
        }
        
        return $value;
    }

}