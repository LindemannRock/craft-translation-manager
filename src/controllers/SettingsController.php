<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for managing plugin settings
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\helpers\SettingsPostHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\records\GenerationStatusRecord;
use lindemannrock\translationmanager\services\IntegrationService;
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
            case 'clear-provider':
                $provider = (string)Craft::$app->getRequest()->getBodyParam('provider', '');
                /** @var IntegrationService $integrationService */
                $integrationService = TranslationManager::getInstance()->get('integrations');
                $integration = $integrationService->get($provider);
                $providerLabel = $integration !== null
                    ? PluginHelper::getPluginName($integration->getPluginHandle(), ucfirst($integration->getName()))
                    : $provider;
                if ($integration === null || !$integrationService->currentUserCanProviderAction('clear', $provider)) {
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to clear {name} translations.', ['name' => $providerLabel]));
                }
                break;
            case 'clear-site':
                if (!$user->checkPermission('translationManager:clearSite')) {
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to clear site translations.'));
                }
                break;
            case 'clear-all':
                if (!$user->checkPermission('translationManager:clearAll')) {
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to clear all translations.'));
                }
                break;
            default:
                // All other actions require editSettings permission
                if (!$user->checkPermission('translationManager:editSettings')) {
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to edit settings.'));
                }
        }

        return parent::beforeAction($action);
    }

    /**
     * Settings index page (redirects to general)
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->redirect('translation-manager/settings/general');
    }

    /**
     * General settings page
     *
     * @return Response
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
     * AI translation settings page
     *
     * @return Response
     */
    public function actionAi(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/settings/ai', [
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
     * Run a live AI provider test from settings UI.
     */
    public function actionTestAi(): Response
    {
        $this->requirePostRequest();

        $provider = (string) $this->request->getBodyParam('provider', '');
        $targetLanguage = trim((string) $this->request->getBodyParam('targetLanguage', 'de'));
        $text = trim((string) $this->request->getBodyParam('text', 'Welcome {name}, your order %1$s is ready.<br/>Thank you!'));

        if (!in_array($provider, ['openai', 'gemini', 'anthropic', 'mock'], true)) {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Invalid AI provider selected.'));
            return $this->redirectToPostedUrl();
        }

        if ($targetLanguage === '') {
            $targetLanguage = 'de';
        }

        if ($text === '') {
            $text = 'Welcome {name}, your order %1$s is ready.<br/>Thank you!';
        }

        try {
            $settings = TranslationManager::getInstance()->getSettings();
            $test = TranslationManager::getInstance()->ai->testProvider($provider);
            $translated = TranslationManager::getInstance()->ai->translateText(
                $text,
                $settings->sourceLanguage,
                $targetLanguage,
                $provider,
            );

            Craft::$app->getSession()->setNotice(Craft::t(
                'translation-manager',
                'AI test successful ({provider}, {model}). Translation: {translation}',
                [
                    'provider' => $test['provider'],
                    'model' => $test['model'],
                    'translation' => $translated,
                ]
            ));
        } catch (\Throwable $e) {
            Craft::$app->getSession()->setError(Craft::t(
                'translation-manager',
                'AI test failed: {error}',
                ['error' => $e->getMessage()]
            ));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Save settings
     *
     * @return Response|null
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
        $oldGenerationPath = $settings->getGenerationPath();

        // Get only the posted settings (fields from the current page)
        $settingsData = Craft::$app->getRequest()->getBodyParam('settings', []);

        // Validate only fields belonging to the current section.
        $section = $this->_validSettingsSection(
            $this->request->getBodyParam('section', 'general'),
        );
        $result = SettingsPostHelper::apply(
            model: $settings,
            postedValues: is_array($settingsData) ? $settingsData : [],
            allowedAttributes: $this->_validationAttributesForSection($section),
            shouldSkipAttribute: fn(string $attribute): bool => $settings->isOverriddenByConfig($attribute),
            adapters: [
                'skipPatterns' => $this->textareaListAdapter(...),
                'excludeFormHandlePatterns' => $this->textareaListAdapter(...),
            ],
        );

        $attributesToValidate = $result->attributesToValidate;

        if ($result->hasErrors || !$settings->validate($attributesToValidate)) {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Could not save settings.'));

            $template = "translation-manager/settings/{$section}";

            return $this->renderTemplate($template, [
                'settings' => $settings,
            ]);
        }

        // Save settings to database (same scoped attributes)
        if ($settings->saveToDatabase($attributesToValidate)) {
            $shouldRegenerateGeneratedFiles = in_array('generationPath', $attributesToValidate, true)
                && $oldGenerationPath !== $settings->getGenerationPath();

            // Detect backup schedule changes and update queue jobs
            if ($oldBackupEnabled !== $settings->backupEnabled ||
                $oldBackupSchedule !== $settings->backupSchedule
            ) {
                // Reset cached settings before queueing so job init/description
                // reads the persisted schedule and date-format settings.
                $plugin->setSettings([]);
                $settings = Settings::loadFromDatabase();
                $plugin->handleBackupScheduleChange($settings, $oldBackupEnabled, $oldBackupSchedule);
            } else {
                // Reset cached settings so next request loads fresh from DB
                $plugin->setSettings([]);
            }

            if ($shouldRegenerateGeneratedFiles) {
                $plugin->setSettings([]);
                $settings = Settings::loadFromDatabase();
                $this->regenerateGeneratedFilesAfterPathChange($oldGenerationPath, $settings->getGenerationPath());
            }

            Craft::$app->getSession()->setNotice(Craft::t('translation-manager', 'Settings saved.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Could not save settings.'));
            return null;
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Regenerate generated PHP files after the generation path changes.
     */
    private function regenerateGeneratedFilesAfterPathChange(string $oldPath, string $newPath): void
    {
        try {
            $result = TranslationManager::getInstance()->generate->generateAll();
            TranslationManager::getInstance()->generationStatus->recordGenerationResult(
                $result,
                GenerationStatusRecord::REASON_SETTINGS_CHANGE,
                GenerationStatusRecord::TRIGGER_CP,
            );

            $this->logInfo('Regenerated translation files after generation path change', [
                'oldPath' => $oldPath,
                'newPath' => $newPath,
                'translationCount' => $result['translationCount'] ?? 0,
                'writtenFileCount' => $result['writtenFileCount'] ?? 0,
                'deletedFileCount' => $result['deletedFileCount'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            $this->logError('Failed to regenerate translation files after generation path change', [
                'oldPath' => $oldPath,
                'newPath' => $newPath,
                'error' => $e->getMessage(),
            ]);

            Craft::$app->getSession()->setError(Craft::t(
                'translation-manager',
                'Failed to generate translation files: {error}',
                ['error' => $e->getMessage()]
            ));
        }
    }
    
    /**
     * Clear translations for a specific form provider.
     *
     * @return Response
     */
    public function actionClearProvider(): Response
    {
        $this->requirePostRequest();

        return $this->clearProvider((string)Craft::$app->getRequest()->getRequiredBodyParam('provider'));
    }

    private function clearProvider(string $provider): Response
    {
        $this->requirePostRequest();

        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');
        $integration = $integrationService->get($provider);

        if ($integration === null || $integration->getSourceType() !== 'forms') {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Invalid type specified'));
            return $this->redirectToPostedUrl();
        }

        $pluginName = PluginHelper::getPluginName($integration->getPluginHandle(), ucfirst($integration->getName()));

        $this->logInfo('User requested clear provider translations', ['provider' => $provider]);

        // Create backup if enabled
        $settings = TranslationManager::getInstance()->getSettings();
        if ($settings->backupEnabled) {
            try {
                $backupService = TranslationManager::getInstance()->backup;
                $backupPath = $backupService->createBackup("before_clear_{$provider}");
                if ($backupPath) {
                    $this->logInfo('Created backup before clearing provider translations', [
                        'provider' => $provider,
                        'backupPath' => $backupPath,
                    ]);
                }
            } catch (\Exception $e) {
                $this->logError('Failed to create backup before clearing provider translations', [
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ]);
                // Continue with the operation even if backup fails
            }
        }
        $count = TranslationManager::getInstance()->translations->clearProviderTranslations($provider);
        
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
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to edit settings.'));
        }
        
        try {
            $settings = TranslationManager::getInstance()->getSettings();
            $translationsService = TranslationManager::getInstance()->translations;
            
            // Debug: Check current settings
            $skipPatterns = $settings->skipPatterns ?? [];
            $skipPatternsCount = count($skipPatterns);
            
            // Debug: Check site translations
            // allSites: true so the count covers every language, matching what
            // applySkipPatternsToExisting() will actually scan + delete.
            $siteTranslations = $translationsService->getTranslations(['type' => 'site', 'allSites' => true]);
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

    /**
     * Validate and sanitize the settings section parameter
     *
     * @param string $section The section from POST data
     * @return string A validated section name
     */
    private function _validSettingsSection(string $section): string
    {
        $allowed = ['general', 'generation', 'backup', 'sources', 'interface', 'locale-mapping', 'integrations', 'ai', 'capture'];

        return in_array($section, $allowed, true) ? $section : 'general';
    }

    /**
     * Get validation attributes for a settings section.
     */
    private function _validationAttributesForSection(string $section): array
    {
        return match ($section) {
            'general' => [
                'pluginName',
                'requireApproval',
                'logLevel',
            ],
            'generation' => [
                'autoGenerate',
                'generationPath',
            ],
            'backup' => [
                'backupEnabled',
                'backupOnImport',
                'backupSchedule',
                'backupRetentionDays',
                'backupVolumeUid',
                'backupPath',
            ],
            'sources' => [
                'enableSiteTranslations',
                'translationCategories',
                'sourceLanguage',
                'skipPatterns',
            ],
            'interface' => [
                'itemsPerPage',
                'autoSaveEnabled',
                'timeFormat',
                'monthFormat',
                'dateOrder',
                'dateSeparator',
                'showSeconds',
                'exportsCsv',
                'exportsJson',
                'exportsExcel',
            ],
            'locale-mapping' => [
                'localeMapping',
            ],
            'integrations' => [
                'enableFormieIntegration',
                'enableFreeformIntegration',
                'excludeFormHandlePatterns',
            ],
            'ai' => [
                'enableAiTranslations',
                'aiProvider',
                'openAiApiKey',
                'openAiModel',
                'geminiApiKey',
                'geminiModel',
                'anthropicApiKey',
                'anthropicModel',
            ],
            'capture' => [
                'captureMissingTranslations',
                'captureMissingOnlyDevMode',
            ],
            default => [],
        };
    }

    /**
     * Normalizes textarea list settings before typed POST assignment.
     *
     * @return array<int|string, mixed>
     */
    private function textareaListAdapter(mixed $value): array
    {
        if (is_string($value)) {
            if (trim($value) === '') {
                return [];
            }

            return array_values(array_filter(
                array_map('trim', preg_split('/\R/', $value) ?: []),
                static fn(string $line): bool => $line !== '',
            ));
        }

        return is_array($value) ? $value : [];
    }
}
