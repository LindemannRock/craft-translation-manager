<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Core service for managing translations
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Translations Service
 *
 * @since 1.0.0
 */
class TranslationsService extends Component
{
    use LoggingTrait;

    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('translation-manager');
    }
    /**
     * Get translations with optional filters
     */
    public function getTranslations(array $criteria = []): array
    {
        $query = (new Query())
            ->select('*')
            ->from(TranslationRecord::tableName());
            
        $settings = TranslationManager::getInstance()->getSettings();
        
        // Log the request if filters are applied
        if (!empty($criteria)) {
            $filterDesc = [];
            if (isset($criteria['siteId'])) {
                $filterDesc[] = "site:{$criteria['siteId']}";
            }
            if (isset($criteria['status'])) {
                $filterDesc[] = "status:{$criteria['status']}";
            }
            if (isset($criteria['type'])) {
                $filterDesc[] = "type:{$criteria['type']}";
            }
            if (isset($criteria['allSites']) && $criteria['allSites']) {
                $filterDesc[] = "allSites";
            }
            $filters = implode(', ', $filterDesc);
            $this->logDebug('Getting translations with filters', ['filters' => $filters]);
        }

        // Apply site filter (NEW: Multi-site support)
        if (isset($criteria['siteId']) && $criteria['siteId'] !== null) {
            $query->andWhere(['siteId' => $criteria['siteId']]);
        } elseif (!isset($criteria['allSites']) || !$criteria['allSites']) {
            // Default to current site if no specific site requested and allSites not explicitly set
            $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id;
            $query->andWhere(['siteId' => $currentSiteId]);
        }
        // If allSites is true, don't add any site filter

        // Apply status filter
        if (!empty($criteria['status']) && $criteria['status'] !== 'all') {
            $query->andWhere(['status' => $criteria['status']]);
        }

        // Apply type filter
        $hasTypeFilter = false;
        if (!empty($criteria['type']) && $criteria['type'] !== 'all') {
            if ($criteria['type'] === 'forms') {
                $query->andWhere(['or',
                    ['like', 'context', 'formie.%', false],
                    ['=', 'context', 'formie'],
                ]);
                $hasTypeFilter = true;
            } elseif ($criteria['type'] === 'site') {
                $query->andWhere(['and',
                    ['not', ['like', 'context', 'formie.%', false]],
                    ['!=', 'context', 'formie'],
                ]);
                $hasTypeFilter = true;
            }
        }
        
        // Only apply integration filters if no type filter was applied
        if (!$hasTypeFilter) {
            // Filter based on enabled integrations
            $conditions = [];
            if (!$settings->enableFormieIntegration) {
                $conditions[] = ['not', ['like', 'context', 'formie.%', false]];
            }
            if (!$settings->enableSiteTranslations) {
                $conditions[] = ['like', 'context', 'formie.%', false];
            }
            
            // Apply integration filters if both are not disabled
            if (!empty($conditions)) {
                if (count($conditions) === 2) {
                    // If both are disabled, return empty array
                    return [];
                } else {
                    $query->andWhere($conditions[0]);
                }
            }
        }

        // Apply search
        if (!empty($criteria['search'])) {
            // Trim the search term
            $searchTerm = trim($criteria['search']);
            
            if ($searchTerm !== '') {
                // Log the search for debugging
                $this->logInfo("Searching for", ['searchTerm' => $searchTerm]);
                
                // Add wildcards for partial matching
                $searchPattern = '%' . strtr($searchTerm, ['%' => '\%', '_' => '\_', '\\' => '\\\\']) . '%';
                
                $query->andWhere([
                    'or',
                    ['like', 'translationKey', $searchPattern, false],
                    ['like', 'translation', $searchPattern, false],
                    ['like', 'context', $searchPattern, false],
                ]);
                
                // Debug: Log the SQL query
                $sql = $query->createCommand()->getRawSql();
                $this->logDebug("Search SQL", ['sql' => $sql]);
            }
        }

        // Apply sorting
        $sortMap = [
            'translationKey' => 'translationKey',
            'translation' => 'translation',
            'type' => 'context',
            'status' => 'status',
        ];

        $sort = $criteria['sort'] ?? 'translationKey';
        $dir = $criteria['dir'] ?? 'asc';

        if (isset($sortMap[$sort])) {
            $query->orderBy([$sortMap[$sort] => $dir === 'desc' ? SORT_DESC : SORT_ASC]);
        }

        // Get all translations
        $translations = $query->all();

        // Always check usage to update database status
        if (!empty($criteria['includeUsageCheck'])) {
            $translations = $this->checkUsage($translations);
        }

        return $translations;
    }

    /**
     * Get a single translation by ID
     */
    public function getTranslationById(int $id): ?TranslationRecord
    {
        return TranslationRecord::findOne($id);
    }

    /**
     * Save a translation
     */
    public function saveTranslation(TranslationRecord $translation): bool
    {
        $translation->dateUpdated = Db::prepareDateForDb(new \DateTime());
        
        // Don't override 'unused' status
        if ($translation->status !== 'unused') {
            if ($translation->translation) {
                $translation->status = 'translated';
            } else {
                $translation->status = 'pending';
            }
        }

        return $translation->save();
    }

    /**
     * Create or update a translation
     */
    public function createOrUpdateTranslation(string $text, string $context = 'site'): ?TranslationRecord
    {
        // First check if this text contains Twig component blocks with plain text
        if ($this->containsTwigCode($text)) {
            // Try to extract plain text from Twig component blocks
            $plainTexts = $this->extractPlainTextFromTwig($text);
            
            if (!empty($plainTexts)) {
                // Process each extracted plain text
                foreach ($plainTexts as $plainText) {
                    $this->createOrUpdateTranslation($plainText, $context);
                }
                
                $this->logInfo("Extracted plain text from Twig code", [
                    'original' => $text,
                    'extracted' => $plainTexts,
                    'context' => $context,
                ]);
            } else {
                // No plain text found, skip entirely
                $this->logInfo("Skipping translation with Twig code (no plain text found)", [
                    'text' => $text,
                    'context' => $context,
                ]);
            }
            
            return null;
        }

        $hash = md5($text);
        
        // NEW: Multi-site support - create translations for ALL sites
        return $this->createOrUpdateMultiSiteTranslation($text, $hash, $context);
    }
    
    /**
     * Create or update translations for all sites (NEW: Multi-site support)
     */
    private function createOrUpdateMultiSiteTranslation(string $text, string $hash, string $context): ?TranslationRecord
    {
        $sites = TranslationManager::getInstance()->getAllowedSites();
        $primaryTranslation = null;
        
        foreach ($sites as $site) {
            // Check if translation already exists for this site (IGNORE context - text should be unique per site)
            $translation = TranslationRecord::findOne([
                'sourceHash' => $hash,
                'siteId' => $site->id,
            ]);
            
            if (!$translation) {
                // Create new translation for this site
                $translation = new TranslationRecord([
                    'source' => $text,
                    'sourceHash' => $hash,
                    'context' => $context,
                    'siteId' => $site->id,
                    'translationKey' => $text, // Always the original text
                    'translation' => $this->getDefaultTranslation($text, $site),
                    'status' => $this->getDefaultStatus($text, $site),
                    'usageCount' => 1,
                    'lastUsed' => new \DateTime(),
                    'dateCreated' => new \DateTime(),
                    'dateUpdated' => new \DateTime(),
                    'uid' => StringHelper::UUID(),
                ]);
                $translation->save();
                $this->logInfo("Template scanner: Created new multi-site translation", [
                    'text' => $text,
                    'site' => $site->name,
                ]);
            } else {
                // Update existing translation
                $translation->usageCount++;
                $translation->lastUsed = Db::prepareDateForDb(new \DateTime());
                
                // Update context to the most recent usage (for unused tracking)
                $translation->context = $context;
                
                // Reactivate if marked as unused
                if ($translation->status === 'unused') {
                    if ($translation->translation) {
                        $translation->status = 'translated';
                    } else {
                        $translation->status = 'pending';
                    }
                    $this->logInfo("Reactivated translation", [
                        'text' => $text,
                        'site' => $site->name,
                    ]);
                }
                
                $translation->save();
            }
            
            // Return the first translation created (for compatibility)
            if ($primaryTranslation === null) {
                $primaryTranslation = $translation;
            }
        }
        
        return $primaryTranslation;
    }
    
    /**
     * Get default translation for a site (NEW: Multi-site helper)
     */
    private function getDefaultTranslation(string $text, $site): ?string
    {
        // If the text language matches the site language, return the text
        // For English sites, English text = translated
        // For other languages, return null (pending translation)
        if ($site->language === 'en' || $site->language === 'en-US') {
            return $text; // English text = English translation
        }
        
        return null; // Other languages start as pending
    }
    
    /**
     * Get default status for a site (NEW: Multi-site helper)
     */
    private function getDefaultStatus(string $text, $site): string
    {
        // English sites are automatically "translated" (text = translation)
        // Other languages are "pending"
        if ($site->language === 'en' || $site->language === 'en-US') {
            return 'translated';
        }
        
        return 'pending';
    }

    /**
     * Scan all templates for translation usage and mark unused ones (NEW)
     */
    public function scanTemplatesForUnused(): array
    {
        $results = [
            'scanned_files' => 0,
            'found_keys' => [],
            'marked_unused' => 0,
            'reactivated' => 0,
            'errors' => [],
        ];
        
        try {
            // Get the configured translation category
            $settings = TranslationManager::getInstance()->getSettings();
            $category = $settings->translationCategory;
            
            // Scan all .twig files in templates directory
            $templatePath = Craft::$app->getPath()->getSiteTemplatesPath();
            
            // Add warning logs for debugging staging vs local differences
            $this->logInfo("Template scanner starting", ['category' => $category]);
            $this->logInfo("Template scanner path", ['path' => $templatePath]);
            
            $foundKeys = $this->scanTemplateDirectory($templatePath, $category);
            
            $results['scanned_files'] = $this->_scannedFileCount;
            $results['found_keys'] = array_keys($foundKeys);
            
            $this->logInfo("Template scanner results", [
                'scanned_files' => $results['scanned_files'],
                'keys_found' => count($foundKeys),
            ]);
            
            // Get all site translations (not formie)
            $siteTranslations = (new Query())
                ->from(TranslationRecord::tableName())
                ->where(['like', 'context', 'site%', false])
                ->all();
            
            // Create a map of existing translation keys for quick lookup
            $existingKeys = [];
            foreach ($siteTranslations as $translation) {
                $existingKeys[$translation['translationKey']] = $translation;
            }
            
            // First pass: Create new translations found in templates but not in database
            $results['created'] = 0;
            foreach ($foundKeys as $key => $count) {
                if (!isset($existingKeys[$key])) {
                    // New translation key found in templates - create database entry
                    $this->createOrUpdateTranslation($key, 'site');
                    $results['created']++;
                    $this->logInfo("Template scanner: Created new translation (found in templates)", ['key' => $key]);
                }
            }
            
            // Second pass: Manage existing translations
            foreach ($siteTranslations as $translation) {
                $key = $translation['translationKey'];
                
                if (!isset($foundKeys[$key])) {
                    // Translation key not found in any template
                    if ($translation['status'] !== 'unused') {
                        // Mark as unused
                        Db::update(TranslationRecord::tableName(),
                            ['status' => 'unused'],
                            ['id' => $translation['id']]
                        );
                        $results['marked_unused']++;
                        $this->logInfo("Template scanner: Marked as unused (not found in templates)", ['key' => $key]);
                        
                        // Debug: Show available keys that might be similar
                        $similarKeys = array_filter(array_keys($foundKeys), fn($fk) => stripos($fk, substr($key, 0, 10)) !== false);
                        if (!empty($similarKeys)) {
                            $this->logDebug("Template scanner: Similar found keys", ['similarKeys' => $similarKeys]);
                        }
                    }
                } else {
                    // Translation key found in templates
                    if ($translation['status'] === 'unused') {
                        // Reactivate unused translation
                        $newStatus = $translation['translation'] ? 'translated' : 'pending';
                        Db::update(TranslationRecord::tableName(),
                            ['status' => $newStatus],
                            ['id' => $translation['id']]
                        );
                        $results['reactivated']++;
                        $this->logWarning("Template scanner: Reactivated (found in templates)", ['key' => $key]);
                    }
                }
            }
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            $this->logError('Template scanning failed', ['error' => $e->getMessage()]);
        }
        
        return $results;
    }
    
    /**
     * @var int Count of template files scanned during usage checks
     */
    public $_scannedFileCount = 0;
    
    /**
     * Recursively scan template directory for translation usage
     */
    public function scanTemplateDirectory(string $path, string $category): array
    {
        $foundKeys = [];
        
        if (!is_dir($path)) {
            return $foundKeys;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'twig') {
                $this->_scannedFileCount++;
                $content = file_get_contents($file->getPathname());
                
                // Find all |t('category') usage - handle escaped quotes and multi-line
                $literalPattern = '/([\'"`])((?:\\\\.|(?!\1).)*)\1\s*\|\s*t\s*\(\s*[\'"`]' . preg_quote($category) . '[\'"`][\s\S]*?\)/s';
                
                // Also find |t(_globals.primaryTranslationCategory) usage - multi-line support
                $dynamicPattern = '/([\'"`])((?:\\\\.|(?!\1).)*)\1\s*\|\s*t\s*\(\s*_globals\.primaryTranslationCategory[\s\S]*?\)/s';
                
                // Check for literal category usage: 'Text'|t('category')
                if (preg_match_all($literalPattern, $content, $matches)) {
                    foreach ($matches[2] as $key) { // matches[2] now contains the quoted content
                        // Unescape quotes and other escaped characters
                        $unescapedKey = stripslashes($key);
                        $foundKeys[$unescapedKey] = true;
                        
                        // Also store original escaped version for debugging
                        if ($key !== $unescapedKey) {
                            $this->logWarning("Template scanner: Unescaped", [
                                'from' => $key,
                                'to' => $unescapedKey,
                            ]);
                        }
                    }
                }
                
                // Check for dynamic category usage: 'Text'|t(_globals.primaryTranslationCategory)
                if (preg_match_all($dynamicPattern, $content, $matches)) {
                    foreach ($matches[2] as $key) { // matches[2] now contains the quoted content
                        // Unescape quotes and other escaped characters
                        $unescapedKey = stripslashes($key);
                        $foundKeys[$unescapedKey] = true;
                        
                        $this->logWarning("Template scanner: Found dynamic translation using _globals.primaryTranslationCategory", [
                            'key' => $unescapedKey,
                        ]);
                    }
                }
            }
        }
        
        return $foundKeys;
    }

    /**
     * Delete translations by IDs
     */
    public function deleteTranslations(array $ids): int
    {
        return TranslationRecord::deleteAll(['id' => $ids]);
    }

    /**
     * Check which translations are still in use
     */
    private function checkUsage(array $translations): array
    {
        $this->logInfo('Starting usage check', ['count' => count($translations)]);
        
        // For generic contexts, we need to check if the text is used anywhere
        $activeTexts = [];
        
        if (class_exists('verbb\\formie\\Formie')) {
            $forms = \verbb\formie\Formie::getInstance()->getForms()->getAllForms();
            
            foreach ($forms as $form) {
                // Collect form title
                if ($form->title) {
                    $activeTexts[$form->title] = true;
                }
                
                // Collect button labels from page settings
                foreach ($form->getPages() as $page) {
                    $pageSettings = $page->getPageSettings();

                    if ($pageSettings->submitButtonLabel ?? false) {
                        $activeTexts[$pageSettings->submitButtonLabel] = true;
                        $this->logDebug("Form submit button", [
                            'form' => $form->handle,
                            'label' => $pageSettings->submitButtonLabel,
                        ]);
                    }

                    if ($pageSettings->backButtonLabel ?? false) {
                        $activeTexts[$pageSettings->backButtonLabel] = true;
                    }

                    if ($pageSettings->saveButtonLabel ?? false) {
                        $activeTexts[$pageSettings->saveButtonLabel] = true;
                    }
                }
                
                // Collect submission message (both HTML and plain text versions)
                if (method_exists($form->settings, 'getSubmitActionMessage')) {
                    $htmlMessage = $form->settings->getSubmitActionMessage();
                    if ($htmlMessage) {
                        // Store both HTML and plain text versions
                        $activeTexts[$htmlMessage] = true;
                        $plainMessage = $this->extractPlainTextFromFormie($htmlMessage);
                        if ($plainMessage && $plainMessage !== $htmlMessage) {
                            $activeTexts[$plainMessage] = true;
                        }
                        $this->logDebug("Form submit message", [
                            'form' => $form->handle,
                            'message' => $htmlMessage,
                        ]);
                    }
                }
                
                // Collect error message (both HTML and plain text versions)
                if (method_exists($form->settings, 'getErrorMessage')) {
                    $htmlMessage = $form->settings->getErrorMessage();
                    if ($htmlMessage) {
                        // Store both HTML and plain text versions
                        $activeTexts[$htmlMessage] = true;
                        $plainMessage = $this->extractTextFromTipTap($htmlMessage);
                        if ($plainMessage && $plainMessage !== $htmlMessage) {
                            $activeTexts[$plainMessage] = true;
                        }
                        $this->logDebug("Form error message", [
                            'form' => $form->handle,
                            'message' => $htmlMessage,
                        ]);
                    }
                }
                
                // Collect all field texts (including nested fields in groups)
                foreach ($form->getCustomFields() as $field) {
                    $this->collectFieldTexts($field, $activeTexts);
                }
            }
            
            $this->logInfo('Found active texts in forms', ['count' => count($activeTexts)]);
        }

        foreach ($translations as &$translation) {
            $isUsed = false;

            // For Formie translations
            if (str_starts_with($translation['context'], 'formie.') || $translation['context'] === 'formie') {
                // Default Formie translations (validation messages) are always considered used
                if (str_starts_with($translation['context'], 'formie.defaults.')) {
                    $isUsed = true;
                } else {
                    // Check if the English text is still used in any form
                    $isUsed = isset($activeTexts[$translation['translationKey']]);
                }

                $usedStatus = $isUsed ? 'used' : 'unused';
                $this->logDebug("Checking formie translation", [
                    'key' => $translation['translationKey'],
                    'context' => $translation['context'],
                    'status' => $usedStatus,
                ]);
            } else {
                // For site translations, we can't check if they're used
                // So we always consider them as "in use"
                $isUsed = true;
            }

            $translation['isUsed'] = $isUsed;
            $translation['formCount'] = $isUsed ? 1 : 0;

            // Update status in database if it's a Formie translation that's not used
            // Skip default Formie translations - they're always used
            if (!$isUsed && str_starts_with($translation['context'], 'formie.') && !str_starts_with($translation['context'], 'formie.defaults.')) {
                // Update the record's status to 'unused' if it's not already
                if ($translation['status'] !== 'unused') {
                    $this->logInfo('Marking translation as unused', [
                        'id' => $translation['id'],
                        'context' => $translation['context'],
                        'translationKey' => $translation['translationKey'],
                    ]);
                    
                    // Use direct DB update for better performance
                    $updated = Db::update(TranslationRecord::tableName(),
                        ['status' => 'unused'],
                        ['id' => $translation['id']]
                    );
                    
                    if ($updated) {
                        $translation['status'] = 'unused';
                        $this->logInfo('Successfully marked as unused');
                    } else {
                        $this->logError('Failed to update unused status');
                    }
                }
            }
        }

        return $translations;
    }

    /**
     * Get translation statistics
     */
    public function getStatistics(?int $siteId = null): array
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $query = (new Query())
            ->from(TranslationRecord::tableName());
            
        // Apply site filter if specified
        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
        }
            
        // Don't apply integration filters for statistics - show all data
        // This gives a true picture of what's in the database

        // Get all counts in one query to ensure consistency
        $statusCounts = (new Query())
            ->select(['status', 'COUNT(*) as count'])
            ->from(TranslationRecord::tableName())
            ->andWhere($siteId ? ['siteId' => $siteId] : [])
            ->groupBy('status')
            ->createCommand()
            ->queryAll();
            
        // Convert to associative array
        $counts = [];
        $total = 0;
        foreach ($statusCounts as $row) {
            $counts[$row['status']] = (int)$row['count'];
            $total += (int)$row['count'];
        }
        
        $pending = $counts['pending'] ?? 0;
        $translated = $counts['translated'] ?? 0;
        $approved = $counts['approved'] ?? 0;
        $unused = $counts['unused'] ?? 0;
        

        // Get type counts with same site filter
        $formieCount = (new Query())
            ->from(TranslationRecord::tableName())
            ->andWhere($siteId ? ['siteId' => $siteId] : [])
            ->andWhere(['or',
                ['like', 'context', 'formie.%', false],
                ['=', 'context', 'formie'],
            ])->count();
        
        $siteCount = (new Query())
            ->from(TranslationRecord::tableName())
            ->andWhere($siteId ? ['siteId' => $siteId] : [])
            ->andWhere(['and',
                ['not', ['like', 'context', 'formie.%', false]],
                ['!=', 'context', 'formie'],
            ])->count();

        $stats = [
            'total' => $total,
            'pending' => $pending,
            'translated' => $translated,
            'approved' => $approved,
            'unused' => $unused,
            'formie' => $formieCount,
            'site' => $siteCount,
        ];
        
        // Add site information if filtering by specific site
        if ($siteId) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            $stats['siteInfo'] = $site ? [
                'id' => $site->id,
                'name' => $site->name,
                'language' => $site->language,
            ] : null;
        }
        
        return $stats;
    }
    
    /**
     * Clear all Formie translations
     */
    public function clearFormieTranslations(): int
    {
        $count = Db::delete(TranslationRecord::tableName(), [
            'like', 'context', 'formie.%', false,
        ]);
        
        // Delete corresponding translation files
        if ($count > 0) {
            $this->deleteFormieTranslationFiles();
        }
        
        $this->logInfo("Cleared Formie translations", ['count' => $count]);
        
        return $count;
    }
    
    /**
     * Clear all site translations
     */
    public function clearSiteTranslations(): int
    {
        $count = Db::delete(TranslationRecord::tableName(), [
            'not', ['like', 'context', 'formie.%', false],
        ]);
        
        // Delete corresponding translation files
        if ($count > 0) {
            $this->deleteSiteTranslationFiles();
        }
        
        $this->logInfo("Cleared site translations", ['count' => $count]);
        
        return $count;
    }
    
    /**
     * Clear all translations
     */
    public function clearAllTranslations(): int
    {
        $count = Db::delete(TranslationRecord::tableName());
        
        // Delete all translation files
        if ($count > 0) {
            $this->deleteFormieTranslationFiles();
            $this->deleteSiteTranslationFiles();
        }
        
        $this->logInfo("Cleared ALL translations", ['count' => $count]);
        
        return $count;
    }
    
    /**
     * Delete Formie translation files
     */
    private function deleteFormieTranslationFiles(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $basePath = $settings->getExportPath();
        
        // Get actual site languages dynamically
        $sites = TranslationManager::getInstance()->getAllowedSites();
        foreach ($sites as $site) {
            $file = $basePath . '/' . $site->language . '/formie.php';
            if (file_exists($file)) {
                @unlink($file);
                $this->logInfo("Deleted stale Formie file", ['file' => $file]);
            }
        }
    }
    
    /**
     * Delete site translation files
     */
    private function deleteSiteTranslationFiles(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $basePath = $settings->getExportPath();
        $category = $settings->translationCategory;
        $filename = $category . '.php';
        
        $enFile = $basePath . '/en/' . $filename;
        $arFile = $basePath . '/ar/' . $filename;
        
        if (file_exists($enFile)) {
            @unlink($enFile);
            $this->logInfo("Deleted site English translation file", ['file' => $enFile]);
        }
        
        if (file_exists($arFile)) {
            @unlink($arFile);
            $this->logInfo("Deleted site Arabic translation file", ['file' => $arFile]);
        }
    }
    
    /**
     * Apply skip patterns to existing site translations
     * This method removes existing site translations that match the current skip patterns
     */
    public function applySkipPatternsToExisting(): int
    {
        $settings = TranslationManager::getInstance()->getSettings();
        
        $this->logInfo('Starting applySkipPatternsToExisting', [
            'skipPatterns' => $settings->skipPatterns,
            'skipPatternsCount' => count($settings->skipPatterns ?? []),
        ]);
        
        if (empty($settings->skipPatterns)) {
            $this->logInfo('No skip patterns configured');
            return 0;
        }
        
        $deleted = 0;
        
        // Get all site translations (context NOT LIKE 'formie.%')
        $siteTranslations = $this->getTranslations(['type' => 'site']);
        
        $this->logInfo('Found site translations', ['count' => count($siteTranslations)]);
        
        foreach ($siteTranslations as $translation) {
            $translationKey = $translation['translationKey'];
            
            $this->logInfo("Checking translation", [
                'text' => $translationKey,
                'id' => $translation['id'],
            ]);
            
            // Check if this translation matches any skip pattern
            foreach ($settings->skipPatterns as $pattern) {
                $pattern = trim($pattern); // Trim whitespace
                
                $this->logInfo("Checking pattern", [
                    'pattern' => $pattern,
                    'text' => $translationKey,
                    'contains_check' => str_contains($translationKey, $pattern) ? 'YES' : 'NO',
                ]);
                
                if (!empty($pattern) && str_contains($translationKey, $pattern)) {
                    $this->logInfo("Found matching translation", [
                        'pattern' => $pattern,
                        'text' => $translationKey,
                        'translationId' => $translation['id'],
                    ]);
                    
                    // Delete this translation
                    $translationRecord = TranslationRecord::findOne($translation['id']);
                    if ($translationRecord) {
                        $translationRecord->delete();
                        $deleted++;
                        $this->logInfo("Successfully deleted translation", [
                            'pattern' => $pattern,
                            'text' => $translationKey,
                            'id' => $translation['id'],
                        ]);
                        break; // Stop checking other patterns for this translation
                    } else {
                        $this->logWarning("Translation record not found for deletion", [
                            'id' => $translation['id'],
                        ]);
                    }
                }
            }
        }
        
        $this->logInfo('Completed applying skip patterns to existing translations', [
            'patterns' => $settings->skipPatterns,
            'deleted' => $deleted,
            'totalSiteTranslations' => count($siteTranslations),
        ]);
        
        return $deleted;
    }
    
    /**
     * Get count of unused translations (forms that no longer exist)
     */
    public function getUnusedTranslationCount(): int
    {
        // Simply count translations marked as "not used" from Formie
        return (int) TranslationRecord::find()
            ->where(['like', 'context', 'formie.%', false])
            ->andWhere(['status' => 'unused'])
            ->count();
    }
    
    /**
     * Clean up unused translations and regenerate files
     */
    public function cleanUnusedTranslations(): int
    {
        // Get all unused Formie translations
        $unusedTranslations = TranslationRecord::find()
            ->where(['like', 'context', 'formie.%', false])
            ->andWhere(['status' => 'unused'])
            ->all();
        
        $deleted = 0;
        
        foreach ($unusedTranslations as $translation) {
            if ($translation->delete()) {
                $deleted++;
            }
        }
        
        // If we deleted any translations, regenerate the Formie translation files
        if ($deleted > 0) {
            $this->logInfo("Cleaned up unused translations", ['deleted' => $deleted]);
            
            // Regenerate Formie translation files
            $exportService = TranslationManager::getInstance()->export;
            $exportService->exportFormieTranslations();
            
            $this->logInfo("Regenerated Formie translation files after cleanup");
        }
        
        return $deleted;
    }

    /**
     * Check if text contains Twig code
     *
     * @param string $text
     * @return bool
     */
    private function containsTwigCode(string $text): bool
    {
        // Check for Twig variable syntax {{ ... }}
        if (preg_match('/\{\{.*?\}\}/', $text)) {
            return true;
        }
        
        // Check for Twig tag syntax {% ... %}
        if (preg_match('/\{%.*?%\}/', $text)) {
            return true;
        }
        
        // Check for Twig comment syntax {# ... #}
        if (preg_match('/\{#.*?#\}/', $text)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Extract plain text content from between Twig tags
     * For example, from "{% x:text %}Hello World{% endx %}" extract "Hello World"
     *
     * @param string $text
     * @return array
     */
    private function extractPlainTextFromTwig(string $text): array
    {
        $plainTexts = [];
        
        // Pattern to match Twig component blocks with content
        // Matches: {% x:component ... %}content{% endx %}
        $pattern = '/\{%\s*x:\w+[^%]*%\}(.*?)\{%\s*endx\s*%\}/s';
        
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $content) {
                // Trim whitespace and newlines
                $content = trim($content);
                
                // Skip if empty
                if (empty($content)) {
                    continue;
                }
                
                // Skip if the content itself contains Twig code
                if ($this->containsTwigCode($content)) {
                    continue;
                }
                
                // Only add if it's meaningful text (not just whitespace or punctuation)
                if (strlen($content) > 1 && preg_match('/\w/', $content)) {
                    $plainTexts[] = $content;
                }
            }
        }
        
        return $plainTexts;
    }

    /**
     * Extract plain text from HTML or TipTap JSON format (same as FormieIntegration)
     */
    private function extractPlainTextFromFormie($content): string
    {
        if (empty($content)) {
            return '';
        }

        // Try TipTap JSON first
        $jsonText = $this->extractTextFromTipTap($content);
        if ($jsonText !== $content) {
            return $jsonText; // Successfully extracted from JSON
        }

        // If not JSON, try HTML stripping with paragraph preservation
        if (is_string($content) && (strpos($content, '<') !== false)) {
            // Replace </p><p> with newlines to preserve paragraph breaks
            $content = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n\n", $content);
            return trim(strip_tags($content));
        }

        // Return as-is if neither JSON nor HTML
        return $content;
    }

    /**
     * Recursively collect field texts including nested fields in groups
     */
    private function collectFieldTexts($field, array &$activeTexts): void
    {
        // Basic field properties
        if ($field->label) {
            $activeTexts[$field->label] = true;
        }

        if ($field->instructions) {
            $activeTexts[$field->instructions] = true;
        }

        if (property_exists($field, 'placeholder') && $field->placeholder) {
            $activeTexts[$field->placeholder] = true;
        }

        if (property_exists($field, 'errorMessage') && $field->errorMessage) {
            $activeTexts[$field->errorMessage] = true;
        }

        // Field type-specific content
        $fieldClass = get_class($field);

        switch ($fieldClass) {
            case 'verbb\formie\fields\Group':
                // Recursively process nested fields in groups
                if (method_exists($field, 'getCustomFields')) {
                    foreach ($field->getCustomFields() as $nestedField) {
                        $this->collectFieldTexts($nestedField, $activeTexts);
                    }
                }
                break;

            case 'verbb\formie\fields\Html':
                if (property_exists($field, 'htmlContent') && $field->htmlContent) {
                    $activeTexts[$field->htmlContent] = true;
                }
                break;

            case 'verbb\formie\fields\Heading':
                if (property_exists($field, 'headingText') && $field->headingText) {
                    $activeTexts[$field->headingText] = true;
                }
                break;

            case 'lindemannrock\formieparagraphfield\fields\Paragraph':
                if (property_exists($field, 'paragraphContent') && $field->paragraphContent) {
                    $activeTexts[$field->paragraphContent] = true;
                }
                break;

            case 'lindemannrock\formieratingfield\fields\Rating':
                if (property_exists($field, 'showEndpointLabels') && $field->showEndpointLabels) {
                    if (property_exists($field, 'startLabel') && $field->startLabel) {
                        $activeTexts[$field->startLabel] = true;
                    }
                    if (property_exists($field, 'endLabel') && $field->endLabel) {
                        $activeTexts[$field->endLabel] = true;
                    }
                }

                // customLabels is an array of objects: [{"value": "", "label": "Text"}, ...]
                if (property_exists($field, 'customLabels') && is_array($field->customLabels)) {
                    foreach ($field->customLabels as $labelData) {
                        if (is_array($labelData) && !empty($labelData['label'])) {
                            $activeTexts[$labelData['label']] = true;
                        }
                    }
                }
                break;

            case 'verbb\formie\fields\Dropdown':
            case 'verbb\formie\fields\Radio':
            case 'verbb\formie\fields\Checkboxes':
                if (property_exists($field, 'options') && is_array($field->options)) {
                    foreach ($field->options as $option) {
                        if (isset($option['label']) && !empty($option['label'])) {
                            $activeTexts[$option['label']] = true;
                        }
                    }
                }
                break;

            case 'verbb\formie\fields\Agree':
                // Use getDescriptionHtml() method to get the actual HTML that Formie uses
                if (method_exists($field, 'getDescriptionHtml')) {
                    $descriptionHtml = $field->getDescriptionHtml();
                    if (!empty($descriptionHtml)) {
                        $activeTexts[(string)$descriptionHtml] = true;
                    }
                }

                // Agree field checked/unchecked values
                if (property_exists($field, 'checkedValue') && $field->checkedValue) {
                    $activeTexts[$field->checkedValue] = true;
                }

                if (property_exists($field, 'uncheckedValue') && $field->uncheckedValue) {
                    $activeTexts[$field->uncheckedValue] = true;
                }
                break;
        }
    }

    /**
     * Extract text from TipTap/ProseMirror JSON format (same as FormieIntegration)
     */
    private function extractTextFromTipTap($jsonString): string
    {
        if (empty($jsonString)) {
            return '';
        }

        // If it's already plain text, return it
        if (!is_string($jsonString) || $jsonString[0] !== '[') {
            return $jsonString;
        }

        try {
            $data = json_decode($jsonString, true);
            if (!is_array($data)) {
                return $jsonString;
            }

            $textParts = [];

            // Iterate through all blocks
            foreach ($data as $block) {
                if (isset($block['content']) && is_array($block['content'])) {
                    foreach ($block['content'] as $content) {
                        if (isset($content['type']) && $content['type'] === 'text' && isset($content['text'])) {
                            $textParts[] = $content['text'];
                        }
                    }
                }
            }

            // Join with line breaks to preserve paragraph structure
            return implode("\n", $textParts);
        } catch (\Exception) {
            // If JSON parsing fails, return original
            return $jsonString;
        }
    }
}
