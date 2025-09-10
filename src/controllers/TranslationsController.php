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
use lindemannrock\translationmanager\TranslationManager;
use lindemannrock\translationmanager\records\TranslationRecord;
use yii\web\Response;
use yii\web\ForbiddenHttpException;

/**
 * Translations Controller
 */
class TranslationsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Require permission to view translations
        if (!Craft::$app->getUser()->checkPermission('translationManager:viewTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to view translations');
        }

        return parent::beforeAction($action);
    }

    /**
     * Translation index page
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
        
        // NEW: Multi-site support - get site selection
        $siteId = $request->getParam('siteId');
        if (!$siteId) {
            // Default to current site
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }
        $currentSite = Craft::$app->getSites()->getSiteById($siteId);
        $dir = $request->getParam('dir', 'asc');
        $type = $request->getParam('type', 'all');
        
        $limit = $settings->itemsPerPage;
        $offset = ($page - 1) * $limit;

        // Get translations with filters
        $criteria = [
            'siteId' => $siteId,  // NEW: Site filtering
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'type' => $type,
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
            'settings' => $settings,
            // NEW: Multi-site variables
            'currentSite' => $currentSite,
            'currentSiteId' => $siteId,
            'allSites' => TranslationManager::getInstance()->getAllowedSites(),
        ]);
    }

    /**
     * Save a single translation
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
                $arabicText = $data['arabicText'] ?? '';
                
                // Validate input length to prevent DoS
                if (strlen($arabicText) > 5000) {
                    continue;
                }
                
                $translation->arabicText = $arabicText;
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

