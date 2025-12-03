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
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Export Service
 *
 * @since 1.0.0
 */
class ExportService extends Component
{
    use LoggingTrait;
    /**
     * Export all translations
     */
    public function exportAll(): array
    {
        $this->logInfo('Starting exportAll()');
        $results = [];

        $this->logInfo('About to export Formie translations');
        $results['formie'] = $this->exportFormieTranslations();
        $this->logInfo('Formie export result', ['success' => $results['formie']]);

        $this->logInfo('About to export site translations');
        $results['site'] = $this->exportSiteTranslations();
        $this->logInfo('Site export result', ['success' => $results['site']]);

        $this->logInfo('exportAll() completed');
        return $results;
    }

    /**
     * Export Formie translations to translation files
     */
    public function exportFormieTranslations(): bool
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $translationsService = TranslationManager::getInstance()->translations;
        
        $this->logInfo('Starting Formie translation export');

        // NEW: Get Formie translations for ALL sites
        $translations = $translationsService->getTranslations([
            'type' => 'forms',
            'status' => 'translated',
            'allSites' => true,  // Get from all sites
        ]);

        $this->logInfo('Found Formie translations to export', ['count' => count($translations)]);

        if (empty($translations)) {
            $this->logInfo('No Formie translations to export');
            
            // Delete existing files if they exist to prevent stale translations
            $basePath = $settings->getExportPath();
            
            // Get actual site languages dynamically
            $sites = TranslationManager::getInstance()->getAllowedSites();
            foreach ($sites as $site) {
                $file = $basePath . '/' . $site->language . '/formie.php';
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
        $basePath = $settings->getExportPath();
        
        foreach ($translationsBySite as $siteId => $siteTranslations) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            if ($site) {
                $sitePath = $basePath . '/' . $site->language;
                FileHelper::createDirectory($sitePath);
                
                // Write translation file for this site
                $this->writeTranslationFile($sitePath . '/formie.php', $siteTranslations, $site->name);
                $this->logInfo("Exported Formie translations", [
                    'count' => count($siteTranslations),
                    'site' => $site->name,
                    'language' => $site->language,
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
        
        $this->logInfo('Starting site translation export', ['category' => $category]);

        // NEW: Get site translations for ALL sites
        $translations = $translationsService->getTranslations([
            'type' => 'site',
            'status' => 'translated',
            'allSites' => true,  // Get from all sites
        ]);

        $this->logInfo('Found site translations to export', ['count' => count($translations)]);

        if (empty($translations)) {
            $this->logInfo('No site translations to export');
            
            // Delete existing files if they exist to prevent stale translations
            $basePath = $settings->getExportPath();
            $filename = $category . '.php';
            
            // Get actual site languages dynamically
            $sites = TranslationManager::getInstance()->getAllowedSites();
            foreach ($sites as $site) {
                $file = $basePath . '/' . $site->language . '/' . $filename;
                if (file_exists($file)) {
                    @unlink($file);
                    $this->logInfo("Deleted stale file", ['file' => $file]);
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
                $this->logInfo("Exported site translations", [
                    'count' => count($siteTranslations),
                    'site' => $site->name,
                    'language' => $site->language,
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
        
        $count = count($ids);
        $this->logInfo("Exporting selected translations", ['count' => $count]);
        
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
