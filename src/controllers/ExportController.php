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
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Export Controller
 *
 * @since 1.0.0
 */
class ExportController extends Controller
{
    use LoggingTrait;
    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = false;
    
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        $user = Craft::$app->getUser();

        switch ($action->id) {
            case 'index':
                // CSV export
                if (!$user->checkPermission('translationManager:exportTranslations')) {
                    throw new ForbiddenHttpException('User does not have permission to export translations');
                }
                break;
            case 'files':
                // Generate all files
                if (!$user->checkPermission('translationManager:generateAllTranslations')) {
                    throw new ForbiddenHttpException('User does not have permission to generate translation files');
                }
                break;
            case 'formie-files':
                // Generate Formie files
                if (!$user->checkPermission('translationManager:generateFormieTranslations')) {
                    throw new ForbiddenHttpException('User does not have permission to generate Formie translation files');
                }
                break;
            case 'site-files':
                // Generate site files
                if (!$user->checkPermission('translationManager:generateSiteTranslations')) {
                    throw new ForbiddenHttpException('User does not have permission to generate site translation files');
                }
                break;
            case 'category-files':
                // Generate single category files
                if (!$user->checkPermission('translationManager:generateSiteTranslations')) {
                    throw new ForbiddenHttpException('User does not have permission to generate category translation files');
                }
                break;
            default:
                // For any other actions, require export permission
                if (!$user->checkPermission('translationManager:exportTranslations')) {
                    throw new ForbiddenHttpException('User does not have permission to export translations');
                }
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

            // Language filter (preferred) or legacy siteId support
            $languageParam = $request->getParam('language') ?: $request->getBodyParam('language');
            $siteParam = $request->getParam('siteId') ?: $request->getBodyParam('siteId'); // Legacy
            $exportAll = empty($languageParam) && empty($siteParam);

            if (!empty($languageParam)) {
                $criteria['language'] = $languageParam;
            } elseif (!empty($siteParam)) {
                // Legacy: convert siteId to language
                $site = Craft::$app->getSites()->getSiteById($siteParam);
                if ($site) {
                    $criteria['language'] = $site->language;
                }
            } else {
                // Export all languages
                $criteria['allSites'] = true;
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

            // Category filter
            $categoryParam = $request->getParam('category') ?: $request->getBodyParam('category');
            if ($categoryParam && $categoryParam !== 'all') {
                if ($categoryParam === 'formie') {
                    $criteria['type'] = 'forms';
                } else {
                    $criteria['type'] = 'site';
                    $criteria['category'] = $categoryParam;
                }
            }

            // Get translations with filters
            $translations = $translationsService->getTranslations($criteria);
            
            // If no translations found, still export empty CSV with headers
            if (empty($translations)) {
                $settings = TranslationManager::getInstance()->getSettings();
                $csv = "\xEF\xBB\xBF"; // UTF-8 BOM
                if ($settings->showContext) {
                    $csv .= "Translation Key,Translation,Category,Type,Context,Status,Language\n";
                } else {
                    $csv .= "Translation Key,Translation,Category,Type,Status,Language\n";
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
            $settings = TranslationManager::$plugin->getSettings();
            $filenamePart = strtolower(str_replace(' ', '-', $settings->getPluralLowerDisplayName()));
            $filename = $filenamePart . '-export';

            // Add language info to filename (sanitized to prevent header injection)
            if ($exportAll) {
                $filename .= '-all-languages';
            } elseif (!empty($languageParam)) {
                $filename .= '-' . $this->sanitizeFilenamePart($languageParam);
            } elseif (!empty($siteParam)) {
                // Legacy: use site's language
                $site = Craft::$app->getSites()->getSiteById($siteParam);
                if ($site) {
                    $filename .= '-' . $this->sanitizeFilenamePart($site->language);
                }
            }

            // Add category to filename (sanitized)
            if ($categoryParam && $categoryParam !== 'all') {
                $filename .= '-' . $this->sanitizeFilenamePart($categoryParam);
            }

            if ($typeParam && $typeParam !== 'all') {
                $filename .= '-' . $this->sanitizeFilenamePart($typeParam);
            }
            if ($statusParam && $statusParam !== 'all') {
                $filename .= '-' . $this->sanitizeFilenamePart($statusParam);
            }
            $filename .= '-' . date('Y-m-d') . '.csv';
            
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
            $this->logError('Export failed', ['error' => $e->getMessage()]);
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
        $settings = TranslationManager::$plugin->getSettings();
        $filenamePart = strtolower(str_replace(' ', '-', $settings->getPluralLowerDisplayName()));
        $filename = $filenamePart . '-export-selected';
        
        // Get language info from first few translations to determine if single-language or multi-language
        $translationsService = TranslationManager::getInstance()->translations;
        $languages = [];
        $types = [];

        foreach (array_slice($ids, 0, 5) as $id) { // Check first 5 to determine pattern
            $translation = $translationsService->getTranslationById($id);
            if ($translation) {
                $languages[] = $translation->language;
                $types[] = strpos($translation->context, 'formie.') === 0 ? 'formie' : 'site';
            }
        }

        $languages = array_unique($languages);
        $types = array_unique($types);

        // Add language info to filename (sanitized)
        if (count($languages) === 1 && !empty($languages[0])) {
            $filename .= '-' . $this->sanitizeFilenamePart($languages[0]);
        } else {
            $filename .= '-multi-language';
        }

        // Add type info if all selected are same type (sanitized)
        if (count($types) === 1) {
            $filename .= '-' . $this->sanitizeFilenamePart($types[0]);
        }
        
        $filename .= '-' . date('Y-m-d') . '.csv';
        
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
            $this->logInfo('User requested file export');
            $exportService = TranslationManager::getInstance()->export;
            $translationsService = TranslationManager::getInstance()->translations;

            // Check what's available to export first
            $formieCount = count($translationsService->getTranslations(['type' => 'forms', 'status' => 'translated', 'allSites' => true]));
            $siteCount = count($translationsService->getTranslations(['type' => 'site', 'status' => 'translated', 'allSites' => true]));

            $this->logInfo("Export preparation", [
                'formieCount' => $formieCount,
                'siteCount' => $siteCount,
            ]);
            
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
            $this->logInfo("Export completed", ['message' => $message]);

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
            $this->logInfo('User requested Formie export only');
            $translationsService = TranslationManager::getInstance()->translations;
            $formieCount = count($translationsService->getTranslations(['type' => 'forms', 'status' => 'translated', 'allSites' => true]));

            $this->logInfo("Formie export preparation", ['formieCount' => $formieCount]);

            if ($formieCount > 0) {
                TranslationManager::getInstance()->export->exportFormieTranslations();
                $pluginName = TranslationManager::getFormiePluginName();
                $message = $pluginName . " translation files generated successfully ({$formieCount} translations)";
                $this->logInfo("Formie export completed", ['message' => $message]);
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
            $this->logInfo('User requested site/category export only');
            $translationsService = TranslationManager::getInstance()->translations;
            $siteCount = count($translationsService->getTranslations(['type' => 'site', 'status' => 'translated', 'allSites' => true]));

            $this->logInfo("Site export preparation", ['siteCount' => $siteCount]);

            if ($siteCount > 0) {
                TranslationManager::getInstance()->export->exportSiteTranslations();
                $message = "Site translation files generated successfully ({$siteCount} translations)";
                $this->logInfo("Site export completed", ['message' => $message]);
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

    /**
     * Export a specific category's translations to files
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
                throw new \Exception("Category '{$category}' is not enabled");
            }

            $this->logInfo('User requested single category export', ['category' => $category]);
            $translationsService = TranslationManager::getInstance()->translations;
            $count = count($translationsService->getTranslations([
                'type' => 'site',
                'category' => $category,
                'status' => 'translated',
                'allSites' => true,
            ]));

            $this->logInfo("Category export preparation", ['category' => $category, 'count' => $count]);

            if ($count > 0) {
                TranslationManager::getInstance()->export->exportCategoryTranslations($category);
                $message = ucfirst($category) . " translation files generated successfully ({$count} translations)";
                $this->logInfo("Category export completed", ['message' => $message]);
            } else {
                $message = "No translated translations found for category '{$category}'. Add translations for |t('{$category}') strings first.";
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

            Craft::$app->getSession()->setError('Failed to generate category translation files: ' . $e->getMessage());
            return $this->redirect('translation-manager');
        }
    }

    /**
     * Sanitize a string for use in filename
     *
     * Prevents header injection and ensures valid filename characters.
     * Only allows alphanumeric, dots, underscores, and hyphens.
     */
    private function sanitizeFilenamePart(string $part): string
    {
        // Replace any non-alphanumeric characters (except ._-) with hyphen
        $sanitized = preg_replace('/[^a-z0-9._-]+/i', '-', $part);
        // Remove leading/trailing hyphens and convert to lowercase
        return strtolower(trim($sanitized, '-'));
    }
}
