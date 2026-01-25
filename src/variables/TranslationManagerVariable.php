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
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
     */
    public function getStats(): array
    {
        return TranslationManager::getInstance()->translations->getStatistics();
    }
    
    /**
     * Get allowed sites for current license
     *
     * @since 1.0.0
     */
    public function getAllowedSites(): array
    {
        return TranslationManager::getInstance()->getAllowedSites();
    }
    
    /**
     * Check if a translation exists
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
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
     * Get the configured Formie plugin name
     *
     * @since 1.0.0
     */
    public function getFormiePluginName(): string
    {
        return TranslationManager::getFormiePluginName();
    }
    
    /**
     * Get count of unused translations (forms that no longer exist)
     *
     * @since 1.0.0
     */
    public function getUnusedTranslationCount(): int
    {
        return TranslationManager::getInstance()->translations->getUnusedTranslationCount();
    }
    
    /**
     * Get the backup service
     *
     * @since 1.0.0
     */
    public function getBackup(): \lindemannrock\translationmanager\services\BackupService
    {
        return TranslationManager::getInstance()->getBackup();
    }
    
    /**
     * Get plugin settings
     *
     * @since 1.0.0
     */
    public function getSettings(): \lindemannrock\translationmanager\models\Settings
    {
        return TranslationManager::getInstance()->getSettings();
    }
}
