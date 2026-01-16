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
use lindemannrock\translationmanager\helpers\TemplateHelper;
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

    /**
     * Script family mapping for language codes
     */
    private const LANGUAGE_SCRIPT_MAP = [
        // Latin script languages
        'en' => 'latin', 'en-US' => 'latin', 'en-GB' => 'latin',
        'de' => 'latin', 'de-DE' => 'latin', 'de-AT' => 'latin', 'de-CH' => 'latin',
        'fr' => 'latin', 'fr-FR' => 'latin', 'fr-CA' => 'latin',
        'es' => 'latin', 'es-ES' => 'latin', 'es-MX' => 'latin',
        'it' => 'latin', 'pt' => 'latin', 'pt-BR' => 'latin',
        'nl' => 'latin', 'sv' => 'latin', 'da' => 'latin', 'no' => 'latin',
        'fi' => 'latin', 'pl' => 'latin', 'cs' => 'latin', 'sk' => 'latin',
        'hu' => 'latin', 'ro' => 'latin', 'hr' => 'latin', 'sl' => 'latin',
        'et' => 'latin', 'lv' => 'latin', 'lt' => 'latin',
        'id' => 'latin', 'ms' => 'latin', 'vi' => 'latin',
        'tr' => 'latin', 'az' => 'latin',
        // Arabic script languages
        'ar' => 'arabic', 'ar-SA' => 'arabic', 'ar-AE' => 'arabic', 'ar-EG' => 'arabic',
        'fa' => 'arabic', 'fa-IR' => 'arabic',
        'ur' => 'arabic', 'ur-PK' => 'arabic',
        'ps' => 'arabic', 'ku' => 'arabic',
        // CJK languages
        'zh' => 'chinese', 'zh-CN' => 'chinese', 'zh-TW' => 'chinese', 'zh-HK' => 'chinese',
        'ja' => 'japanese', 'ja-JP' => 'japanese',
        'ko' => 'korean', 'ko-KR' => 'korean',
        // Cyrillic script languages
        'ru' => 'cyrillic', 'ru-RU' => 'cyrillic',
        'uk' => 'cyrillic', 'uk-UA' => 'cyrillic',
        'bg' => 'cyrillic', 'sr' => 'cyrillic', 'mk' => 'cyrillic',
        'be' => 'cyrillic', 'kk' => 'cyrillic', 'ky' => 'cyrillic',
        // Hebrew
        'he' => 'hebrew', 'he-IL' => 'hebrew', 'yi' => 'hebrew',
        // Greek
        'el' => 'greek', 'el-GR' => 'greek',
        // Thai
        'th' => 'thai', 'th-TH' => 'thai',
        // Indic scripts
        'hi' => 'devanagari', 'hi-IN' => 'devanagari',
        'mr' => 'devanagari', 'ne' => 'devanagari', 'sa' => 'devanagari',
        'bn' => 'bengali', 'bn-BD' => 'bengali', 'bn-IN' => 'bengali',
        'ta' => 'tamil', 'ta-IN' => 'tamil',
        'te' => 'telugu', 'kn' => 'kannada', 'ml' => 'malayalam',
        'gu' => 'gujarati', 'pa' => 'gurmukhi',
        // Other scripts
        'ka' => 'georgian', 'hy' => 'armenian',
    ];

    /**
     * Unicode regex patterns for each script family
     */
    private const SCRIPT_PATTERNS = [
        'latin' => '/[\x{0041}-\x{007A}\x{00C0}-\x{00FF}\x{0100}-\x{017F}\x{0180}-\x{024F}]/u',
        'arabic' => '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u',
        'chinese' => '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{20000}-\x{2A6DF}]/u',
        'japanese' => '/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u',
        'korean' => '/[\x{AC00}-\x{D7AF}\x{1100}-\x{11FF}\x{3130}-\x{318F}]/u',
        'cyrillic' => '/[\x{0400}-\x{04FF}\x{0500}-\x{052F}]/u',
        'hebrew' => '/[\x{0590}-\x{05FF}\x{FB1D}-\x{FB4F}]/u',
        'greek' => '/[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}]/u',
        'thai' => '/[\x{0E00}-\x{0E7F}]/u',
        'devanagari' => '/[\x{0900}-\x{097F}\x{A8E0}-\x{A8FF}]/u',
        'bengali' => '/[\x{0980}-\x{09FF}]/u',
        'tamil' => '/[\x{0B80}-\x{0BFF}]/u',
        'telugu' => '/[\x{0C00}-\x{0C7F}]/u',
        'kannada' => '/[\x{0C80}-\x{0CFF}]/u',
        'malayalam' => '/[\x{0D00}-\x{0D7F}]/u',
        'gujarati' => '/[\x{0A80}-\x{0AFF}]/u',
        'gurmukhi' => '/[\x{0A00}-\x{0A7F}]/u',
        'georgian' => '/[\x{10A0}-\x{10FF}]/u',
        'armenian' => '/[\x{0530}-\x{058F}]/u',
    ];

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
            if (isset($criteria['language'])) {
                $filterDesc[] = "language:{$criteria['language']}";
            }
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

        // Apply language filter (preferred over siteId)
        if (isset($criteria['language']) && $criteria['language'] !== null) {
            $query->andWhere(['language' => $criteria['language']]);
        } elseif (isset($criteria['siteId']) && $criteria['siteId'] !== null) {
            // Legacy: filter by siteId
            $query->andWhere(['siteId' => $criteria['siteId']]);
        } elseif (!isset($criteria['allSites']) || !$criteria['allSites']) {
            // Default to current site's language if no specific filter and allSites not set
            $currentLanguage = Craft::$app->getSites()->getCurrentSite()->language;
            $query->andWhere(['language' => $currentLanguage]);
        }
        // If allSites is true, don't add any language/site filter

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

        // Apply category filter
        if (!empty($criteria['category']) && $criteria['category'] !== 'all') {
            $query->andWhere(['category' => $criteria['category']]);
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
     * Automatically skips text that is not in the source language
     */
    public function createOrUpdateTranslation(string $text, string $context = 'site', ?string $category = null): ?TranslationRecord
    {
        // Determine category: use provided, derive from context, or use primary category
        if ($category === null) {
            $category = $this->deriveCategoryFromContext($context);
        }

        // Check if text is in source language (skip non-source language text)
        if (!$this->isTextInSourceLanguage($text)) {
            $this->logDebug("Skipping non-source-language text", [
                'text' => mb_substr($text, 0, 50) . (mb_strlen($text) > 50 ? '...' : ''),
                'context' => $context,
                'category' => $category,
            ]);
            return null;
        }

        // First check if this text contains Twig component blocks with plain text
        if ($this->containsTwigCode($text)) {
            // Try to extract plain text from Twig component blocks
            $plainTexts = $this->extractPlainTextFromTwig($text);

            if (!empty($plainTexts)) {
                // Process each extracted plain text
                foreach ($plainTexts as $plainText) {
                    $this->createOrUpdateTranslation($plainText, $context, $category);
                }

                $this->logInfo("Extracted plain text from Twig code", [
                    'original' => $text,
                    'extracted' => $plainTexts,
                    'context' => $context,
                    'category' => $category,
                ]);
            } else {
                // No plain text found, skip entirely
                $this->logInfo("Skipping translation with Twig code (no plain text found)", [
                    'text' => $text,
                    'context' => $context,
                    'category' => $category,
                ]);
            }

            return null;
        }

        $hash = md5($text);

        // Multi-site support - create translations for ALL sites
        return $this->createOrUpdateMultiSiteTranslation($text, $hash, $context, $category);
    }

    /**
     * Derive translation category from context
     * - formie.* contexts use 'formie' category
     * - site.* contexts use the primary configured category
     */
    private function deriveCategoryFromContext(string $context): string
    {
        // Formie contexts always use 'formie' category
        if (str_starts_with($context, 'formie.') || $context === 'formie') {
            return 'formie';
        }

        // Site contexts use the primary configured category
        return TranslationManager::getInstance()->getSettings()->getPrimaryCategory();
    }
    
    /**
     * Create or update translations for all unique languages
     */
    private function createOrUpdateMultiSiteTranslation(string $text, string $hash, string $context, string $category): ?TranslationRecord
    {
        $languages = TranslationManager::getInstance()->getUniqueLanguages();
        $primaryTranslation = null;

        foreach ($languages as $language) {
            // Check if translation already exists for this language AND category
            $translation = TranslationRecord::findOne([
                'sourceHash' => $hash,
                'language' => $language,
                'category' => $category,
            ]);

            if (!$translation) {
                // Get a siteId for this language (for backwards compatibility)
                $siteId = $this->getSiteIdForLanguage($language);

                // Create new translation for this language
                $translation = new TranslationRecord([
                    'source' => $text,
                    'sourceHash' => $hash,
                    'context' => $context,
                    'category' => $category,
                    'siteId' => $siteId,
                    'language' => $language,
                    'translationKey' => $text,
                    'translation' => $this->getDefaultTranslationForLanguage($text, $language),
                    'status' => $this->getDefaultStatusForLanguage($language),
                    'usageCount' => 1,
                    'lastUsed' => new \DateTime(),
                    'dateCreated' => new \DateTime(),
                    'dateUpdated' => new \DateTime(),
                    'uid' => StringHelper::UUID(),
                ]);
                $translation->save();
                $this->logInfo("Created new translation for language", [
                    'text' => $text,
                    'language' => $language,
                    'category' => $category,
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
                        'language' => $language,
                        'category' => $category,
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
     * Get a site ID for a given language (for backwards compatibility)
     */
    private function getSiteIdForLanguage(string $language): int
    {
        $sites = Craft::$app->getSites()->getAllSites();
        foreach ($sites as $site) {
            if ($site->language === $language) {
                return $site->id;
            }
        }
        // Fallback to primary site
        return Craft::$app->getSites()->getPrimarySite()->id;
    }

    /**
     * Get default translation for a language
     */
    private function getDefaultTranslationForLanguage(string $text, string $language): ?string
    {
        // For source language, the source text is the translation
        if ($this->isSourceLanguage($language)) {
            return $text;
        }

        return null; // Other languages start as pending
    }

    /**
     * Get default status for a language
     */
    private function getDefaultStatusForLanguage(string $language): string
    {
        // Source language is automatically "translated"
        if ($this->isSourceLanguage($language)) {
            return 'translated';
        }

        return 'pending';
    }

    /**
     * Check if a language matches the configured source language
     */
    private function isSourceLanguage(string $language): bool
    {
        $sourceLanguage = TranslationManager::getInstance()->getSettings()->sourceLanguage;

        // Exact match
        if ($language === $sourceLanguage) {
            return true;
        }

        // Base language match (e.g., 'en-US' matches source 'en', or 'en' matches source 'en-US')
        $languageBase = explode('-', $language)[0];
        $sourceBase = explode('-', $sourceLanguage)[0];

        return $languageBase === $sourceBase;
    }

    /**
     * Scan all templates for translation usage and mark unused ones
     */
    public function scanTemplatesForUnused(): array
    {
        $results = [
            'scanned_files' => 0,
            'found_keys' => [],
            'marked_unused' => 0,
            'reactivated' => 0,
            'created' => 0,
            'errors' => [],
        ];

        try {
            // Get the configured translation categories (not formie)
            $settings = TranslationManager::getInstance()->getSettings();
            $categories = $settings->getEnabledCategories();

            // Scan all .twig files in templates directory
            $templatePath = Craft::$app->getPath()->getSiteTemplatesPath();

            // Add warning logs for debugging staging vs local differences
            $this->logInfo("Template scanner starting", ['categories' => $categories]);
            $this->logInfo("Template scanner path", ['path' => $templatePath]);

            // Scan for ALL enabled categories at once
            $foundKeysByCategory = $this->scanTemplateDirectoryAllCategories($templatePath, $categories);

            $results['scanned_files'] = $this->_scannedFileCount;

            // Flatten for results reporting
            $allFoundKeys = [];
            foreach ($foundKeysByCategory as $category => $keys) {
                foreach ($keys as $key => $data) {
                    $allFoundKeys[$key] = true;
                }
            }
            $results['found_keys'] = array_keys($allFoundKeys);

            $this->logInfo("Template scanner results", [
                'scanned_files' => $results['scanned_files'],
                'keys_found' => count($allFoundKeys),
                'by_category' => array_map('count', $foundKeysByCategory),
            ]);

            // Get all site translations (not formie) - filter by enabled categories
            $siteTranslations = (new Query())
                ->from(TranslationRecord::tableName())
                ->where(['like', 'context', 'site%', false])
                ->andWhere(['category' => $categories])
                ->all();

            // Create a map of existing translation keys by category for quick lookup
            $existingKeysByCategory = [];
            foreach ($siteTranslations as $translation) {
                $cat = $translation['category'];
                if (!isset($existingKeysByCategory[$cat])) {
                    $existingKeysByCategory[$cat] = [];
                }
                $existingKeysByCategory[$cat][$translation['translationKey']] = $translation;
            }

            // First pass: Create new translations found in templates but not in database
            foreach ($foundKeysByCategory as $category => $keys) {
                foreach ($keys as $key => $data) {
                    $existing = $existingKeysByCategory[$category][$key] ?? null;
                    if (!$existing) {
                        // New translation key found in templates - create database entry with correct category
                        $this->createOrUpdateTranslation($key, 'site', $category);
                        $results['created']++;
                        $this->logInfo("Template scanner: Created new translation", [
                            'key' => $key,
                            'category' => $category,
                        ]);
                    }
                }
            }

            // Second pass: Manage existing translations - check if still used
            foreach ($siteTranslations as $translation) {
                $key = $translation['translationKey'];
                $category = $translation['category'];
                $foundInCategory = isset($foundKeysByCategory[$category][$key]);

                if (!$foundInCategory) {
                    // Translation key not found in templates for this category
                    if ($translation['status'] !== 'unused') {
                        // Mark as unused
                        Db::update(TranslationRecord::tableName(),
                            ['status' => 'unused'],
                            ['id' => $translation['id']]
                        );
                        $results['marked_unused']++;
                        $this->logInfo("Template scanner: Marked as unused", [
                            'key' => $key,
                            'category' => $category,
                        ]);
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
                        $this->logWarning("Template scanner: Reactivated", [
                            'key' => $key,
                            'category' => $category,
                        ]);
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
     * Scan template directory for ALL enabled categories at once
     * Uses AST-based parsing for accurate translation detection
     *
     * Returns: ['category' => ['key' => ['file' => 'path']], ...]
     */
    public function scanTemplateDirectoryAllCategories(string $path, array $categories): array
    {
        $foundKeysByCategory = [];

        // Initialize categories
        foreach ($categories as $category) {
            $foundKeysByCategory[$category] = [];
        }

        if (!is_dir($path)) {
            return $foundKeysByCategory;
        }

        $this->logInfo('Starting AST-based template scan', ['categories' => $categories]);

        // Use AST-based scanner for accurate detection
        $scanResult = TemplateHelper::scanTemplates($categories);

        $this->_scannedFileCount = $scanResult['scannedFiles'];

        // Log any parsing errors (non-fatal)
        foreach ($scanResult['errors'] as $error) {
            $this->logWarning('Template parse warning', ['error' => $error]);
        }

        // Convert result format to match expected output
        foreach ($scanResult['found'] as $category => $keys) {
            foreach ($keys as $key => $data) {
                $foundKeysByCategory[$category][$key] = [
                    'file' => $data['file'],
                ];
            }
        }

        $this->logInfo('AST template scan complete', [
            'scannedFiles' => $this->_scannedFileCount,
            'foundKeys' => array_sum(array_map('count', $foundKeysByCategory)),
            'errors' => count($scanResult['errors']),
        ]);

        return $foundKeysByCategory;
    }

    /**
     * Recursively scan template directory for translation usage (single category - legacy)
     * Uses AST-based parsing for accurate translation detection
     *
     * @deprecated Use scanTemplateDirectoryAllCategories instead
     */
    public function scanTemplateDirectory(string $path, string $category): array
    {
        $foundKeys = [];

        if (!is_dir($path)) {
            return $foundKeys;
        }

        // Use the multi-category method and extract single category
        $result = $this->scanTemplateDirectoryAllCategories($path, [$category]);

        // Convert to simple key => true format
        if (isset($result[$category])) {
            foreach ($result[$category] as $key => $data) {
                $foundKeys[$key] = true;
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
                // IMPORTANT: Use RAW property, NOT getter - getter returns translated content
                if ($form->settings->submitActionMessage ?? false) {
                    $htmlMessage = $this->convertTipTapToHtml($form->settings->submitActionMessage);
                    if ($htmlMessage) {
                        // Store both HTML and plain text versions
                        $activeTexts[$htmlMessage] = true;
                        $plainMessage = $this->extractPlainTextFromFormie($htmlMessage);
                        if ($plainMessage && $plainMessage !== $htmlMessage) {
                            $activeTexts[$plainMessage] = true;
                        }
                        $this->logDebug("Form submit message (using raw property)", [
                            'form' => $form->handle,
                            'message' => substr($htmlMessage, 0, 80),
                            'length' => strlen($htmlMessage),
                        ]);
                    }
                }
                
                // Collect error message (both HTML and plain text versions)
                // IMPORTANT: Use RAW property, NOT getter - getter returns translated content
                if ($form->settings->errorMessage ?? false) {
                    $htmlMessage = $this->convertTipTapToHtml($form->settings->errorMessage);
                    if ($htmlMessage) {
                        // Store both HTML and plain text versions
                        $activeTexts[$htmlMessage] = true;
                        $plainMessage = $this->extractTextFromTipTap($htmlMessage);
                        if ($plainMessage && $plainMessage !== $htmlMessage) {
                            $activeTexts[$plainMessage] = true;
                        }
                        $this->logDebug("Form error message (using raw property)", [
                            'form' => $form->handle,
                            'message' => substr($htmlMessage, 0, 80),
                            'length' => strlen($htmlMessage),
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
     * Clear translations for a specific category
     */
    public function clearCategoryTranslations(string $category): int
    {
        $count = Db::delete(TranslationRecord::tableName(), [
            'category' => $category,
        ]);

        // Delete corresponding translation files
        if ($count > 0) {
            $this->deleteCategoryTranslationFiles($category);
        }

        $this->logInfo("Cleared category translations", ['category' => $category, 'count' => $count]);

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
     * Delete translation files for a specific category
     */
    private function deleteCategoryTranslationFiles(string $category): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $basePath = $settings->getExportPath();
        $filename = $category . '.php';

        // Get actual site languages dynamically
        $sites = TranslationManager::getInstance()->getAllowedSites();
        foreach ($sites as $site) {
            $file = $basePath . '/' . $site->language . '/' . $filename;
            if (file_exists($file)) {
                @unlink($file);
                $this->logInfo("Deleted category translation file", ['file' => $file, 'category' => $category]);
            }
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

                // Google Review integration messages
                if (property_exists($field, 'enableGoogleReview') && $field->enableGoogleReview) {
                    // Add message high (custom or default)
                    $messageHigh = $field->googleReviewMessageHigh ?: 'Thank you for the excellent rating! We would love if you could share your experience with others.';
                    $activeTexts[$messageHigh] = true;

                    // Add message medium (custom or default)
                    $messageMedium = $field->googleReviewMessageMedium ?: 'Thank you for your feedback!';
                    $activeTexts[$messageMedium] = true;

                    // Add message low (custom or default)
                    $messageLow = $field->googleReviewMessageLow ?: 'Thank you for your feedback. We will use it to improve our service.';
                    $activeTexts[$messageLow] = true;

                    // Add button label (custom or default)
                    $buttonLabel = $field->googleReviewButtonLabel ?: 'Review on Google';
                    $activeTexts[$buttonLabel] = true;
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
     * Convert TipTap JSON to HTML (same as FormieIntegration)
     */
    private function convertTipTapToHtml($content): string
    {
        if (empty($content)) {
            return '';
        }

        // If content is already an array (JSON-decoded), use it directly
        if (is_array($content)) {
            $data = $content;
        }
        // If it's a string, try to decode it
        elseif (is_string($content)) {
            // If it doesn't look like JSON, return as-is
            if ($content[0] !== '[' && $content[0] !== '{') {
                return $content;
            }

            try {
                $data = json_decode($content, true);
                if (!is_array($data)) {
                    return $content;
                }
            } catch (\Exception) {
                return $content;
            }
        } else {
            return (string)$content;
        }

        $htmlParts = [];

        // Convert TipTap blocks to HTML
        foreach ($data as $block) {
            if (isset($block['type']) && $block['type'] === 'paragraph') {
                $paragraphText = '';

                if (isset($block['content']) && is_array($block['content'])) {
                    foreach ($block['content'] as $textNode) {
                        if (isset($textNode['type']) && $textNode['type'] === 'text' && isset($textNode['text'])) {
                            $paragraphText .= $textNode['text'];
                        }
                    }
                }

                if (!empty(trim($paragraphText))) {
                    $htmlParts[] = '<p>' . htmlspecialchars($paragraphText) . '</p>';
                } else {
                    $htmlParts[] = '<p></p>';
                }
            }
        }

        return implode('', $htmlParts);
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

    // =========================================================================
    // Script Detection Methods (for source language filtering)
    // =========================================================================

    /**
     * Get the script family for a language code
     */
    private function getScriptForLanguage(string $languageCode): string
    {
        if (isset(self::LANGUAGE_SCRIPT_MAP[$languageCode])) {
            return self::LANGUAGE_SCRIPT_MAP[$languageCode];
        }

        $baseCode = explode('-', $languageCode)[0];
        if (isset(self::LANGUAGE_SCRIPT_MAP[$baseCode])) {
            return self::LANGUAGE_SCRIPT_MAP[$baseCode];
        }

        return 'latin';
    }

    /**
     * Check if text contains characters from a specific script
     */
    private function textContainsScript(string $text, string $script): bool
    {
        if (!isset(self::SCRIPT_PATTERNS[$script])) {
            return true;
        }

        return (bool) preg_match(self::SCRIPT_PATTERNS[$script], $text);
    }

    /**
     * Check if text appears to be in the source language (based on script)
     */
    private function isTextInSourceLanguage(string $text): bool
    {
        if (trim($text) === '') {
            return true;
        }

        $sourceLanguage = TranslationManager::getInstance()->getSettings()->sourceLanguage;
        $sourceScript = $this->getScriptForLanguage($sourceLanguage);

        if ($sourceScript === 'latin') {
            $nonLatinScripts = ['arabic', 'chinese', 'japanese', 'korean', 'cyrillic', 'hebrew',
                                'greek', 'thai', 'devanagari', 'bengali', 'tamil', 'telugu',
                                'kannada', 'malayalam', 'gujarati', 'gurmukhi', 'georgian', 'armenian', ];

            foreach ($nonLatinScripts as $script) {
                if ($this->textContainsScript($text, $script)) {
                    $this->logDebug("Text contains non-source script", [
                        'text' => mb_substr($text, 0, 50),
                        'detectedScript' => $script,
                    ]);
                    return false;
                }
            }

            return true;
        }

        if (!$this->textContainsScript($text, $sourceScript)) {
            $this->logDebug("Text does not contain source script", [
                'text' => mb_substr($text, 0, 50),
                'expectedScript' => $sourceScript,
            ]);
            return false;
        }

        return true;
    }
}
