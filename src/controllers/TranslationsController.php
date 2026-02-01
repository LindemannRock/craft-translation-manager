<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for managing translations in the Control Panel
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Translations Controller
 *
 * @since 1.0.0
 */
class TranslationsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        $user = Craft::$app->getUser();

        // For index action, redirect to first accessible section if no viewTranslations permission
        if ($action->id === 'index' && !$user->checkPermission('translationManager:viewTranslations')) {
            // Check other permissions and redirect accordingly
            if ($user->checkPermission('translationManager:generateTranslations')) {
                Craft::$app->getResponse()->redirect('translation-manager/generate')->send();
                return false;
            }
            if ($user->checkPermission('translationManager:manageImportExport') ||
                $user->checkPermission('translationManager:importTranslations') ||
                $user->checkPermission('translationManager:exportTranslations')) {
                Craft::$app->getResponse()->redirect('translation-manager/import-export')->send();
                return false;
            }
            if ($user->checkPermission('translationManager:maintenance') ||
                $user->checkPermission('translationManager:clearTranslations')) {
                Craft::$app->getResponse()->redirect('translation-manager/maintenance')->send();
                return false;
            }
            if ($user->checkPermission('translationManager:manageBackups')) {
                Craft::$app->getResponse()->redirect('translation-manager/backups')->send();
                return false;
            }
            if ($user->checkPermission('translationManager:viewSystemLogs')) {
                Craft::$app->getResponse()->redirect('translation-manager/logs')->send();
                return false;
            }
            if ($user->checkPermission('translationManager:editSettings')) {
                Craft::$app->getResponse()->redirect('translation-manager/settings')->send();
                return false;
            }

            // No access at all
            throw new ForbiddenHttpException('User does not have permission to access Translation Manager');
        }

        // For other actions, require viewTranslations permission
        if ($action->id !== 'index' && !$user->checkPermission('translationManager:viewTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to view translations');
        }

        return parent::beforeAction($action);
    }

    /**
     * Translation index page
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionIndex(): Response
    {
        $request = Craft::$app->getRequest();
        $settings = TranslationManager::getInstance()->getSettings();
        
        // Get query parameters
        $page = (int) $request->getParam('page', 1);
        $status = $request->getParam('status', 'all');
        $search = $request->getParam('search', '');
        $sort = $request->getParam('sort', 'translationKey');

        // Get language selection (or fall back to siteId for backwards compatibility)
        $language = $request->getParam('language');
        $siteId = $request->getParam('siteId'); // Legacy support

        if (!$language && $siteId) {
            // Convert legacy siteId to language
            $site = Craft::$app->getSites()->getSiteById($siteId);
            $language = $site?->language;
        }

        if (!$language) {
            // Default to current site's language
            $language = Craft::$app->getSites()->getCurrentSite()->language;
        }

        // Get unique languages for the selector
        $uniqueLanguages = TranslationManager::getInstance()->getUniqueLanguages();
        $dir = $request->getParam('dir', 'asc');
        $type = $request->getParam('type', 'all');
        $category = $request->getParam('category', 'all');

        $limit = $settings->itemsPerPage;
        $offset = ($page - 1) * $limit;

        // Get translations with filters
        $criteria = [
            'language' => $language,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'type' => $type,
            'category' => $category,
            'includeUsageCheck' => true,
        ];

        $allTranslations = TranslationManager::getInstance()->translations->getTranslations($criteria);

        // Calculate pagination
        $totalCount = count($allTranslations);
        $totalPages = ceil($totalCount / $limit);

        // Slice for current page
        $translations = array_slice($allTranslations, $offset, $limit);

        // Get statistics
        $stats = TranslationManager::getInstance()->translations->getStatistics();

        // Get all available categories for the filter dropdown
        $allCategories = $settings->getAllCategories();

        return $this->renderTemplate('translation-manager/translations/index', [
            'translations' => $translations,
            'stats' => $stats,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'type' => $type,
            'category' => $category,
            'allCategories' => $allCategories,
            'settings' => $settings,
            // Language-based variables
            'currentLanguage' => $language,
            'uniqueLanguages' => $uniqueLanguages,
            // Legacy support (keeping for backwards compatibility)
            'allSites' => TranslationManager::getInstance()->getAllowedSites(),
        ]);
    }

    /**
     * Save a single translation
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        // Check permission
        if (!Craft::$app->getUser()->checkPermission('translationManager:editTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to edit translations');
        }

        $request = Craft::$app->getRequest();
        $id = $request->getRequiredBodyParam('id');
        $translationText = $request->getBodyParam('translation', '');

        $translation = TranslationRecord::findOne($id);
        
        if (!$translation) {
            return $this->asJson([
                'success' => false,
                'error' => 'Translation not found',
            ]);
        }

        $translation->translation = $translationText;
        
        if (TranslationManager::getInstance()->translations->saveTranslation($translation)) {
            // Export if auto-export is enabled
            if (TranslationManager::getInstance()->getSettings()->autoExport) {
                $isFormie = str_starts_with($translation->context, 'formie.');
                if ($isFormie) {
                    TranslationManager::getInstance()->export->exportFormieTranslations();
                } else {
                    TranslationManager::getInstance()->export->exportSiteTranslations();
                }
            }

            return $this->asJson([
                'success' => true,
                'message' => 'Translation saved',
                'status' => $translation->status,
            ]);
        }

        return $this->asJson([
            'success' => false,
            'error' => 'Failed to save translation',
        ]);
    }

    /**
     * Save all translations
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionSaveAll(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        // Check permission
        if (!Craft::$app->getUser()->checkPermission('translationManager:editTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to edit translations');
        }

        $request = Craft::$app->getRequest();
        $translations = $request->getRequiredBodyParam('translations');
        
        $saved = 0;
        $hasFormie = false;
        $hasSite = false;

        foreach ($translations as $data) {
            // Validate ID is numeric
            if (!isset($data['id']) || !is_numeric($data['id'])) {
                continue;
            }

            $translation = TranslationRecord::findOne($data['id']);
            if ($translation) {
                $translationText = $data['translation'] ?? '';

                // Validate input length to prevent DoS
                if (strlen($translationText) > 5000) {
                    continue;
                }

                $translation->translation = $translationText;
                if (TranslationManager::getInstance()->translations->saveTranslation($translation)) {
                    $saved++;
                    
                    // Track what types we're saving
                    if (str_starts_with($translation->context, 'formie.')) {
                        $hasFormie = true;
                    } else {
                        $hasSite = true;
                    }
                }
            }
        }

        // Export if auto-export is enabled
        if (TranslationManager::getInstance()->getSettings()->autoExport && $saved > 0) {
            if ($hasFormie) {
                TranslationManager::getInstance()->export->exportFormieTranslations();
            }
            if ($hasSite) {
                TranslationManager::getInstance()->export->exportSiteTranslations();
            }
        }

        return $this->asJson([
            'success' => true,
            'saved' => $saved,
            // Only suggest refresh if we've exported files (which might change contexts)
            'shouldRefresh' => false, // Set to true if you want full page refresh
        ]);
    }

    /**
     * Delete translations
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        // Check permission
        if (!Craft::$app->getUser()->checkPermission('translationManager:deleteTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to delete translations');
        }

        $request = Craft::$app->getRequest();
        $ids = $request->getRequiredBodyParam('ids');
        
        if (!is_array($ids)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid IDs provided',
            ]);
        }

        $deleted = TranslationManager::getInstance()->translations->deleteTranslations($ids);

        return $this->asJson([
            'success' => true,
            'deleted' => $deleted,
        ]);
    }
}
