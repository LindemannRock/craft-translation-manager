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
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\records\GenerationStatusRecord;
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\services\SourceService;
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
        /** @var SourceService $sourceService */
        $sourceService = TranslationManager::getInstance()->get('sources');

        switch ($action->id) {
            case 'files':
                if (!$user->checkPermission($sourceService->getAllPermission(SourceService::ACTION_GENERATE))) {
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to generate translation files.'));
                }
                break;
            case 'provider-files':
                $provider = (string)Craft::$app->getRequest()->getBodyParam('provider', '');
                /** @var IntegrationService $integrationService */
                $integrationService = TranslationManager::getInstance()->get('integrations');
                $integration = $integrationService->get($provider);
                $providerLabel = $integration !== null
                    ? PluginHelper::getPluginName($integration->getPluginHandle(), ucfirst($integration->getName()))
                    : $provider;
                if ($integration === null || !$sourceService->currentUserCanProvider(SourceService::ACTION_GENERATE, $integration->getName())) {
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to generate {name} translation files.', ['name' => $providerLabel]));
                }
                break;
            case 'site-files':
                if (!$user->checkPermission($sourceService->getAllPermission(SourceService::ACTION_GENERATE))) {
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to generate site translation files.'));
                }
                break;
            case 'category-files':
                $category = (string)Craft::$app->getRequest()->getBodyParam('category', '');
                if (!$sourceService->currentUserCanCategory(SourceService::ACTION_GENERATE, $category)) {
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to generate category translation files.'));
                }
                break;
            default:
                // Index page — the parent permission opens the section; source permissions expose actions.
                $hasGenerateAccess = $user->checkPermission('translationManager:generateTranslations')
                    || $sourceService->currentUserCanAny(SourceService::ACTION_GENERATE);

                if (!$hasGenerateAccess) {
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to generate translation files.'));
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
     * Generate all translation files (enabled form providers + site)
     *
     * @return Response
     */
    public function actionFiles(): Response
    {
        $this->requirePostRequest();

        try {
            $this->logInfo('User requested all-files generation');
            $generationService = TranslationManager::getInstance()->generate;
            /** @var IntegrationService $integrationService */
            $integrationService = TranslationManager::getInstance()->get('integrations');
            $result = $generationService->generateAll();
            TranslationManager::getInstance()->generationStatus->recordGenerationResult(
                $result,
                GenerationStatusRecord::REASON_MANUAL,
                GenerationStatusRecord::TRIGGER_CP,
            );
            $messages = [];
            $warnings = [];

            foreach (($result['results'] ?? []) as $key => $scopeResult) {
                if (!is_array($scopeResult)) {
                    continue;
                }

                $count = (int)($scopeResult['translationCount'] ?? 0);
                $type = (string)($scopeResult['type'] ?? '');

                if ($count > 0) {
                    if ($type === 'site') {
                        $messages[] = Craft::t('translation-manager', 'Site files ({count} translations)', ['count' => $count]);
                    } else {
                        $provider = (string)($scopeResult['provider'] ?? $key);
                        $integration = $integrationService->get($provider);
                        $name = $integration !== null
                            ? PluginHelper::getPluginName($integration->getPluginHandle(), ucfirst($integration->getName()))
                            : (string)($scopeResult['label'] ?? ucfirst($provider));
                        $messages[] = Craft::t('translation-manager', '{name} files ({count} translations)', ['name' => $name, 'count' => $count]);
                    }
                } elseif ($type === 'site') {
                    $warnings[] = Craft::t('translation-manager', 'No translated site translations found');
                } else {
                    $provider = (string)($scopeResult['provider'] ?? $key);
                    $integration = $integrationService->get($provider);
                    $name = $integration !== null
                        ? PluginHelper::getPluginName($integration->getPluginHandle(), ucfirst($integration->getName()))
                        : (string)($scopeResult['label'] ?? ucfirst($provider));
                    $warnings[] = Craft::t('translation-manager', 'No translated {name} translations found', ['name' => $name]);
                }
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
                $response = [
                    'success' => true,
                    'message' => $message,
                ];

                if (Craft::$app->getConfig()->getGeneral()->devMode) {
                    $response['debug'] = [
                        'result' => $result,
                    ];
                }

                return $this->asJson($response);
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
     * Generate one form provider's translation files.
     */
    public function actionProviderFiles(): Response
    {
        $this->requirePostRequest();

        try {
            $provider = Craft::$app->getRequest()->getRequiredBodyParam('provider');
            /** @var IntegrationService $integrationService */
            $integrationService = TranslationManager::getInstance()->get('integrations');
            $integration = $integrationService->get($provider);

            if ($integration === null || $integration->getSourceType() !== 'forms') {
                throw new \Exception(Craft::t('translation-manager', 'Provider not found.'));
            }

            if (!$integrationService->isIntegrationEnabled($integration->getName())) {
                throw new \Exception(Craft::t('translation-manager', 'Provider is not enabled.'));
            }

            $pluginName = PluginHelper::getPluginName(
                $integration->getPluginHandle(),
                ucfirst($integration->getName()),
            );
            $category = $integration->getCategory();
            $result = TranslationManager::getInstance()->generate->generateProviderTranslations($integration->getName());
            $count = (int)($result['translationCount'] ?? 0);

            $this->logInfo('Provider generation preparation', [
                'provider' => $provider,
                'category' => $category,
                'count' => $count,
            ]);

            if ($count > 0) {
                $message = Craft::t('translation-manager', '{name} translation files generated successfully ({count} translations)', [
                    'name' => $pluginName,
                    'count' => $count,
                ]);
            } else {
                $message = Craft::t('translation-manager', 'No translated {name} translations found. Add translations first.', [
                    'name' => $pluginName,
                ]);
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
            $result = TranslationManager::getInstance()->generate->generateSiteTranslations();
            $siteCount = (int)($result['translationCount'] ?? 0);

            $this->logInfo("Site generation preparation", ['siteCount' => $siteCount]);

            if ($siteCount > 0) {
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
            $result = TranslationManager::getInstance()->generate->generateCategoryTranslations($category);
            $count = (int)($result['translationCount'] ?? 0);

            $this->logInfo("Category generation preparation", ['category' => $category, 'count' => $count]);

            if ($count > 0) {
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
