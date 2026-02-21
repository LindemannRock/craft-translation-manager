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
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Settings Controller
 *
 * @since 1.0.0
 */
class SettingsController extends Controller
{
    use LoggingTrait;
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Check granular permissions based on action
        $user = Craft::$app->getUser();

        switch ($action->id) {
            case 'clear-formie':
                if (!$user->checkPermission('translationManager:clearFormie')) {
                    throw new ForbiddenHttpException('User does not have permission to clear Formie translations');
                }
                break;
            case 'clear-site':
                if (!$user->checkPermission('translationManager:clearSite')) {
                    throw new ForbiddenHttpException('User does not have permission to clear site translations');
                }
                break;
            case 'clear-all':
                if (!$user->checkPermission('translationManager:clearAll')) {
                    throw new ForbiddenHttpException('User does not have permission to clear all translations');
                }
                break;
            default:
                // All other actions require editSettings permission
                if (!$user->checkPermission('translationManager:editSettings')) {
                    throw new ForbiddenHttpException('User does not have permission to edit settings');
                }
        }

        return parent::beforeAction($action);
    }

    /**
     * Settings index page (redirects to general)
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionIndex(): Response
    {
        return $this->redirect('translation-manager/settings/general');
    }

    /**
     * General settings page
     *
     * @return Response
     * @since 1.0.0
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
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionGeneration(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/settings/generation', [
            'settings' => $settings,
        ]);
    }

    /**
     * Backup settings page
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionBackup(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/settings/backup', [
            'settings' => $settings,
        ]);
    }

    /**
     * Translation sources settings page
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionSources(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/settings/sources', [
            'settings' => $settings,
        ]);
    }

    /**
     * Interface settings page
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionInterface(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/settings/interface', [
            'settings' => $settings,
        ]);
    }

    /**
     * Locale mapping settings page
     *
     * @return Response
     * @since 5.17.0
     */
    public function actionLocaleMapping(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/settings/locale-mapping', [
            'settings' => $settings,
        ]);
    }

    /**
     * Integrations settings page
     *
     * @return Response
     * @since 5.19.0
     */
    public function actionIntegrations(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/settings/integrations', [
            'settings' => $settings,
        ]);
    }

    /**
     * Auto-capture settings page
     *
     * @return Response
     * @since 5.19.0
     */
    public function actionCapture(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/settings/capture', [
            'settings' => $settings,
        ]);
    }

    /**
     * Save settings
     *
     * @return Response|null
     * @since 1.0.0
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $plugin = TranslationManager::getInstance();

        // Load current settings from database
        $settings = Settings::loadFromDatabase();

        // Capture old backup settings before applying new values (for schedule change detection)
        $oldBackupEnabled = $settings->backupEnabled;
        $oldBackupSchedule = $settings->backupSchedule;

        // Get only the posted settings (fields from the current page)
        $settingsData = Craft::$app->getRequest()->getBodyParam('settings', []);

        // Only update fields that were posted and are not overridden by config
        foreach ($settingsData as $key => $value) {
            if (!$settings->isOverriddenByConfig($key) && property_exists($settings, $key)) {
                // Check for setter method first (handles array conversions, etc.)
                $setterMethod = 'set' . ucfirst($key);
                if (method_exists($settings, $setterMethod)) {
                    $settings->$setterMethod($value);
                } else {
                    $settings->$key = $value;
                }
            }
        }

        // Validate
        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Could not save settings.'));

            // Get the section to re-render the correct template with errors
            $section = $this->request->getBodyParam('section', 'general');
            $template = "translation-manager/settings/{$section}";

            return $this->renderTemplate($template, [
                'settings' => $settings,
            ]);
        }

        // Save settings to database
        if ($settings->saveToDatabase()) {
            // Detect backup schedule changes and update queue jobs
            if ($oldBackupEnabled !== $settings->backupEnabled ||
                $oldBackupSchedule !== $settings->backupSchedule
            ) {
                $plugin->handleBackupScheduleChange($settings);
            }

            // Reset cached settings so next request loads fresh from DB
            $plugin->setSettings([]);

            Craft::$app->getSession()->setNotice(Craft::t('translation-manager', 'Settings saved.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Could not save settings'));
            return null;
        }

        return $this->redirectToPostedUrl();
    }
    
    /**
     * Clear Formie translations
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionClearFormie(): Response
    {
        $this->requirePostRequest();

        $this->logInfo("User requested clear Formie translations");

        // Create backup if enabled
        $settings = TranslationManager::getInstance()->getSettings();
        if ($settings->backupEnabled) {
            try {
                $backupService = TranslationManager::getInstance()->backup;
                $pluginName = TranslationManager::getFormiePluginName();
                $backupPath = $backupService->createBackup('before_clear');
                if ($backupPath) {
                    $this->logInfo("Created backup before clearing Formie translations", ['backupPath' => $backupPath]);
                }
            } catch (\Exception $e) {
                $this->logError("Failed to create backup before clearing Formie translations", ['error' => $e->getMessage()]);
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
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionClearSite(): Response
    {
        $this->requirePostRequest();

        $this->logInfo("User requested clear site translations");

        // Create backup if enabled
        $settings = TranslationManager::getInstance()->getSettings();
        if ($settings->backupEnabled) {
            try {
                $backupService = TranslationManager::getInstance()->backup;
                $backupPath = $backupService->createBackup('before_clear');
                if ($backupPath) {
                    $this->logInfo("Created backup before clearing site translations", ['backupPath' => $backupPath]);
                }
            } catch (\Exception $e) {
                $this->logError("Failed to create backup before clearing site translations", ['error' => $e->getMessage()]);
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
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionClearAll(): Response
    {
        $this->requirePostRequest();

        $this->logInfo("User requested clear all translations");

        // Create backup if enabled
        $settings = TranslationManager::getInstance()->getSettings();
        if ($settings->backupEnabled) {
            try {
                $backupService = TranslationManager::getInstance()->backup;
                $backupPath = $backupService->createBackup('before_clear');
                if ($backupPath) {
                    $this->logInfo("Created backup before clearing all translations", ['backupPath' => $backupPath]);
                }
            } catch (\Exception $e) {
                $this->logError("Failed to create backup before clearing all translations", ['error' => $e->getMessage()]);
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
     * Clear translations for a specific category
     *
     * @return Response
     * @since 5.0.0
     */
    public function actionClearCategory(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $category = $request->getRequiredBodyParam('category');

        $this->logInfo("User requested clear category translations", ['category' => $category]);

        // Validate category is enabled
        $settings = TranslationManager::getInstance()->getSettings();
        $enabledCategories = $settings->getEnabledCategories();

        if (!in_array($category, $enabledCategories, true)) {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', "Category '{category}' is not enabled.", ['category' => $category]));
            return $this->redirectToPostedUrl();
        }

        // Create backup if enabled
        if ($settings->backupEnabled) {
            try {
                $backupService = TranslationManager::getInstance()->backup;
                $backupPath = $backupService->createBackup("before_clear_{$category}");
                if ($backupPath) {
                    $this->logInfo("Created backup before clearing category translations", ['category' => $category, 'backupPath' => $backupPath]);
                }
            } catch (\Exception $e) {
                $this->logError("Failed to create backup before clearing category translations", ['error' => $e->getMessage()]);
                // Continue with the operation even if backup fails
            }
        }

        $count = TranslationManager::getInstance()->translations->clearCategoryTranslations($category);

        $message = $count > 0
            ? Craft::t('translation-manager', '{count} {category} translations and corresponding files have been deleted.', ['count' => $count, 'category' => $category])
            : Craft::t('translation-manager', 'No {category} translations found to delete.', ['category' => $category]);

        Craft::$app->getSession()->setNotice($message);

        return $this->redirectToPostedUrl();
    }

    /**
     * Apply skip patterns to existing translations
     *
     * @return Response
     * @since 5.14.0
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
                    'patterns' => implode(', ', $skipPatterns),
                ]);
                Craft::$app->getSession()->setNotice($message);
                return $this->redirectToPostedUrl();
            }
            
            $count = $translationsService->applySkipPatternsToExisting();
            
            $message = $count > 0
                ? Craft::t('translation-manager', '{count} existing translations matching skip patterns have been removed. Patterns checked: {patterns}', [
                    'count' => $count,
                    'patterns' => implode(', ', $skipPatterns),
                ])
                : Craft::t('translation-manager', 'No existing translations match the current skip patterns. Checked {siteCount} site translations against {patternCount} patterns: {patterns}', [
                    'siteCount' => $siteTranslationsCount,
                    'patternCount' => $skipPatternsCount,
                    'patterns' => implode(', ', $skipPatterns),
                ]);
            
            Craft::$app->getSession()->setNotice($message);
        } catch (\Exception $e) {
            $errorMessage = Craft::t('translation-manager', 'Error applying skip patterns: {error}', ['error' => $e->getMessage()]);
            Craft::$app->getSession()->setError($errorMessage);
        }
        
        return $this->redirectToPostedUrl();
    }
}
