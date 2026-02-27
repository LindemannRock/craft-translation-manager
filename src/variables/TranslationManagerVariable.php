<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Template variable for accessing translation functions
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\variables;

use lindemannrock\translationmanager\TranslationManager;

/**
 * Translation Manager Variable
 *
 * Provides template access to translation functions
 *
 * @since 1.0.0
 */
class TranslationManagerVariable
{
    /**
     * Translate text
     *
     * Usage: {{ craft.translationManager.t('Text to translate', 'context') }}
     */
    public function t(string $text, string $context = ''): string
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $category = $settings->translationCategory;
        
        // If no context provided, use the category
        if (empty($context)) {
            $context = "site.{$category}";
        } elseif (!str_starts_with($context, 'site.')) {
            $context = "site.{$context}";
        }
        
        // Create or update the translation
        $translation = TranslationManager::getInstance()->translations->createOrUpdateTranslation($text, $context);

        // Get current site language
        $currentSite = \Craft::$app->getSites()->getCurrentSite();
        $sourceLanguage = $settings->sourceLanguage ?? 'en';

        // Return translated text if available and we're not on the source language
        if ($currentSite->language !== $sourceLanguage &&
            !str_starts_with($currentSite->language, $sourceLanguage . '-') &&
            !empty($translation->translation)) {
            return $translation->translation;
        }

        return $text;
    }
    
    /**
     * Get translation statistics
     */
    public function getStats(): array
    {
        return TranslationManager::getInstance()->translations->getStatistics();
    }
    
    /**
     * Get allowed sites for current license
     */
    public function getAllowedSites(): array
    {
        return TranslationManager::getInstance()->getAllowedSites();
    }
    
    /**
     * Check if a translation exists
     */
    public function hasTranslation(string $text, string $context = ''): bool
    {
        $hash = md5($text);
        $record = \lindemannrock\translationmanager\records\TranslationRecord::findOne([
            'sourceHash' => $hash,
            'context' => $context ?: 'site',
        ]);
        
        return $record !== null && !empty($record->translation);
    }
    
    /**
     * Get unused translation counts by type and category (for maintenance dropdown)
     */
    public function getUnusedTranslationCounts(): array
    {
        $query = new \craft\db\Query();

        // Get formie count
        $formieCount = $query->from('{{%translationmanager_translations}}')
            ->where(['status' => 'unused'])
            ->andWhere(['or',
                ['like', 'context', 'formie.%', false],
                ['=', 'context', 'formie'],
            ])
            ->count();

        // Get per-category counts for site translations
        $categoryCounts = (new \craft\db\Query())
            ->select(['category', 'COUNT(*) as count'])
            ->from('{{%translationmanager_translations}}')
            ->where(['status' => 'unused'])
            ->andWhere(['not', ['or',
                ['like', 'context', 'formie.%', false],
                ['=', 'context', 'formie'],
            ]])
            ->groupBy(['category'])
            ->all();

        $result = [
            'formie' => (int) $formieCount,
        ];

        $siteTotal = 0;
        foreach ($categoryCounts as $row) {
            $category = $row['category'] ?? 'messages';
            $count = (int) $row['count'];
            $result[$category] = $count;
            $siteTotal += $count;
        }

        // Keep 'site' for backwards compatibility (sum of all non-formie)
        $result['site'] = $siteTotal;
        $result['total'] = $siteTotal + (int) $formieCount;

        return $result;
    }
    
    /**
     * Get total translation counts by type and category (for clear translations dropdown)
     */
    public function getTranslationCounts(): array
    {
        $query = new \craft\db\Query();

        // Get formie count
        $formieCount = $query->from('{{%translationmanager_translations}}')
            ->where(['or',
                ['like', 'context', 'formie.%', false],
                ['=', 'context', 'formie'],
            ])
            ->count();

        // Get per-category counts for site translations
        $categoryCounts = (new \craft\db\Query())
            ->select(['category', 'COUNT(*) as count'])
            ->from('{{%translationmanager_translations}}')
            ->where(['not', ['or',
                ['like', 'context', 'formie.%', false],
                ['=', 'context', 'formie'],
            ]])
            ->groupBy(['category'])
            ->all();

        $result = [
            'formie' => (int) $formieCount,
        ];

        $siteTotal = 0;
        foreach ($categoryCounts as $row) {
            $category = $row['category'] ?? 'messages';
            $count = (int) $row['count'];
            $result[$category] = $count;
            $siteTotal += $count;
        }

        $result['site'] = $siteTotal;
        $result['total'] = $siteTotal + (int) $formieCount;

        return $result;
    }

    /**
     * Get cleanup candidates for language-level data cleanup.
     *
     * - mappedSource: languages that are mapped source locales and still exist in DB
     * - ghost: languages that are not in active canonical locale set and not mapped sources
     */
    public function getLanguageCleanupCandidates(): array
    {
        $settings = TranslationManager::getInstance()->getSettings();

        $languageCounts = (new \craft\db\Query())
            ->select(['language', 'COUNT(*) as count'])
            ->from('{{%translationmanager_translations}}')
            ->groupBy(['language'])
            ->all();

        $activeMapping = $settings->getActiveLocaleMapping();
        $mappedSources = array_keys($activeMapping);

        $canonicalLocales = [];
        foreach (TranslationManager::getInstance()->getAllowedSites() as $site) {
            $canonicalLocales[] = $settings->mapLanguage($site->language);
        }
        foreach ($activeMapping as $target) {
            $canonicalLocales[] = $target;
        }
        $canonicalLocales = array_values(array_unique(array_filter($canonicalLocales)));

        $canonicalLookup = array_fill_keys(array_map('strtolower', $canonicalLocales), true);
        $mappedSourceLookup = array_fill_keys(array_map('strtolower', $mappedSources), true);

        $result = [
            'mappedSource' => [],
            'ghost' => [],
            'totalCandidates' => 0,
            'totalRows' => 0,
        ];

        foreach ($languageCounts as $row) {
            $language = (string)($row['language'] ?? '');
            $count = (int)($row['count'] ?? 0);
            if ($language === '' || $count <= 0) {
                continue;
            }

            $normalized = strtolower($language);
            $entry = [
                'language' => $language,
                'count' => $count,
            ];

            if (isset($mappedSourceLookup[$normalized])) {
                $entry['mappedTo'] = $activeMapping[$language] ?? $settings->mapLanguage($language);
                $result['mappedSource'][] = $entry;
                $result['totalCandidates']++;
                $result['totalRows'] += $count;
                continue;
            }

            if (!isset($canonicalLookup[$normalized])) {
                $result['ghost'][] = $entry;
                $result['totalCandidates']++;
                $result['totalRows'] += $count;
            }
        }

        return $result;
    }

    /**
     * Get cleanup candidates for removed/disabled categories.
     *
     * Categories present in DB but not currently enabled in settings are candidates.
     */
    public function getCategoryCleanupCandidates(): array
    {
        $settings = TranslationManager::getInstance()->getSettings();

        $categoryCounts = (new \craft\db\Query())
            ->select(['category', 'COUNT(*) as count'])
            ->from('{{%translationmanager_translations}}')
            ->groupBy(['category'])
            ->all();

        $enabledCategories = $settings->getEnabledCategories();
        if ($settings->enableFormieIntegration && !in_array('formie', $enabledCategories, true)) {
            $enabledCategories[] = 'formie';
        }
        $enabledLookup = array_fill_keys(array_map('strtolower', array_filter($enabledCategories)), true);

        $result = [
            'removed' => [],
            'totalCandidates' => 0,
            'totalRows' => 0,
        ];

        foreach ($categoryCounts as $row) {
            $category = (string)($row['category'] ?? '');
            $count = (int)($row['count'] ?? 0);
            if ($category === '' || $count <= 0) {
                continue;
            }

            $normalized = strtolower($category);
            if (isset($enabledLookup[$normalized])) {
                continue;
            }

            $result['removed'][] = [
                'category' => $category,
                'count' => $count,
            ];
            $result['totalCandidates']++;
            $result['totalRows'] += $count;
        }

        usort($result['removed'], static fn(array $a, array $b): int => strcmp($a['category'], $b['category']));

        return $result;
    }

    /**
     * Get the configured Formie plugin name
     */
    public function getFormiePluginName(): string
    {
        return TranslationManager::getFormiePluginName();
    }
    
    /**
     * Get count of unused translations (forms that no longer exist)
     */
    public function getUnusedTranslationCount(): int
    {
        return TranslationManager::getInstance()->translations->getUnusedTranslationCount();
    }
    
    /**
     * Get the backup service
     */
    public function getBackup(): \lindemannrock\translationmanager\services\BackupService
    {
        return TranslationManager::getInstance()->getBackup();
    }
    
    /**
     * Get plugin settings
     */
    public function getSettings(): \lindemannrock\translationmanager\models\Settings
    {
        return TranslationManager::getInstance()->getSettings();
    }
}
