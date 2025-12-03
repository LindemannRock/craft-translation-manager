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
        
        // Return translated text if available and we're on Arabic site
        if (($currentSite->language === 'ar' || str_starts_with($currentSite->language, 'ar-')) &&
            !empty($translation->arabicText)) {
            return $translation->arabicText;
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
        
        return $record !== null && !empty($record->arabicText);
    }
    
    /**
     * Get unused translation counts by type (NEW: For maintenance dropdown)
     */
    public function getUnusedTranslationCounts(): array
    {
        $query = new \craft\db\Query();
        
        $siteCount = $query->from('{{%translationmanager_translations}}')
            ->where(['status' => 'unused'])
            ->andWhere(['like', 'context', 'site%', false])
            ->count();
            
        $formieCount = $query->from('{{%translationmanager_translations}}')
            ->where(['status' => 'unused'])
            ->andWhere(['or',
                ['like', 'context', 'formie.%', false],
                ['=', 'context', 'formie'],
            ])
            ->count();
        
        return [
            'site' => (int) $siteCount,
            'formie' => (int) $formieCount,
            'total' => (int) ($siteCount + $formieCount),
        ];
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
