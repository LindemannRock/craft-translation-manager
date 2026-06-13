<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Core service for managing translations
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\helpers\SiteLanguageHelper;
use lindemannrock\translationmanager\helpers\TemplateHelper;
use lindemannrock\translationmanager\models\Settings;
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
        $this->setLoggingHandle(TranslationManager::$plugin->id);
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
            if (isset($criteria['origin'])) {
                $filterDesc[] = "origin:{$criteria['origin']}";
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
            $currentLanguage = $settings->mapLanguage(Craft::$app->getSites()->getCurrentSite()->language);
            $query->andWhere(['language' => $currentLanguage]);
        }
        // If allSites is true, don't add any language/site filter

        // Hide mapped source locales globally (e.g., en-US when mapped to en).
        $mappedSourceLocales = array_keys($settings->getActiveLocaleMapping());
        if (!empty($mappedSourceLocales)) {
            $query->andWhere(['not in', 'language', $mappedSourceLocales]);
        }

        // Apply status filter
        if (!empty($criteria['status']) && $criteria['status'] !== 'all') {
            $query->andWhere(['status' => $criteria['status']]);
        }

        // Apply type filter
        $hasTypeFilter = false;
        if (!empty($criteria['type']) && $criteria['type'] !== 'all') {
            if ($criteria['type'] === 'forms') {
                $condition = $this->buildSourceTypeContextCondition('forms');
                if ($condition === null) {
                    return [];
                }
                $query->andWhere($condition);
                $hasTypeFilter = true;
            } elseif ($criteria['type'] === 'site') {
                $condition = $this->buildNonIntegrationContextCondition();
                if ($condition !== null) {
                    $query->andWhere($condition);
                }
                $hasTypeFilter = true;
            }
        }
        
        // Only apply integration filters if no type filter was applied
        if (!$hasTypeFilter) {
            if (!$settings->enableSiteTranslations) {
                $condition = $this->buildEnabledIntegrationContextCondition();
                if ($condition === null) {
                    return [];
                }
                $query->andWhere($condition);
            } else {
                $disabledCondition = $this->buildDisabledIntegrationContextCondition();
                if ($disabledCondition !== null) {
                    $query->andWhere(['not', $disabledCondition]);
                }
            }
        }

        // Apply category filter
        if (!empty($criteria['category']) && $criteria['category'] !== 'all') {
            $query->andWhere(['category' => $criteria['category']]);
        }

        // Apply origin filter
        if (!empty($criteria['origin']) && $criteria['origin'] !== 'all') {
            $query->andWhere(['translationOrigin' => $criteria['origin']]);
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
            'category' => 'category',
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

        // Get all translations. Integration rows maintain their "unused"
        // status through provider save hooks and maintenance rescans, so this
        // read path does not traverse forms on every request.
        return $query->all();
    }

    /**
     * Get the integration registry.
     */
    private function getIntegrationService(): IntegrationService
    {
        /** @var IntegrationService $service */
        $service = TranslationManager::getInstance()->get('integrations');

        return $service;
    }

    /**
     * Build a context condition for all registered integrations in a source type.
     *
     * @return array<int, mixed>|null
     */
    private function buildSourceTypeContextCondition(string $sourceType): ?array
    {
        return $this->buildContextPrefixCondition(
            $this->getIntegrationService()->getContextPrefixes($sourceType),
        );
    }

    /**
     * Build a context condition for all enabled and available integrations.
     *
     * @return array<int, mixed>|null
     */
    private function buildEnabledIntegrationContextCondition(): ?array
    {
        return $this->buildContextPrefixCondition(
            $this->getIntegrationService()->getIntegrationContextPrefixes(true),
        );
    }

    /**
     * Build a context condition for registered integrations disabled by settings.
     *
     * @return array<int, mixed>|null
     */
    private function buildDisabledIntegrationContextCondition(): ?array
    {
        $service = $this->getIntegrationService();
        $prefixes = [];

        foreach ($service->getAll() as $integration) {
            if (!$service->isIntegrationEnabled($integration->getName())) {
                $prefixes[] = $integration->getContextPrefix();
            }
        }

        return $this->buildContextPrefixCondition($prefixes);
    }

    /**
     * Build a condition matching rows that do not belong to any integration.
     *
     * @return array<int, mixed>|null
     */
    private function buildNonIntegrationContextCondition(): ?array
    {
        $condition = $this->buildContextPrefixCondition(
            $this->getIntegrationService()->getIntegrationContextPrefixes(),
        );

        return $condition === null ? null : ['not', $condition];
    }

    /**
     * Build a Yii condition matching exact context prefixes and dotted children.
     *
     * @param string[] $prefixes
     * @return array<int, mixed>|null
     */
    private function buildContextPrefixCondition(array $prefixes): ?array
    {
        $conditions = [];

        foreach (array_values(array_unique(array_filter($prefixes))) as $prefix) {
            $conditions[] = ['like', 'context', $prefix . '.%', false];
            $conditions[] = ['=', 'context', $prefix];
        }

        if ($conditions === []) {
            return null;
        }

        return array_merge(['or'], $conditions);
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
        
        // Keep system states untouched unless explicitly changed elsewhere.
        if (!in_array($translation->status, ['unused', 'draft'], true)) {
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
     * - integration contexts use their integration category
     * - site.* contexts use the primary configured category
     */
    private function deriveCategoryFromContext(string $context): string
    {
        $integrationCategory = $this->getIntegrationService()->getCategoryForContext($context);
        if ($integrationCategory !== null) {
            return $integrationCategory;
        }

        // Site contexts use the primary configured category
        return TranslationManager::getInstance()->getSettings()->getPrimaryCategory();
    }
    
    /**
     * Create or update translations for all unique languages.
     *
     * Batched to keep the hot capture path cheap: one SELECT to load
     * existing rows for every language at once, one batch INSERT for
     * any newly-needed rows, one bulk UPDATE for counter/lastUsed/
     * context across all existing rows, and one CASE-based UPDATE for
     * `unused` reactivation. ~3-4 queries per call instead of N×2.
     *
     * Behaviors preserved from the per-row implementation:
     * - `translation`, `translationOrigin`, `createdByUserId`,
     *   `reviewedByUserId`, `reviewedAt` are never overwritten on
     *   existing rows (manual / import / AI translations stay intact).
     * - Reactivation only fires for rows whose status is currently
     *   `unused`. Rule: non-empty `translation` → `translated`,
     *   else → `pending`.
     */
    private function createOrUpdateMultiSiteTranslation(string $text, string $hash, string $context, string $category): ?TranslationRecord
    {
        $languages = TranslationManager::getInstance()->getUniqueLanguages();
        if (!$languages) {
            return null;
        }

        $now = Db::prepareDateForDb(new \DateTime());

        // 1. Single SELECT for every language in this category.
        /** @var TranslationRecord[] $existing */
        $existing = TranslationRecord::find()
            ->where([
                'sourceHash' => $hash,
                'category' => $category,
                'language' => $languages,
            ])
            ->indexBy('language')
            ->all();

        $existingIds = array_map(
            static fn(TranslationRecord $r): int => (int) $r->id,
            array_values($existing),
        );

        // 2. Collect rows for any languages we don't have yet.
        // Pre-build language => siteId map so we don't iterate all
        // sites once per missing language. First-match-wins to mirror
        // the old per-language helper's behavior in multi-site setups
        // where two sites share the same language code.
        $siteIdByLanguage = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            if (!isset($siteIdByLanguage[$site->language])) {
                $siteIdByLanguage[$site->language] = $site->id;
            }
        }
        $fallbackSiteId = Craft::$app->getSites()->getPrimarySite()->id;

        $newRows = [];
        foreach ($languages as $language) {
            if (isset($existing[$language])) {
                continue;
            }
            $newRows[] = [
                'source' => $text,
                'sourceHash' => $hash,
                'context' => $context,
                'category' => $category,
                'siteId' => $siteIdByLanguage[$language] ?? $fallbackSiteId,
                'language' => $language,
                'translationKey' => $text,
                'translation' => $this->getDefaultTranslationForLanguage($text, $language),
                'status' => $this->getDefaultStatusForLanguage($language),
                'translationOrigin' => 'system',
                'usageCount' => 1,
                'lastUsed' => $now,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => StringHelper::UUID(),
            ];
        }

        $createdCount = 0;
        if ($newRows) {
            Craft::$app->getDb()->createCommand()
                ->batchInsert(TranslationRecord::tableName(), array_keys($newRows[0]), $newRows)
                ->execute();
            $createdCount = count($newRows);
        }

        // 3. Bulk increment + refresh on any pre-existing rows.
        $updatedCount = 0;
        $reactivatedCount = 0;
        if ($existingIds) {
            $updatedCount = TranslationRecord::updateAll([
                'usageCount' => new \yii\db\Expression('[[usageCount]] + 1'),
                'lastUsed' => $now,
                'context' => $context,
                'dateUpdated' => $now,
            ], ['id' => $existingIds]);

            // 4. Reactivation in one query — CASE picks the right destination
            // status based on whether the row already has a translation.
            $reactivatedCount = TranslationRecord::updateAll([
                'status' => new \yii\db\Expression(
                    "CASE WHEN [[translation]] IS NULL OR [[translation]] = '' THEN 'pending' ELSE 'translated' END"
                ),
            ], ['id' => $existingIds, 'status' => 'unused']);
        }

        $this->logInfo('Captured multi-site translation', [
            'text' => $text,
            'category' => $category,
            'created' => $createdCount,
            'updated' => $updatedCount,
            'reactivated' => $reactivatedCount,
        ]);

        // Always refetch the primary row — any AR objects in $existing are
        // stale after the bulk UPDATEs above (usageCount/lastUsed/context/
        // dateUpdated/status no longer reflect DB state).
        return TranslationRecord::findOne([
            'sourceHash' => $hash,
            'language' => $languages[0],
            'category' => $category,
        ]);
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
            // Get the configured site translation categories
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

            // Get all site translations - filter by enabled categories
            // Include both 'site' context and 'runtime' context (from auto-capture)
            $siteTranslations = (new Query())
                ->from(TranslationRecord::tableName())
                ->where([
                    'or',
                    ['like', 'context', 'site%', false],
                    ['context' => 'runtime'],
                ])
                ->andWhere(['category' => $categories])
                ->all();

            // Create a map of existing translation keys by category for quick lookup
            // Group by category -> key -> array of translations (to handle multiple languages)
            $existingKeysByCategory = [];
            foreach ($siteTranslations as $translation) {
                $cat = $translation['category'];
                $key = $translation['translationKey'];
                if (!isset($existingKeysByCategory[$cat])) {
                    $existingKeysByCategory[$cat] = [];
                }
                if (!isset($existingKeysByCategory[$cat][$key])) {
                    $existingKeysByCategory[$cat][$key] = [];
                }
                $existingKeysByCategory[$cat][$key][] = $translation;
            }

            // First pass: Create new translations found in templates but not in database
            foreach ($foundKeysByCategory as $category => $keys) {
                foreach ($keys as $key => $data) {
                    $existingList = $existingKeysByCategory[$category][$key] ?? [];
                    if (empty($existingList)) {
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
            // Process all translations (including all language variants)
            $markUnusedIds = [];
            $reactivateTranslatedIds = [];
            $reactivatePendingIds = [];
            $contextUpdateIdsByContext = [];

            foreach ($siteTranslations as $translation) {
                $key = $translation['translationKey'];
                $category = $translation['category'];
                $foundInCategory = isset($foundKeysByCategory[$category][$key]);

                if (!$foundInCategory) {
                    // Translation key not found in templates for this category
                    if ($translation['status'] !== 'unused') {
                        // Mark as unused
                        $markUnusedIds[] = (int)$translation['id'];
                        $results['marked_unused']++;
                        $this->logInfo("Template scanner: Marked as unused", [
                            'key' => $key,
                            'category' => $category,
                            'language' => $translation['language'] ?? 'unknown',
                        ]);
                    }
                } else {
                    // Translation key found in templates
                    $updates = [];

                    // Reactivate if unused
                    if ($translation['status'] === 'unused') {
                        if ($translation['translation']) {
                            $reactivateTranslatedIds[] = (int)$translation['id'];
                        } else {
                            $reactivatePendingIds[] = (int)$translation['id'];
                        }
                        $results['reactivated']++;
                        $this->logWarning("Template scanner: Reactivated", [
                            'key' => $key,
                            'category' => $category,
                            'language' => $translation['language'] ?? 'unknown',
                        ]);
                    }

                    // Update context from 'runtime' to actual file path
                    if ($translation['context'] === 'runtime') {
                        $fileInfo = $foundKeysByCategory[$category][$key] ?? null;
                        if ($fileInfo && isset($fileInfo['file'])) {
                            $updates['context'] = 'site.' . $fileInfo['file'];
                            $contextUpdateIdsByContext[$updates['context']][] = (int)$translation['id'];
                            $this->logInfo("Template scanner: Updated runtime context", [
                                'key' => $key,
                                'language' => $translation['language'] ?? 'unknown',
                                'newContext' => $updates['context'],
                            ]);
                        }
                    }

                    // Apply updates if any
                    if (!empty($updates)) {
                        // Writes are applied in grouped batches after this loop.
                    }
                }
            }

            $table = TranslationRecord::tableName();
            if ($markUnusedIds) {
                Db::update($table, ['status' => 'unused'], ['id' => $markUnusedIds]);
            }
            if ($reactivateTranslatedIds) {
                Db::update($table, ['status' => 'translated'], ['id' => $reactivateTranslatedIds]);
            }
            if ($reactivatePendingIds) {
                Db::update($table, ['status' => 'pending'], ['id' => $reactivatePendingIds]);
            }
            foreach ($contextUpdateIdsByContext as $context => $ids) {
                Db::update($table, ['context' => $context], ['id' => $ids]);
            }
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            $this->logError('Template scanning failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }
    
    /**
     * @var int Count of template files scanned during the most recent template scan.
     */
    private int $_scannedFileCount = 0;

    /**
     * Number of template files scanned during the most recent scan
     * (`scanTemplateDirectory()` / `scanTemplateDirectoryAllCategories()`).
     *
     * @since 5.25.0
     */
    public function getScannedFileCount(): int
    {
        return $this->_scannedFileCount;
    }

    /**
     * Scan template directory for ALL enabled categories at once
     * Uses AST-based parsing for accurate translation detection
     *
     * Returns: ['category' => ['key' => ['file' => 'path']], ...]
     *
     * @since 5.17.0
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
     * Import parsed PHP translation entries into the database for a given
     * language + category. Shared by the CP PHP import and the console
     * `translations/import` command so the two cannot drift.
     *
     * Each entry is `['key' => sourceString, 'value' => translatedText]`.
     * Records are created/updated for every unique site language (like a scan):
     * the import language gets the value, the source language gets the key as
     * its own translation, other languages get an empty pending row.
     *
     * @param array<int, array{key?: string, value?: string}> $entries
     * @param string $importLanguage The language the file's values belong to
     * @param string $category Translation category (e.g. 'formie', 'messages')
     * @param int|null $userId User to attribute the import to (null from console)
     * @return array{imported: int, updated: int, errors: string[]}
     * @since 5.25.0
     */
    public function importPhpEntries(array $entries, string $importLanguage, string $category, ?int $userId = null): array
    {
        // Create/update records for ALL languages (like scan does).
        $allLanguages = TranslationManager::getInstance()->getUniqueLanguages();
        $sourceHashes = [];

        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($entries as $item) {
            $key = $item['key'] ?? '';
            if ($key === '') {
                continue;
            }

            $sourceHashes[] = md5($key);
        }

        /** @var array<string,TranslationRecord> $existingRecords */
        $existingRecords = [];
        $sourceHashes = array_values(array_unique($sourceHashes));
        if ($sourceHashes !== [] && $allLanguages !== []) {
            /** @var TranslationRecord[] $records */
            $records = TranslationRecord::find()
                ->where([
                    'sourceHash' => $sourceHashes,
                    'language' => $allLanguages,
                    'category' => $category,
                ])
                ->all();

            foreach ($records as $record) {
                $existingRecords[$this->phpImportLookupKey($record->sourceHash, (string)$record->language)] = $record;
            }
        }

        foreach ($entries as $item) {
            try {
                $key = $item['key'] ?? '';
                $value = $item['value'] ?? '';

                if (empty($key)) {
                    continue;
                }

                $sourceHash = md5($key);

                foreach ($allLanguages as $language) {
                    $lookupKey = $this->phpImportLookupKey($sourceHash, $language);
                    $record = $existingRecords[$lookupKey] ?? null;

                    $isImportLanguage = ($language === $importLanguage);
                    $isSourceLang = $this->isSourceLanguage($language);

                    if ($record instanceof TranslationRecord) {
                        // Existing row: only the import language gets the value.
                        if ($isImportLanguage) {
                            $record->translation = $value;
                            $record->status = !empty($value) ? 'translated' : 'pending';
                            $record->translationOrigin = 'import';
                            // Preserve original creator when running without a user (console).
                            if ($userId !== null) {
                                $record->createdByUserId = $userId;
                            }
                            if (!empty($value)) {
                                $record->reviewedByUserId = $userId;
                                $record->reviewedAt = Db::prepareDateForDb(new \DateTime());
                            } else {
                                $record->reviewedByUserId = null;
                                $record->reviewedAt = null;
                            }
                            $record->dateUpdated = Db::prepareDateForDb(new \DateTime());

                            if ($record->save()) {
                                $updated++;
                                $existingRecords[$lookupKey] = $record;
                            } else {
                                $errors[] = "Failed to update '{$key}' ({$language}): " . json_encode($record->getErrors());
                                $record->refresh();
                            }
                        }
                        // Other languages: record exists, don't touch it.
                    } else {
                        // Create a new record for this language.
                        $record = new TranslationRecord();
                        $record->source = $key;
                        $record->sourceHash = $sourceHash;
                        $record->translationKey = $key;
                        $record->language = $language;
                        $record->category = $category;
                        $integrationPrefix = $this->getIntegrationService()->getContextPrefixForCategory($category);
                        $record->context = $integrationPrefix === null ? 'site.php-import' : $integrationPrefix . '.php-import';
                        $record->siteId = SiteLanguageHelper::getSiteIdForLanguage($language);
                        $record->usageCount = 1;
                        $record->dateCreated = Db::prepareDateForDb(new \DateTime());
                        $record->dateUpdated = Db::prepareDateForDb(new \DateTime());
                        $record->uid = StringHelper::UUID();

                        if ($isImportLanguage) {
                            // This is the language being imported - use the value.
                            $record->translation = $value;
                            $record->status = !empty($value) ? 'translated' : 'pending';
                            $record->translationOrigin = 'import';
                            if ($userId !== null) {
                                $record->createdByUserId = $userId;
                            }
                            if (!empty($value)) {
                                $record->reviewedByUserId = $userId;
                                $record->reviewedAt = Db::prepareDateForDb(new \DateTime());
                            }
                        } elseif ($isSourceLang) {
                            // Source language: key is the translation.
                            $record->translation = $key;
                            $record->status = 'translated';
                            $record->translationOrigin = 'system';
                        } else {
                            // Other languages: empty translation, pending.
                            $record->translation = '';
                            $record->status = 'pending';
                            $record->translationOrigin = 'system';
                        }

                        if ($record->save()) {
                            $existingRecords[$lookupKey] = $record;
                            // Only count the import language for the "imported" count.
                            if ($isImportLanguage) {
                                $imported++;
                            }
                        } else {
                            $errors[] = "Failed to create '{$key}' ({$language}): " . json_encode($record->getErrors());
                        }
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "Error processing: " . ($item['key'] ?? 'unknown') . " - " . $e->getMessage();
            }
        }

        $this->logInfo('PHP import completed', [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => count($errors),
            'language' => $importLanguage,
            'category' => $category,
            'languages_created' => $allLanguages,
        ]);

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    private function phpImportLookupKey(string $sourceHash, string $language): string
    {
        return $sourceHash . "\n" . $language;
    }

    /**
     * Resolve the configuration status for an import category: whether it is a
     * valid, enabled, importable category, and whether it can be auto-registered.
     *
     * @return array<string, bool|string>
     * @since 5.25.0
     */
    public function getImportCategoryStatus(string $category): array
    {
        $category = trim($category);

        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $category)) {
            return [
                'error' => Craft::t('translation-manager', 'Category "{category}" must start with a letter and contain only letters, numbers, hyphens, and underscores.', [
                    'category' => $category,
                ]),
            ];
        }

        if (in_array(strtolower($category), Settings::RESERVED_CATEGORIES, true)) {
            return [
                'error' => Craft::t('translation-manager', 'Category "{category}" is reserved and cannot be imported.', [
                    'category' => $category,
                ]),
            ];
        }

        $settings = TranslationManager::getInstance()->getSettings();
        $enabledCategories = $settings->getEnabledCategories();
        $isConfigured = in_array($category, $enabledCategories, true)
            || $this->getIntegrationService()->isIntegrationCategoryEnabled($category);

        if ($isConfigured) {
            return [
                'requiresRegistration' => false,
                'canAutoRegister' => false,
            ];
        }

        $canAutoRegister = !$settings->isOverriddenByConfig('translationCategories');

        return [
            'requiresRegistration' => true,
            'canAutoRegister' => $canAutoRegister,
            'message' => Craft::t('translation-manager', $canAutoRegister
                ? 'Category "{category}" is not enabled. It will be added to Translation Categories when imported.'
                : 'Category "{category}" is not enabled and cannot be added automatically because Translation Categories are configured in config/translation-manager.php.', [
                    'category' => $category,
                ]),
        ];
    }

    /**
     * Add or enable a translation category before importing into it.
     *
     * @since 5.25.0
     */
    public function registerImportCategory(string $category): bool
    {
        $settings = Settings::loadFromDatabase();

        if ($settings->isOverriddenByConfig('translationCategories')) {
            return false;
        }

        $categories = $settings->translationCategories;
        if (empty($categories)) {
            $categories[] = [
                'key' => $settings->translationCategory ?: 'messages',
                'enabled' => true,
            ];
        }
        $found = false;

        foreach ($categories as &$categoryConfig) {
            if (($categoryConfig['key'] ?? null) === $category) {
                $categoryConfig['enabled'] = true;
                $found = true;
                break;
            }
        }
        unset($categoryConfig);

        if (!$found) {
            $categories[] = [
                'key' => $category,
                'enabled' => true,
            ];
        }

        $settings->translationCategories = $categories;

        if (!$settings->saveToDatabase(['translationCategories'])) {
            return false;
        }

        TranslationManager::getInstance()->setSettings([]);

        return true;
    }

    /**
     * Recompute "unused" status for form-provider translations and persist
     * the result with batched UPDATEs.
     *
     * Called synchronously by form integrations on save and by the maintenance
     * "Rescan all forms" action — never on the read path.
     * Handles both directions: marks newly-orphaned strings unused, and
     * restores the appropriate non-unused status when a previously
     * orphaned string becomes active again.
     *
     * @return array{checked:int,markedUnused:int,markedTranslated:int,markedPending:int}
     * @since 5.24.0
     */
    public function recheckUsage(): array
    {
        $this->logInfo('Starting usage recheck');

        $activeTexts = $this->buildActiveTextsMap();

        $rows = (new Query())
            ->select(['id', 'context', 'translationKey', 'translation', 'status'])
            ->from(TranslationRecord::tableName())
            ->where($this->buildSourceTypeContextCondition('forms') ?? '0=1')
            ->all();

        $shouldBeUnused = [];
        $shouldBeTranslated = [];
        $shouldBePending = [];

        foreach ($rows as $row) {
            // formie.defaults.* are validation messages — always considered used
            $isDefault = str_starts_with($row['context'], 'formie.defaults.');
            $isActive = $isDefault || isset($activeTexts[$row['translationKey']]);

            $currentStatus = (string) $row['status'];

            if (!$isActive && $currentStatus !== 'unused') {
                $shouldBeUnused[] = (int) $row['id'];
            } elseif ($isActive && $currentStatus === 'unused') {
                if ((string) $row['translation'] !== '') {
                    $shouldBeTranslated[] = (int) $row['id'];
                } else {
                    $shouldBePending[] = (int) $row['id'];
                }
            }
        }

        $result = [
            'checked' => count($rows),
            'markedUnused' => 0,
            'markedTranslated' => 0,
            'markedPending' => 0,
        ];

        $table = TranslationRecord::tableName();

        if ($shouldBeUnused) {
            $result['markedUnused'] = Db::update($table, ['status' => 'unused'], ['id' => $shouldBeUnused]);
        }
        if ($shouldBeTranslated) {
            $result['markedTranslated'] = Db::update($table, ['status' => 'translated'], ['id' => $shouldBeTranslated]);
        }
        if ($shouldBePending) {
            $result['markedPending'] = Db::update($table, ['status' => 'pending'], ['id' => $shouldBePending]);
        }

        $this->logInfo('Usage recheck finished', $result);

        return $result;
    }

    /**
     * Build the set of currently-active text strings across every enabled form
     * provider.
     *
     * @return array<string,true> Map keyed by translation source string
     */
    private function buildActiveTextsMap(): array
    {
        $activeTexts = [];
        $integrations = $this->getIntegrationService()->getIntegrationsBySourceType('forms', true);

        foreach ($integrations as $integration) {
            try {
                $activeTexts += $integration->getActiveTranslationTexts();
            } catch (\Throwable $e) {
                $this->logInfo('Unable to collect active integration texts', [
                    'integration' => $integration->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $activeTexts;
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
        $draft = $counts['draft'] ?? 0;
        $translated = $counts['translated'] ?? 0;
        $unused = $counts['unused'] ?? 0;
        

        // Get type counts with same site filter
        $formsCount = (new Query())
            ->from(TranslationRecord::tableName())
            ->andWhere($siteId ? ['siteId' => $siteId] : [])
            ->andWhere($this->buildSourceTypeContextCondition('forms') ?? '0=1')
            ->count();
        
        $siteQuery = (new Query())
            ->from(TranslationRecord::tableName())
            ->andWhere($siteId ? ['siteId' => $siteId] : []);

        $nonIntegrationCondition = $this->buildNonIntegrationContextCondition();
        if ($nonIntegrationCondition !== null) {
            $siteQuery->andWhere($nonIntegrationCondition);
        }

        $siteCount = $siteQuery->count();

        $stats = [
            'total' => $total,
            'pending' => $pending,
            'draft' => $draft,
            'translated' => $translated,
            'unused' => $unused,
            'formie' => $this->countTranslationsForContextPrefixes(['formie'], $siteId),
            'forms' => $formsCount,
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
     * Count translations for context prefixes.
     *
     * @param string[] $prefixes
     */
    private function countTranslationsForContextPrefixes(array $prefixes, ?int $siteId = null): int
    {
        return (int) (new Query())
            ->from(TranslationRecord::tableName())
            ->andWhere($siteId ? ['siteId' => $siteId] : [])
            ->andWhere($this->buildContextPrefixCondition($prefixes) ?? '0=1')
            ->count();
    }
    
    /**
     * Clear all Formie translations
     */
    public function clearFormieTranslations(): int
    {
        return $this->clearProviderTranslations('formie');
    }

    /**
     * Clear all translations for one integration provider.
     */
    public function clearProviderTranslations(string $provider): int
    {
        $integration = $this->getIntegrationService()->get($provider);
        if ($integration === null) {
            return 0;
        }

        $contextPrefix = $integration->getContextPrefix();
        $category = $integration->getCategory();
        $count = Db::delete(
            TranslationRecord::tableName(),
            $this->buildContextPrefixCondition([$contextPrefix]) ?? '0=1',
        );
        
        // Delete corresponding translation files
        if ($count > 0) {
            $this->deleteCategoryTranslationFiles($category);
        }
        
        $this->logInfo('Cleared provider translations', [
            'provider' => $provider,
            'category' => $category,
            'count' => $count,
        ]);
        
        return $count;
    }
    
    /**
     * Clear all site translations
     */
    public function clearSiteTranslations(): int
    {
        $count = Db::delete(
            TranslationRecord::tableName(),
            $this->buildNonIntegrationContextCondition() ?? [],
        );
        
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
            foreach ($this->getIntegrationService()->getAll() as $integration) {
                $this->deleteCategoryTranslationFiles($integration->getCategory());
            }
            $this->deleteSiteTranslationFiles();
        }
        
        $this->logInfo("Cleared ALL translations", ['count' => $count]);

        return $count;
    }

    /**
     * Clear translations for a specific category
     *
     * @since 5.0.0
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
     * Delete site translation files for all enabled categories and all site languages
     */
    private function deleteSiteTranslationFiles(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $basePath = $settings->getGenerationPath();

        // Get all enabled categories
        $categories = $settings->getEnabledCategories();

        // Get actual site languages dynamically
        $sites = TranslationManager::getInstance()->getAllowedSites();

        foreach ($sites as $site) {
            foreach ($categories as $category) {
                $file = $basePath . '/' . $site->language . '/' . $category . '.php';
                if (file_exists($file)) {
                    @unlink($file);
                    $this->logInfo("Deleted site translation file", ['file' => $file, 'category' => $category]);
                }
            }
        }
    }

    /**
     * Delete translation files for a specific category
     */
    private function deleteCategoryTranslationFiles(string $category): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $basePath = $settings->getGenerationPath();
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
     *
     * @since 5.14.0
     */
    public function applySkipPatternsToExisting(): int
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $patterns = array_filter(array_map('trim', $settings->skipPatterns ?? []));

        if (!$patterns) {
            $this->logInfo('applySkipPatternsToExisting: no skip patterns configured');
            return 0;
        }

        // allSites: true so we scan every language, not just the CP user's
        // current site. Without this the cleanup would silently miss matches
        // in other languages.
        $siteTranslations = $this->getTranslations(['type' => 'site', 'allSites' => true]);

        $idsToDelete = [];
        foreach ($siteTranslations as $translation) {
            foreach ($patterns as $pattern) {
                if (str_contains($translation['translationKey'], $pattern)) {
                    $idsToDelete[] = (int) $translation['id'];
                    break; // matched — stop checking other patterns for this row
                }
            }
        }

        $deleted = 0;
        if ($idsToDelete) {
            $deleted = TranslationRecord::deleteAll(['id' => $idsToDelete]);
        }

        $this->logInfo('Applied skip patterns to existing translations', [
            'patterns' => array_values($patterns),
            'scanned' => count($siteTranslations),
            'deleted' => $deleted,
        ]);

        return $deleted;
    }
    
    /**
     * Get count of unused translations (forms that no longer exist)
     */
    public function getUnusedTranslationCount(): int
    {
        return (int) TranslationRecord::find()
            ->where($this->buildSourceTypeContextCondition('forms') ?? '0=1')
            ->andWhere(['status' => 'unused'])
            ->count();
    }
    
    /**
     * Clean up unused translations and regenerate files
     */
    public function cleanUnusedTranslations(): int
    {
        $deleted = TranslationRecord::deleteAll([
            'and',
            $this->buildSourceTypeContextCondition('forms') ?? '0=1',
            ['status' => 'unused'],
        ]);
        
        // If we deleted any translations, regenerate enabled integration files.
        if ($deleted > 0) {
            $this->logInfo("Cleaned up unused translations", ['deleted' => $deleted]);
            
            $generationService = TranslationManager::getInstance()->generate;
            $generationService->generateIntegrationTranslations('forms');

            $this->logInfo('Regenerated form integration translation files after cleanup');
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
