<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Maintenance console commands
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
 * Translation Manager Maintenance Commands
 *
 * @since 1.0.0
 */
class MaintenanceController extends Controller
{
    /**
     * Scan all templates for translation usage and mark unused translations
     *
     * @since 1.0.0
     */
    public function actionScanTemplates(): int
    {
        $this->stdout('Scanning templates for unused translations...' . PHP_EOL, Console::FG_BLUE);
        
        $results = TranslationManager::getInstance()->translations->scanTemplatesForUnused();
        
        $this->stdout('Scan Results:' . PHP_EOL, Console::FG_GREEN);
        $this->stdout("- Files scanned: {$results['scanned_files']}" . PHP_EOL);
        $this->stdout("- Translation keys found: " . count($results['found_keys']) . PHP_EOL);
        if (isset($results['created']) && $results['created'] > 0) {
            $this->stdout("- Created: {$results['created']}" . PHP_EOL, Console::FG_CYAN);
        }
        $this->stdout("- Marked as unused: {$results['marked_unused']}" . PHP_EOL);
        $this->stdout("- Reactivated: {$results['reactivated']}" . PHP_EOL);
        
        if (!empty($results['errors'])) {
            $this->stdout('Errors:' . PHP_EOL, Console::FG_RED);
            foreach ($results['errors'] as $error) {
                $this->stdout("- {$error}" . PHP_EOL, Console::FG_RED);
            }
        }
        
        if ($results['marked_unused'] > 0) {
            $this->stdout(PHP_EOL . 'You can now delete unused translations via:' . PHP_EOL);
            $this->stdout('- Control Panel: Translations > Filter by "Unused" > Delete Selected' . PHP_EOL);
            $this->stdout('- Console: craft translation-manager/maintenance/clean-unused' . PHP_EOL);
        }
        
        return ExitCode::OK;
    }
    
    /**
     * Delete all unused translations
     *
     * @since 1.0.0
     */
    public function actionCleanUnused(): int
    {
        $this->stdout('Finding unused translations...' . PHP_EOL, Console::FG_BLUE);
        
        $unusedTranslations = (new \craft\db\Query())
            ->from('{{%translationmanager_translations}}')
            ->where(['status' => 'unused'])
            ->all();
            
        $count = count($unusedTranslations);
        
        if ($count === 0) {
            $this->stdout('No unused translations found.' . PHP_EOL, Console::FG_GREEN);
            return ExitCode::OK;
        }
        
        $this->stdout("Found {$count} unused translations." . PHP_EOL);
        
        if ($this->interactive) {
            if (!$this->confirm("Delete {$count} unused translations?")) {
                $this->stdout('Cancelled.' . PHP_EOL, Console::FG_YELLOW);
                return ExitCode::OK;
            }
        }
        
        $deleted = TranslationManager::getInstance()->translations->deleteTranslations(
            array_column($unusedTranslations, 'id')
        );
        
        $this->stdout("Deleted {$deleted} unused translations." . PHP_EOL, Console::FG_GREEN);
        
        return ExitCode::OK;
    }
    
    /**
     * Show template scanning preview without making changes
     *
     * @since 1.0.0
     */
    public function actionPreviewScan(): int
    {
        $this->stdout('Previewing template scan (no changes will be made)...' . PHP_EOL, Console::FG_BLUE);
        
        try {
            $settings = TranslationManager::getInstance()->getSettings();
            $category = $settings->translationCategory;
            $templatePath = Craft::$app->getPath()->getSiteTemplatesPath();
            
            // Scan templates
            $service = TranslationManager::getInstance()->translations;
            $foundKeys = $service->scanTemplateDirectory($templatePath, $category);
            
            $this->stdout("Template path: {$templatePath}" . PHP_EOL);
            $this->stdout("Category: '{$category}'" . PHP_EOL);
            $this->stdout("Files scanned: {$service->_scannedFileCount}" . PHP_EOL);
            $this->stdout("Translation keys found: " . count($foundKeys) . PHP_EOL);
            
            if (!empty($foundKeys)) {
                $this->stdout(PHP_EOL . 'Found translation keys:' . PHP_EOL, Console::FG_GREEN);
                foreach (array_keys($foundKeys) as $key) {
                    $this->stdout("- '{$key}'" . PHP_EOL);
                }
            }
            
            // Check what would be marked unused
            // Include both 'site' context and 'runtime' context (from auto-capture)
            $siteTranslations = (new \craft\db\Query())
                ->from('{{%translationmanager_translations}}')
                ->where([
                    'or',
                    ['like', 'context', 'site%', false],
                    ['context' => 'runtime'],
                ])
                ->andWhere(['!=', 'status', 'unused'])
                ->all();
                
            $wouldMarkUnused = [];
            foreach ($siteTranslations as $translation) {
                if (!isset($foundKeys[$translation['translationKey']])) {
                    $wouldMarkUnused[] = $translation['translationKey'];
                }
            }
            
            if (!empty($wouldMarkUnused)) {
                $this->stdout(PHP_EOL . 'Would mark as unused:' . PHP_EOL, Console::FG_YELLOW);
                foreach ($wouldMarkUnused as $key) {
                    $this->stdout("- '{$key}'" . PHP_EOL);
                }
            }
        } catch (\Exception $e) {
            $this->stdout('Error: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        return ExitCode::OK;
    }
    
    /**
     * Clean unused translations by type
     *
     * @since 1.0.0
     */
    public function actionCleanByType(?string $type = null): int
    {
        if (!$type) {
            $this->stdout('Usage: craft translation-manager/maintenance/clean-by-type --type=[all|site|formie]' . PHP_EOL, Console::FG_YELLOW);
            return ExitCode::USAGE;
        }
        
        $query = new \craft\db\Query();
        $query->from('{{%translationmanager_translations}}')
              ->where(['status' => 'unused']);
        
        switch ($type) {
            case 'site':
                // Include both 'site' context and 'runtime' context (from auto-capture)
                $query->andWhere([
                    'or',
                    ['like', 'context', 'site%', false],
                    ['context' => 'runtime'],
                ]);
                $this->stdout('Cleaning unused site translations...' . PHP_EOL, Console::FG_BLUE);
                break;
            case 'formie':
                $query->andWhere(['or',
                    ['like', 'context', 'formie.%', false],
                    ['=', 'context', 'formie'],
                ]);
                $this->stdout('Cleaning unused form translations...' . PHP_EOL, Console::FG_BLUE);
                break;
            case 'all':
                $this->stdout('Cleaning ALL unused translations...' . PHP_EOL, Console::FG_BLUE);
                break;
            default:
                $this->stdout("Invalid type: {$type}. Use: all, site, or formie" . PHP_EOL, Console::FG_RED);
                return ExitCode::USAGE;
        }
        
        $unusedTranslations = $query->all();
        $count = count($unusedTranslations);
        
        if ($count === 0) {
            $this->stdout("No unused {$type} translations found." . PHP_EOL, Console::FG_GREEN);
            return ExitCode::OK;
        }
        
        $this->stdout("Found {$count} unused {$type} translations." . PHP_EOL);
        
        if ($this->interactive) {
            if (!$this->confirm("Delete {$count} unused {$type} translations?")) {
                $this->stdout('Cancelled.' . PHP_EOL, Console::FG_YELLOW);
                return ExitCode::OK;
            }
        }
        
        $deleted = TranslationManager::getInstance()->translations->deleteTranslations(
            array_column($unusedTranslations, 'id')
        );
        
        $this->stdout("Deleted {$deleted} unused {$type} translations." . PHP_EOL, Console::FG_GREEN);
        
        return ExitCode::OK;
    }
}
