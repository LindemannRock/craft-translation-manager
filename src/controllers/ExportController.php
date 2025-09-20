<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for exporting translations to CSV and PHP files
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
 * Export Controller
 */
class ExportController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = false;
    
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Require permission to export translations or edit settings (for file generation from settings)
        $user = Craft::$app->getUser();
        if (!$user->checkPermission('translationManager:exportTranslations') && 
            !$user->checkPermission('translationManager:editSettings')) {
            throw new ForbiddenHttpException('User does not have permission to export translations');
        }

        return parent::beforeAction($action);
    }

    /**
     * Export translations as CSV download
     * Respects filters when called from translations page
     * Exports all when called from settings page
     */
    public function actionIndex(): Response
    {
        try {
            $request = Craft::$app->getRequest();
            $translationsService = TranslationManager::getInstance()->translations;
            
            // Check if we have filters from the translations page
            $criteria = [];
            
            // NEW: Multi-site support - get site parameter
            $siteParam = $request->getParam('siteId') ?: $request->getBodyParam('siteId');
            $exportAllSites = empty($siteParam); // True if "All Sites" selected
            
            if (!$exportAllSites) {
                $criteria['siteId'] = $siteParam;
            } else {
                // Explicitly tell service to export all sites
                $criteria['allSites'] = true;
                $criteria['siteId'] = null; // Ensure no site filter
            }
            
            $typeParam = $request->getParam('type') ?: $request->getBodyParam('type');
            if ($typeParam && $typeParam !== 'all') {
                $criteria['type'] = $typeParam;
            }
            $statusParam = $request->getParam('status') ?: $request->getBodyParam('status');
            if ($statusParam && $statusParam !== 'all') {
                $criteria['status'] = $statusParam;
            }
            $searchParam = $request->getParam('search') ?: $request->getBodyParam('search');
            if ($searchParam) {
                $criteria['search'] = $searchParam;
            }
            
            // Get translations with filters
            $translations = $translationsService->getTranslations($criteria);
            
            // If no translations found, still export empty CSV with headers
            if (empty($translations)) {
                $settings = TranslationManager::getInstance()->getSettings();
                $csv = "\xEF\xBB\xBF"; // UTF-8 BOM
                if ($settings->showContext) {
                    $csv .= "Translation Key,Translation,Type,Context,Status,Site ID,Site Language\n";
                } else {
                    $csv .= "Translation Key,Translation,Type,Status,Site ID,Site Language\n";
                }
            } else {
                // Convert to array of IDs
                $ids = array_map(function($translation) {
                    return $translation['id'];
                }, $translations);
                
                // Use the same CSV export logic
                $csv = TranslationManager::getInstance()->export->exportSelected($ids);
            }
            
            // Determine filename based on filters
            $filename = 'translations-export';
            
            // Add site info to filename
            if ($exportAllSites) {
                $filename .= '-all-sites';
            } else {
                // Get site info for filename
                $site = Craft::$app->getSites()->getSiteById($siteParam);
                if ($site) {
                    $siteLanguage = strtolower($site->language); // en-US -> en-us, ar -> ar
                    $filename .= '-' . $siteLanguage;
                }
            }
            
            if ($typeParam && $typeParam !== 'all') {
                $filename .= '-' . $typeParam;
            }
            if ($statusParam && $statusParam !== 'all') {
                $filename .= '-' . $statusParam;
            }
            $filename .= '-' . date('Y-m-d-His') . '.csv';
            
            // Create response with CSV data
            $response = Craft::$app->getResponse();
            $response->format = Response::FORMAT_RAW;
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            $response->data = $csv;
            
            return $response;
                
        } catch (\Exception $e) {
            Craft::error('Export failed: ' . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    /**
     * Export download action - works with regular CP URLs instead of action URLs
     */
    public function actionDownload(): Response
    {
        return $this->actionIndex();
    }
    
    /**
     * Export selected translations as CSV
     */
    public function actionSelected(): Response
    {
        $this->requirePostRequest();
        
        $request = Craft::$app->getRequest();
        $ids = $request->getRequiredBodyParam('ids');
        
        if (!is_string($ids)) {
            throw new \Exception('Invalid IDs provided');
        }
        
        $ids = json_decode($ids, true);
        if (!is_array($ids)) {
            throw new \Exception('Invalid IDs format');
        }
        
        $csv = TranslationManager::getInstance()->export->exportSelected($ids);
        
        // Determine filename based on selected translations
        $filename = 'translations-export-selected';
        
        // Get site info from first few translations to determine if single-site or multi-site
        $translationsService = TranslationManager::getInstance()->translations;
        $siteIds = [];
        $types = [];
        
        foreach (array_slice($ids, 0, 5) as $id) { // Check first 5 to determine pattern
            $translation = $translationsService->getTranslationById($id);
            if ($translation) {
                $siteIds[] = $translation->siteId;
                $types[] = strpos($translation->context, 'formie.') === 0 ? 'formie' : 'site';
            }
        }
        
        $siteIds = array_unique($siteIds);
        $types = array_unique($types);
        
        // Add site info to filename
        if (count($siteIds) === 1) {
            $site = Craft::$app->getSites()->getSiteById($siteIds[0]);
            if ($site) {
                $siteLanguage = strtolower($site->language); // en-US -> en-us, ar -> ar
                $filename .= '-' . $siteLanguage;
            }
        } else {
            $filename .= '-multi-site';
        }
        
        // Add type info if all selected are same type
        if (count($types) === 1) {
            $filename .= '-' . $types[0];
        }
        
        $filename .= '-' . date('Y-m-d-His') . '.csv';
        
        // Set headers for CSV download
        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()
            ->set('Content-Type', 'text/csv; charset=utf-8')
            ->set('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->set('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->set('Pragma', 'no-cache')
            ->set('Expires', '0');
        
        $response->data = $csv;
        
        return $response;
    }

    /**
     * Export all translations to files (for auto-export)
     */
    public function actionFiles(): Response
    {
        $this->requirePostRequest();
        
        try {
            // Add debugging
            Craft::info('User requested file export', 'translation-manager');
            $exportService = TranslationManager::getInstance()->export;
            $translationsService = TranslationManager::getInstance()->translations;

            // Check what's available to export first
            $formieCount = count($translationsService->getTranslations(['type' => 'forms', 'status' => 'translated', 'allSites' => true]));
            $siteCount = count($translationsService->getTranslations(['type' => 'site', 'status' => 'translated', 'allSites' => true]));

            Craft::info("Export preparation: found {$formieCount} Formie translations, {$siteCount} site translations", 'translation-manager');
            
            $formieResult = false;
            $siteResult = false;
            $messages = [];
            $warnings = [];
            
            // Only export if there are translations to export
            if ($formieCount > 0) {
                $formieResult = $exportService->exportFormieTranslations();
                if ($formieResult) {
                    $messages[] = TranslationManager::getFormiePluginName() . " files ({$formieCount} translations)";
                }
            } else {
                $warnings[] = 'No translated ' . TranslationManager::getFormiePluginName() . ' translations found';
            }
            
            if ($siteCount > 0) {
                $siteResult = $exportService->exportSiteTranslations();
                if ($siteResult) {
                    $messages[] = "Site files ({$siteCount} translations)";
                }
            } else {
                $warnings[] = 'No translated site translations found';
            }
            
            // Build response message
            $parts = [];
            if (!empty($messages)) {
                $parts[] = 'Generated: ' . implode(', ', $messages);
            }
            if (!empty($warnings)) {
                $parts[] = implode('; ', $warnings);
            }
            
            $message = !empty($parts) ? implode('. ', $parts) : 'No translation files generated';

            // Log the completion with results
            Craft::info("Export completed: {$message}", 'translation-manager');

            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => $message,
                    'debug' => [
                        'formie' => $formieResult,
                        'site' => $siteResult
                    ]
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
            
            Craft::$app->getSession()->setError('Failed to generate translation files: ' . $e->getMessage());
            return $this->redirect('translation-manager');
        }
    }

    /**
     * Export Formie translations to files
     */
    public function actionFormieFiles(): Response
    {
        $this->requirePostRequest();

        try {
            Craft::info('User requested Formie export only', 'translation-manager');
            $translationsService = TranslationManager::getInstance()->translations;
            $formieCount = count($translationsService->getTranslations(['type' => 'forms', 'status' => 'translated', 'allSites' => true]));

            Craft::info("Formie export preparation: found {$formieCount} Formie translations", 'translation-manager');
            
            if ($formieCount > 0) {
                TranslationManager::getInstance()->export->exportFormieTranslations();
                $pluginName = TranslationManager::getFormiePluginName();
                $message = $pluginName . " translation files generated successfully ({$formieCount} translations)";
                Craft::info("Formie export completed: {$message}", 'translation-manager');
            } else {
                $pluginName = TranslationManager::getFormiePluginName();
                $message = "No translated {$pluginName} translations found. Add Arabic translations to forms first.";
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
            
            Craft::$app->getSession()->setError('Failed to generate translation files: ' . $e->getMessage());
            return $this->redirect('translation-manager');
        }
    }

    /**
     * Export site translations to files
     */
    public function actionSiteFiles(): Response
    {
        $this->requirePostRequest();

        try {
            Craft::info('User requested site/category export only', 'translation-manager');
            $translationsService = TranslationManager::getInstance()->translations;
            $siteCount = count($translationsService->getTranslations(['type' => 'site', 'status' => 'translated', 'allSites' => true]));

            Craft::info("Site export preparation: found {$siteCount} site translations", 'translation-manager');
            
            if ($siteCount > 0) {
                TranslationManager::getInstance()->export->exportSiteTranslations();
                $message = "Site translation files generated successfully ({$siteCount} translations)";
                Craft::info("Site export completed: {$message}", 'translation-manager');
            } else {
                $settings = TranslationManager::getInstance()->getSettings();
                $category = $settings->translationCategory;
                $message = "No translated site translations found. Add Arabic translations for |t('{$category}') strings first.";
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
            
            Craft::$app->getSession()->setError('Failed to generate site translation files: ' . $e->getMessage());
            return $this->redirect('translation-manager');
        }
    }
}