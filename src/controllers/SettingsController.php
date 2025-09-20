<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for managing plugin settings
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\Response;
use yii\web\ForbiddenHttpException;

/**
 * Settings Controller
 */
class SettingsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Require permission to edit settings
        if (!Craft::$app->getUser()->checkPermission('translationManager:editSettings')) {
            throw new ForbiddenHttpException('User does not have permission to edit settings');
        }

        return parent::beforeAction($action);
    }

    /**
     * Settings index page (redirects to general)
     */
    public function actionIndex(): Response
    {
        return $this->redirect('translation-manager/settings/general');
    }

    /**
     * General settings page
     */
    public function actionGeneral(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/settings/general', [
            'settings' => $settings,
        ]);
    }

    /**
     * Generation settings page
     */
    public function actionGeneration(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/settings/generation', [
            'settings' => $settings,
        ]);
    }
    
    /**
     * Import/Export settings page
     */
    public function actionImportExport(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();
        
        // Get import history
        $history = \lindemannrock\translationmanager\records\ImportHistoryRecord::find()
            ->with('user')
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(10) // Show last 10 imports like we do with backups
            ->all();
        
        // Format the data for display
        $formattedHistory = [];
        foreach ($history as $record) {
            $formattedHistory[] = [
                'id' => $record->id,
                'filename' => $record->filename,
                'filesize' => Craft::$app->getFormatter()->asShortSize($record->filesize),
                'imported' => $record->imported,
                'updated' => $record->updated,
                'skipped' => $record->skipped,
                'errors' => $record->errors ? json_decode($record->errors, true) : [],
                'hasErrors' => !empty($record->errors),
                'backupPath' => $record->backupPath,
                'user' => $record->user ? $record->user->username : 'Unknown',
                'dateCreated' => $record->dateCreated,
                'formattedDate' => Craft::$app->getFormatter()->asDatetime($record->dateCreated, 'short'),
            ];
        }
        
        // Get total count for "View All" link
        $totalImports = \lindemannrock\translationmanager\records\ImportHistoryRecord::find()->count();

        return $this->renderTemplate('translation-manager/settings/import-export', [
            'settings' => $settings,
            'importHistory' => $formattedHistory,
            'totalImports' => $totalImports,
            'allSites' => TranslationManager::getInstance()->getAllowedSites(),
        ]);
    }

    /**
     * Maintenance settings page
     */
    public function actionMaintenance(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/settings/maintenance', [
            'settings' => $settings,
        ]);
    }
    
    /**
     * Backup settings page
     */
    public function actionBackup(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();
        
        return $this->renderTemplate('translation-manager/settings/backup', [
            'settings' => $settings,
        ]);
    }

    /**
     * Save settings
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $settings = TranslationManager::getInstance()->getSettings();

        // Populate settings from POST data
        $settings->pluginName = $request->getBodyParam('pluginName', $settings->pluginName);
        $settings->translationCategory = $request->getBodyParam('translationCategory', $settings->translationCategory);
        $settings->enableFormieIntegration = (bool) $request->getBodyParam('enableFormieIntegration', $settings->enableFormieIntegration);
        $settings->enableSiteTranslations = (bool) $request->getBodyParam('enableSiteTranslations', $settings->enableSiteTranslations);
        $settings->autoExport = (bool) $request->getBodyParam('autoExport', $settings->autoExport);
        $settings->exportPath = $request->getBodyParam('exportPath', $settings->exportPath);
        $settings->itemsPerPage = (int) $request->getBodyParam('itemsPerPage', $settings->itemsPerPage);
        $settings->autoSaveEnabled = (bool) $request->getBodyParam('autoSaveEnabled', $settings->autoSaveEnabled);
        $settings->autoSaveDelay = (int) $request->getBodyParam('autoSaveDelay', $settings->autoSaveDelay);
        $settings->showContext = (bool) $request->getBodyParam('showContext', $settings->showContext);
        $settings->enableSuggestions = (bool) $request->getBodyParam('enableSuggestions', $settings->enableSuggestions);
        
        // Backup settings
        $settings->backupEnabled = (bool) $request->getBodyParam('backupEnabled', $settings->backupEnabled);
        $settings->backupRetentionDays = (int) $request->getBodyParam('backupRetentionDays', $settings->backupRetentionDays);
        $settings->backupOnImport = (bool) $request->getBodyParam('backupOnImport', $settings->backupOnImport);
        $settings->backupSchedule = $request->getBodyParam('backupSchedule', $settings->backupSchedule);
        $settings->backupPath = $request->getBodyParam('backupPath', $settings->backupPath);
        $settings->backupVolumeUid = $request->getBodyParam('backupVolumeUid') ?: null;
        $settings->logLevel = $request->getBodyParam('logLevel', $settings->logLevel);
        
        // Handle skip patterns
        $skipPatterns = $request->getBodyParam('skipPatterns', '');
        if (is_string($skipPatterns)) {
            // If the textarea is empty, set to empty array
            if (trim($skipPatterns) === '') {
                $settings->skipPatterns = [];
            } else {
                $patterns = array_filter(array_map('trim', explode("\n", $skipPatterns)));
                $settings->skipPatterns = $patterns;
            }
        } else {
            // Fallback for non-string values
            $settings->skipPatterns = [];
        }

        // Validate settings
        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Couldn\'t save settings.'));
            
            return $this->renderTemplate('translation-manager/settings/general', [
                'settings' => $settings,
            ]);
        }

        // Save settings to database (bypasses project config)
        if (!$settings->saveToDatabase()) {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Couldn\'t save settings.'));
            
            return $this->renderTemplate('translation-manager/settings/general', [
                'settings' => $settings,
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('translation-manager', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
    
    /**
     * Clear Formie translations
     */
    public function actionClearFormie(): Response
    {
        $this->requirePostRequest();

        Craft::info("User requested clear Formie translations", 'translation-manager');

        // Create backup if enabled
        $settings = TranslationManager::getInstance()->getSettings();
        if ($settings->backupEnabled) {
            try {
                $backupService = TranslationManager::getInstance()->backup;
                $pluginName = TranslationManager::getFormiePluginName();
                $backupPath = $backupService->createBackup('before_clear');
                if ($backupPath) {
                    Craft::info("Created backup before clearing Formie translations: $backupPath", 'translation-manager');
                }
            } catch (\Exception $e) {
                Craft::error("Failed to create backup before clearing Formie translations: " . $e->getMessage(), 'translation-manager');
                // Continue with the operation even if backup fails
            }
        }
        $count = TranslationManager::getInstance()->translations->clearFormieTranslations();
        
        $pluginName = TranslationManager::getFormiePluginName();
        $message = $count > 0 
            ? Craft::t('translation-manager', '{count} {plugin} translations and corresponding files have been deleted.', ['count' => $count, 'plugin' => $pluginName])
            : Craft::t('translation-manager', 'No {plugin} translations found to delete.', ['plugin' => $pluginName]);
        
        Craft::$app->getSession()->setNotice($message);
        
        return $this->redirectToPostedUrl();
    }
    
    /**
     * Clear site translations
     */
    public function actionClearSite(): Response
    {
        $this->requirePostRequest();

        Craft::info("User requested clear site translations", 'translation-manager');

        // Create backup if enabled
        $settings = TranslationManager::getInstance()->getSettings();
        if ($settings->backupEnabled) {
            try {
                $backupService = TranslationManager::getInstance()->backup;
                $backupPath = $backupService->createBackup('before_clear');
                if ($backupPath) {
                    Craft::info("Created backup before clearing site translations: $backupPath", 'translation-manager');
                }
            } catch (\Exception $e) {
                Craft::error("Failed to create backup before clearing site translations: " . $e->getMessage(), 'translation-manager');
                // Continue with the operation even if backup fails
            }
        }
        $count = TranslationManager::getInstance()->translations->clearSiteTranslations();
        
        $message = $count > 0 
            ? Craft::t('translation-manager', '{count} site translations and corresponding files have been deleted.', ['count' => $count])
            : Craft::t('translation-manager', 'No site translations found to delete.');
        
        Craft::$app->getSession()->setNotice($message);
        
        return $this->redirectToPostedUrl();
    }
    
    /**
     * Clear all translations
     */
    public function actionClearAll(): Response
    {
        $this->requirePostRequest();

        Craft::info("User requested clear all translations", 'translation-manager');

        // Create backup if enabled
        $settings = TranslationManager::getInstance()->getSettings();
        if ($settings->backupEnabled) {
            try {
                $backupService = TranslationManager::getInstance()->backup;
                $backupPath = $backupService->createBackup('before_clear');
                if ($backupPath) {
                    Craft::info("Created backup before clearing all translations: $backupPath", 'translation-manager');
                }
            } catch (\Exception $e) {
                Craft::error("Failed to create backup before clearing all translations: " . $e->getMessage(), 'translation-manager');
                // Continue with the operation even if backup fails
            }
        }
        $count = TranslationManager::getInstance()->translations->clearAllTranslations();
        
        $message = $count > 0 
            ? Craft::t('translation-manager', 'All {count} translations and corresponding files have been deleted.', ['count' => $count])
            : Craft::t('translation-manager', 'No translations found to delete.');
        
        Craft::$app->getSession()->setNotice($message);
        
        return $this->redirectToPostedUrl();
    }
    
    /**
     * Apply skip patterns to existing translations
     */
    public function actionApplySkipPatterns(): Response
    {
        $this->requirePostRequest();
        
        // Check permission
        if (!Craft::$app->getUser()->checkPermission('translationManager:editSettings')) {
            throw new ForbiddenHttpException('User does not have permission to edit settings');
        }
        
        try {
            $settings = TranslationManager::getInstance()->getSettings();
            $translationsService = TranslationManager::getInstance()->translations;
            
            // Debug: Check current settings
            $skipPatterns = $settings->skipPatterns ?? [];
            $skipPatternsCount = count($skipPatterns);
            
            // Debug: Check site translations
            $siteTranslations = $translationsService->getTranslations(['type' => 'site']);
            $siteTranslationsCount = count($siteTranslations);
            
            if ($skipPatternsCount === 0) {
                $message = Craft::t('translation-manager', 'No skip patterns are currently configured. Add skip patterns in the settings above and save before applying.');
                Craft::$app->getSession()->setError($message);
                return $this->redirectToPostedUrl();
            }
            
            if ($siteTranslationsCount === 0) {
                $message = Craft::t('translation-manager', 'No site translations found. Current skip patterns: {patterns}', [
                    'patterns' => implode(', ', $skipPatterns)
                ]);
                Craft::$app->getSession()->setNotice($message);
                return $this->redirectToPostedUrl();
            }
            
            $count = $translationsService->applySkipPatternsToExisting();
            
            $message = $count > 0 
                ? Craft::t('translation-manager', '{count} existing translations matching skip patterns have been removed. Patterns checked: {patterns}', [
                    'count' => $count,
                    'patterns' => implode(', ', $skipPatterns)
                ])
                : Craft::t('translation-manager', 'No existing translations match the current skip patterns. Checked {siteCount} site translations against {patternCount} patterns: {patterns}', [
                    'siteCount' => $siteTranslationsCount,
                    'patternCount' => $skipPatternsCount,
                    'patterns' => implode(', ', $skipPatterns)
                ]);
            
            Craft::$app->getSession()->setNotice($message);
            
        } catch (\Exception $e) {
            $errorMessage = Craft::t('translation-manager', 'Error applying skip patterns: {error}', ['error' => $e->getMessage()]);
            Craft::$app->getSession()->setError($errorMessage);
        }
        
        return $this->redirectToPostedUrl();
    }
}