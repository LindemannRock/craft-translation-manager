<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for generating PHP translation files that Craft CMS uses for
 * multi-language support
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Generate Controller
 *
 * @since 5.0.0
 */
class GenerateController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        $user = Craft::$app->getUser();

        switch ($action->id) {
            case 'files':
                if (!$user->checkPermission('translationManager:generateAllTranslations')) {
                    throw new ForbiddenHttpException('User does not have permission to generate translation files');
                }
                break;
            case 'formie-files':
                if (!$user->checkPermission('translationManager:generateFormieTranslations')) {
                    throw new ForbiddenHttpException('User does not have permission to generate Formie translation files');
                }
                break;
            case 'site-files':
                if (!$user->checkPermission('translationManager:generateSiteTranslations')) {
                    throw new ForbiddenHttpException('User does not have permission to generate site translation files');
                }
                break;
            case 'category-files':
                if (!$user->checkPermission('translationManager:generateSiteTranslations')) {
                    throw new ForbiddenHttpException('User does not have permission to generate category translation files');
                }
                break;
            default:
                // Index page — any generate permission is sufficient
                $hasGenerateAccess =
                    $user->checkPermission('translationManager:generateTranslations') ||
                    $user->checkPermission('translationManager:generateAllTranslations') ||
                    $user->checkPermission('translationManager:generateFormieTranslations') ||
                    $user->checkPermission('translationManager:generateSiteTranslations');

                if (!$hasGenerateAccess) {
                    throw new ForbiddenHttpException('User does not have permission to generate translation files');
                }
        }

        return parent::beforeAction($action);
    }

    /**
     * Generate index page
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/generate/index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Generate all translation files (Formie + site)
     *
     * @return Response
     */
    public function actionFiles(): Response
    {
        $this->requirePostRequest();

        try {
            $this->logInfo('User requested all-files generation');
            $generationService = TranslationManager::getInstance()->generate;
            $translationsService = TranslationManager::getInstance()->translations;

            // Check what's available to generate first
            $formieCount = count($translationsService->getTranslations(['type' => 'forms', 'status' => 'translated', 'allSites' => true]));
            $siteCount = count($translationsService->getTranslations(['type' => 'site', 'status' => 'translated', 'allSites' => true]));

            $this->logInfo("Generation preparation", [
                'formieCount' => $formieCount,
                'siteCount' => $siteCount,
            ]);

            $formieResult = false;
            $siteResult = false;
            $messages = [];
            $warnings = [];

            // Only generate if there are translations to generate
            if ($formieCount > 0) {
                $formieResult = $generationService->generateFormieTranslations();
                if ($formieResult) {
                    $messages[] = Craft::t('translation-manager', '{name} files ({count} translations)', ['name' => TranslationManager::getFormiePluginName(), 'count' => $formieCount]);
                }
            } else {
                $warnings[] = Craft::t('translation-manager', 'No translated {name} translations found', ['name' => TranslationManager::getFormiePluginName()]);
            }

            if ($siteCount > 0) {
                $siteResult = $generationService->generateSiteTranslations();
                if ($siteResult) {
                    $messages[] = Craft::t('translation-manager', 'Site files ({count} translations)', ['count' => $siteCount]);
                }
            } else {
                $warnings[] = Craft::t('translation-manager', 'No translated site translations found');
            }

            // Build response message
            $parts = [];
            if (!empty($messages)) {
                $parts[] = Craft::t('translation-manager', 'Generated:') . ' ' . implode(', ', $messages);
            }
            if (!empty($warnings)) {
                $parts[] = implode('; ', $warnings);
            }

            $message = !empty($parts) ? implode('. ', $parts) : Craft::t('translation-manager', 'No translation files generated');

            // Log the completion with results
            $this->logInfo("Generation completed", ['message' => $message]);

            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => $message,
                    'debug' => [
                        'formie' => $formieResult,
                        'site' => $siteResult,
                    ],
                ]);
            }

            Craft::$app->getSession()->setNotice($message);
            return $this->redirect('translation-manager');
        } catch (\Exception $e) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Failed to generate translation files: {error}', ['error' => $e->getMessage()]));
            return $this->redirect('translation-manager');
        }
    }

    /**
     * Generate Formie translation files
     *
     * @return Response
     */
    public function actionFormieFiles(): Response
    {
        $this->requirePostRequest();

        try {
            $this->logInfo('User requested Formie generation only');
            $translationsService = TranslationManager::getInstance()->translations;
            $formieCount = count($translationsService->getTranslations(['type' => 'forms', 'status' => 'translated', 'allSites' => true]));

            $this->logInfo("Formie generation preparation", ['formieCount' => $formieCount]);

            $pluginName = TranslationManager::getFormiePluginName();
            if ($formieCount > 0) {
                TranslationManager::getInstance()->generate->generateFormieTranslations();
                $message = Craft::t('translation-manager', '{name} translation files generated successfully ({count} translations)', ['name' => $pluginName, 'count' => $formieCount]);
                $this->logInfo("Formie generation completed", ['message' => $message]);
            } else {
                $message = Craft::t('translation-manager', 'No translated {name} translations found. Add translations first.', ['name' => $pluginName]);
            }

            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            Craft::$app->getSession()->setNotice($message);
            return $this->redirect('translation-manager');
        } catch (\Exception $e) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Failed to generate translation files: {error}', ['error' => $e->getMessage()]));
            return $this->redirect('translation-manager');
        }
    }

    /**
     * Generate site translation files
     *
     * @return Response
     */
    public function actionSiteFiles(): Response
    {
        $this->requirePostRequest();

        try {
            $this->logInfo('User requested site/category generation only');
            $translationsService = TranslationManager::getInstance()->translations;
            $siteCount = count($translationsService->getTranslations(['type' => 'site', 'status' => 'translated', 'allSites' => true]));

            $this->logInfo("Site generation preparation", ['siteCount' => $siteCount]);

            if ($siteCount > 0) {
                TranslationManager::getInstance()->generate->generateSiteTranslations();
                $message = Craft::t('translation-manager', 'Site translation files generated successfully ({count} translations)', ['count' => $siteCount]);
                $this->logInfo("Site generation completed", ['message' => $message]);
            } else {
                $message = Craft::t('translation-manager', 'No translated site translations found. Add translations first.');
            }

            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            Craft::$app->getSession()->setNotice($message);
            return $this->redirect('translation-manager');
        } catch (\Exception $e) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Failed to generate site translation files: {error}', ['error' => $e->getMessage()]));
            return $this->redirect('translation-manager');
        }
    }

    /**
     * Generate a specific category's translation files
     *
     * @return Response
     * @since 5.0.0
     */
    public function actionCategoryFiles(): Response
    {
        $this->requirePostRequest();

        try {
            $request = Craft::$app->getRequest();
            $category = $request->getRequiredBodyParam('category');

            // Validate category is enabled
            $settings = TranslationManager::getInstance()->getSettings();
            $enabledCategories = $settings->getEnabledCategories();

            if (!in_array($category, $enabledCategories, true)) {
                throw new \Exception(Craft::t('translation-manager', "Category '{category}' is not enabled.", ['category' => $category]));
            }

            $this->logInfo('User requested single category generation', ['category' => $category]);
            $translationsService = TranslationManager::getInstance()->translations;
            $count = count($translationsService->getTranslations([
                'type' => 'site',
                'category' => $category,
                'status' => 'translated',
                'allSites' => true,
            ]));

            $this->logInfo("Category generation preparation", ['category' => $category, 'count' => $count]);

            if ($count > 0) {
                TranslationManager::getInstance()->generate->generateCategoryTranslations($category);
                $message = Craft::t('translation-manager', '{name} translation files generated successfully ({count} translations)', ['name' => ucfirst($category), 'count' => $count]);
                $this->logInfo("Category generation completed", ['message' => $message]);
            } else {
                $message = Craft::t('translation-manager', "No translated translations found for category '{category}'. Add translations first.", ['category' => $category]);
            }

            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            Craft::$app->getSession()->setNotice($message);
            return $this->redirect('translation-manager');
        } catch (\Exception $e) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Failed to generate category translation files: {error}', ['error' => $e->getMessage()]));
            return $this->redirect('translation-manager');
        }
    }
}
