<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Console controller for translation management tasks
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use lindemannrock\translationmanager\TranslationManager;
use yii\console\ExitCode;

/**
 * Console Translations Controller
 */
class TranslationsController extends Controller
{
    /**
     * @var string The default action
     */
    public $defaultAction = 'capture-formie';

    /**
     * Capture all existing Formie form translations
     */
    public function actionCaptureFormie(): int
    {
        $this->stdout("Capturing Formie form translations...\n", Console::FG_YELLOW);
        
        // Check if Formie is installed
        if (!class_exists('verbb\\formie\\Formie')) {
            $this->stderr("Formie plugin is not installed.\n", Console::FG_RED);
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

            $formieService = TranslationManager::getInstance()->formie;
            $processed = 0;

            foreach ($forms as $form) {
                $this->stdout("Processing form: {$form->title} ({$form->handle})... ", Console::FG_CYAN);
                
                try {
                    $formieService->captureFormTranslations($form);
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
            $formieService->checkFormUsage();
            $this->stdout("Usage check complete.\n", Console::FG_GREEN);

            return ExitCode::OK;

        } catch (\Exception $e) {
            $this->stderr("Error capturing Formie translations: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Export Formie translations to PHP files
     */
    public function actionExportFormie(): int
    {
        $this->stdout("Exporting Formie translations...\n", Console::FG_YELLOW);
        
        try {
            $exportService = TranslationManager::getInstance()->export;
            $count = $exportService->exportFormieTranslations();
            
            $this->stdout("Exported {$count} translations to Formie translation files\n", Console::FG_GREEN);
            
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error exporting Formie translations: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Export site translations to PHP files
     */
    public function actionExportSite(): int
    {
        $this->stdout("Exporting site translations...\n", Console::FG_YELLOW);
        
        try {
            $exportService = TranslationManager::getInstance()->export;
            $category = TranslationManager::getInstance()->getSettings()->translationCategory;
            $result = $exportService->exportSiteTranslations();
            
            $this->stdout("Exported site translations to per-language files\n", Console::FG_GREEN);
            
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error exporting site translations: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Export all translations (Formie + site)
     */
    public function actionExportAll(): int
    {
        $this->stdout("Exporting all translations...\n\n", Console::FG_YELLOW);
        
        // Export Formie
        $this->stdout("1. Exporting Formie translations...\n", Console::FG_CYAN);
        $formieResult = $this->actionExportFormie();
        
        if ($formieResult !== ExitCode::OK) {
            return $formieResult;
        }
        
        $this->stdout("\n");
        
        // Export site
        $this->stdout("2. Exporting site translations...\n", Console::FG_CYAN);
        $siteResult = $this->actionExportSite();
        
        if ($siteResult !== ExitCode::OK) {
            return $siteResult;
        }
        
        $this->stdout("\nAll translations exported successfully!\n", Console::FG_GREEN);
        
        return ExitCode::OK;
    }

    /**
     * Import existing Formie translation files to database
     */
    public function actionImportFormie(): int
    {
        $this->stdout("Importing existing Formie translation files...\n", Console::FG_YELLOW);
        
        $settings = TranslationManager::getInstance()->getSettings();
        $translationPath = $settings->getExportPath();
        $files = [];
        
        // Find all formie.php files in language directories
        if (is_dir($translationPath)) {
            $dirs = scandir($translationPath);
            foreach ($dirs as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir($translationPath . '/' . $dir)) {
                    $filePath = $translationPath . '/' . $dir . '/formie.php';
                    if (file_exists($filePath)) {
                        $files[$dir] = $filePath;
                    }
                }
            }
        }
        
        if (empty($files)) {
            $this->stdout("No Formie translation files found.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }
        
        $this->stdout("Found translation files for: " . implode(', ', array_keys($files)) . "\n\n", Console::FG_GREEN);
        
        try {
            $translationsService = TranslationManager::getInstance()->translations;
            $totalImported = 0;
            
            foreach ($files as $language => $filePath) {
                $this->stdout("Importing {$language} translations from {$filePath}...\n", Console::FG_CYAN);
                
                $translations = require $filePath;
                
                if (!is_array($translations)) {
                    $this->stderr("  Invalid translation file format.\n", Console::FG_RED);
                    continue;
                }
                
                $count = 0;
                foreach ($translations as $original => $translation) {
                    // Extract context from the translation key
                    $context = 'formie';
                    
                    $translationsService->createOrUpdateTranslation(
                        $original,
                        $context,
                        $language === 'ar' ? $translation : null
                    );
                    
                    $count++;
                }
                
                $this->stdout("  Imported {$count} translations.\n", Console::FG_GREEN);
                $totalImported += $count;
            }
            
            $this->stdout("\nTotal imported: {$totalImported} translations.\n", Console::FG_GREEN);
            
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            $this->stderr("Error importing translations: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}