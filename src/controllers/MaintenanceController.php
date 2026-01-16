<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for maintenance tasks like cleanup and optimization
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\Response;

/**
 * Maintenance Controller
 *
 * @since 1.0.0
 */
class MaintenanceController extends Controller
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
            case 'clean-unused':
            case 'clean-unused-type':
                if (!$user->checkPermission('translationManager:cleanUnused')) {
                    throw new \yii\web\ForbiddenHttpException('User does not have permission to clean unused translations');
                }
                break;
            case 'scan-templates-action':
            case 'debug-search-page':
            case 'debug-search':
                if (!$user->checkPermission('translationManager:scanTemplates')) {
                    throw new \yii\web\ForbiddenHttpException('User does not have permission to scan templates');
                }
                break;
            case 'recapture-formie':
                if (!$user->checkPermission('translationManager:recaptureFormie')) {
                    throw new \yii\web\ForbiddenHttpException('User does not have permission to recapture Formie translations');
                }
                break;
            default:
                // Index page - allow if user has ANY maintenance-related permission
                $hasAccess =
                    $user->checkPermission('translationManager:maintenance') ||
                    $user->checkPermission('translationManager:cleanUnused') ||
                    $user->checkPermission('translationManager:scanTemplates') ||
                    $user->checkPermission('translationManager:recaptureFormie') ||
                    $user->checkPermission('translationManager:clearTranslations') ||
                    $user->checkPermission('translationManager:clearFormie') ||
                    $user->checkPermission('translationManager:clearSite') ||
                    $user->checkPermission('translationManager:clearAll');

                if (!$hasAccess) {
                    throw new \yii\web\ForbiddenHttpException('User does not have permission to access maintenance');
                }
        }

        return parent::beforeAction($action);
    }

    /**
     * Maintenance index page
     */
    public function actionIndex(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/maintenance/index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Clean up unused translations
     */
    public function actionCleanUnused(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('translationManager:cleanUnused');
        
        $translationsService = TranslationManager::getInstance()->translations;
        
        try {
            // Create backup if enabled
            $settings = TranslationManager::getInstance()->getSettings();
            if ($settings->backupEnabled) {
                try {
                    $backupService = TranslationManager::getInstance()->backup;
                    $backupPath = $backupService->createBackup('before_cleanup');
                    if ($backupPath) {
                        $this->logInfo("Created backup before cleaning unused translations", ['backupPath' => $backupPath]);
                    }
                } catch (\Exception $e) {
                    $this->logError("Failed to create backup before cleaning unused translations", ['error' => $e->getMessage()]);
                    // Continue with the operation even if backup fails
                }
            }
            
            $deleted = $translationsService->cleanUnusedTranslations();
            
            // Check if this is an AJAX request
            if (Craft::$app->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'deleted' => $deleted,
                    'message' => $deleted > 0
                        ? Craft::t('translation-manager', 'Cleaned up {count} unused translation(s) and regenerated translation files.', ['count' => $deleted])
                        : Craft::t('translation-manager', 'No unused translations found.'),
                ]);
            }
            
            // Regular form submission
            if ($deleted > 0) {
                Craft::$app->getSession()->setNotice(
                    Craft::t('translation-manager', 'Cleaned up {count} unused translation(s) and regenerated translation files.', [
                        'count' => $deleted,
                    ])
                );
            } else {
                Craft::$app->getSession()->setNotice(
                    Craft::t('translation-manager', 'No unused translations found.')
                );
            }
        } catch (\Exception $e) {
            // Check if this is an AJAX request
            if (Craft::$app->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => Craft::t('translation-manager', 'Failed to clean up translations: {error}', [
                        'error' => $e->getMessage(),
                    ]),
                ]);
            }
            
            // Regular form submission
            Craft::$app->getSession()->setError(
                Craft::t('translation-manager', 'Failed to clean up translations: {error}', [
                    'error' => $e->getMessage(),
                ])
            );
        }
        
        return $this->redirectToPostedUrl();
    }

    /**
     * Display debug search page
     */
    public function actionDebugSearchPage(): mixed
    {
        $this->requirePermission('translationManager:scanTemplates');
        
        return $this->renderTemplate('translation-manager/debug-search');
    }

    /**
     * Debug search functionality
     */
    public function actionDebugSearch(): Response
    {
        // Check permission
        $this->requirePermission('translationManager:scanTemplates');

        $request = Craft::$app->getRequest();
        $searchTerm = $request->getParam('search', '');
        
        if (empty($searchTerm)) {
            return $this->asJson([
                'error' => 'Please provide a search term',
            ]);
        }
        
        // Direct database searches
        $results = [];
        
        // 1. Exact match
        /** @var \lindemannrock\translationmanager\records\TranslationRecord|null $exactMatch */
        $exactMatch = \lindemannrock\translationmanager\records\TranslationRecord::find()
            ->where(['englishText' => $searchTerm])
            ->one();
        
        if ($exactMatch) {
            $results['exactMatch'] = [
                'id' => $exactMatch->id,
                'text' => $exactMatch->englishText,
                'context' => $exactMatch->context,
                'status' => $exactMatch->status,
            ];
        }
        
        // 2. Case-insensitive exact match
        /** @var \lindemannrock\translationmanager\records\TranslationRecord[] $caseInsensitive */
        $caseInsensitive = \lindemannrock\translationmanager\records\TranslationRecord::find()
            ->where(['LOWER([[englishText]])' => strtolower($searchTerm)])
            ->all();
        
        $results['caseInsensitive'] = array_map(function($t) {
            return [
                'id' => $t->id,
                'text' => $t->englishText,
                'context' => $t->context,
            ];
        }, $caseInsensitive);
        
        // 3. Partial matches
        /** @var \lindemannrock\translationmanager\records\TranslationRecord[] $partialMatches */
        $partialMatches = \lindemannrock\translationmanager\records\TranslationRecord::find()
            ->where(['like', 'englishText', '%' . $searchTerm . '%', false])
            ->limit(10)
            ->all();
        
        $results['partialMatches'] = array_map(function($t) {
            return [
                'id' => $t->id,
                'text' => $t->englishText,
                'context' => $t->context,
            ];
        }, $partialMatches);
        
        // 4. Check what the service returns
        $serviceResults = TranslationManager::getInstance()->translations->getTranslations([
            'search' => $searchTerm,
            'status' => 'all',
            'type' => 'all',
        ]);
        
        $results['serviceResults'] = count($serviceResults);
        
        // 5. Database stats
        $totalCount = \lindemannrock\translationmanager\records\TranslationRecord::find()->count();
        $results['totalTranslations'] = $totalCount;
        
        // 6. Check for similar texts (remove punctuation)
        $cleanSearch = preg_replace('/[^\w\s]/u', '', $searchTerm);
        if ($cleanSearch !== $searchTerm) {
            /** @var \lindemannrock\translationmanager\records\TranslationRecord[] $similarMatches */
            $similarMatches = \lindemannrock\translationmanager\records\TranslationRecord::find()
                ->where(['like', 'englishText', '%' . $cleanSearch . '%', false])
                ->limit(5)
                ->all();
            
            $results['withoutPunctuation'] = array_map(function($t) {
                return [
                    'id' => $t->id,
                    'text' => $t->englishText,
                    'context' => $t->context,
                ];
            }, $similarMatches);
        }
        
        // 7. Search in context
        /** @var \lindemannrock\translationmanager\records\TranslationRecord[] $contextMatches */
        $contextMatches = \lindemannrock\translationmanager\records\TranslationRecord::find()
            ->where(['like', 'context', '%' . $searchTerm . '%', false])
            ->limit(5)
            ->all();
        
        if (!empty($contextMatches)) {
            $results['contextMatches'] = array_map(function($t) {
                return [
                    'id' => $t->id,
                    'text' => $t->englishText,
                    'context' => $t->context,
                ];
            }, $contextMatches);
        }
        
        return $this->asJson([
            'searchTerm' => $searchTerm,
            'results' => $results,
        ]);
    }

    /**
     * Force recapture all Formie translations
     */
    public function actionRecaptureFormie(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('translationManager:recaptureFormie');

        try {
            $count = 0;
            $pluginName = TranslationManager::getFormiePluginName();

            if (class_exists('verbb\\formie\\Formie')) {
                $forms = \verbb\formie\Formie::getInstance()->getForms()->getAllForms();

                foreach ($forms as $form) {
                    TranslationManager::getInstance()->formie->captureFormTranslations($form);
                    $count++;
                }
            }

            return $this->asJson([
                'success' => true,
                'message' => "Recaptured {$pluginName} translations from {$count} form(s)",
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => 'Failed to recapture translations: ' . $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Clean unused translations by type (NEW: For enhanced dropdown)
     */
    public function actionCleanUnusedType(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('translationManager:cleanUnused');

        $request = Craft::$app->getRequest();
        $type = $request->getBodyParam('type');

        // Handle per-category types (category:xxx)
        $category = null;
        if (str_starts_with($type, 'category:')) {
            $category = substr($type, 9);
            $type = 'category';
        }

        if (!in_array($type, ['all', 'category', 'formie'])) {
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid type specified',
            ]);
        }

        try {
            $query = new \craft\db\Query();
            $query->from('{{%translationmanager_translations}}')
                  ->where(['status' => 'unused']);

            $displayType = $type;
            switch ($type) {
                case 'category':
                    // Filter by specific category
                    $query->andWhere(['category' => $category]);
                    $displayType = $category;
                    break;
                case 'formie':
                    $query->andWhere(['or',
                        ['like', 'context', 'formie.%', false],
                        ['=', 'context', 'formie'],
                    ]);
                    break;
                case 'all':
                    // No additional filter needed
                    break;
            }

            /** @var \lindemannrock\translationmanager\records\TranslationRecord[] $unusedTranslations */
            $unusedTranslations = $query->all();
            $count = count($unusedTranslations);
            
            if ($count === 0) {
                return $this->asJson([
                    'success' => true,
                    'message' => "No unused {$displayType} translations found.",
                ]);
            }

            // Create backup if enabled
            $settings = TranslationManager::getInstance()->getSettings();
            if ($settings->backupEnabled) {
                try {
                    $backupService = TranslationManager::getInstance()->backup;
                    $backupPath = $backupService->createBackup("before_cleanup_{$displayType}");
                    $this->logInfo("Created backup before cleaning unused translations", [
                        'type' => $displayType,
                        'backupPath' => $backupPath,
                    ]);
                } catch (\Exception $e) {
                    $this->logError("Failed to create backup", ['error' => $e->getMessage()]);
                }
            }

            $deleted = TranslationManager::getInstance()->translations->deleteTranslations(
                array_column($unusedTranslations, 'id')
            );

            return $this->asJson([
                'success' => true,
                'message' => "Deleted {$deleted} unused {$displayType} translations.",
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => 'Failed to clean unused translations: ' . $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Scan templates action (NEW: For scan templates button)
     */
    public function actionScanTemplatesAction(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('translationManager:scanTemplates');
        
        try {
            $results = TranslationManager::getInstance()->translations->scanTemplatesForUnused();
            
            $message = "Template scan complete: {$results['scanned_files']} files scanned, {$results['marked_unused']} marked as unused";
            if (isset($results['created']) && $results['created'] > 0) {
                $message .= ", {$results['created']} created";
            }
            if ($results['reactivated'] > 0) {
                $message .= ", {$results['reactivated']} reactivated";
            }
            
            return $this->asJson([
                'success' => true,
                'message' => $message,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => 'Failed to scan templates: ' . $e->getMessage(),
            ]);
        }
    }
}
