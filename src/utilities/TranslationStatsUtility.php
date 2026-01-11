<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Utility for displaying translation statistics
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\utilities;

use Craft;
use craft\base\Utility;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Translation Stats Utility
 *
 * @since 1.0.0
 */
class TranslationStatsUtility extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return TranslationManager::$plugin->getSettings()->getFullName();
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'translation-stats';
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return 'language';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        // Get site from URL parameter, fallback to current CP site
        $request = Craft::$app->getRequest();
        $siteId = $request->getParam('siteId');
        
        if (!$siteId) {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }
        
        
        $stats = TranslationManager::getInstance()->translations->getStatistics((int)$siteId);
        $allSiteStats = [];
        
        // Get stats for enabled sites only
        $plugin = TranslationManager::getInstance();
        $enabledSites = $plugin->getAllowedSites();

        foreach ($enabledSites as $site) {
            $allSiteStats[$site->id] = $plugin->translations->getStatistics($site->id);
            $allSiteStats[$site->id]['siteInfo'] = [
                'id' => $site->id,
                'name' => $site->name,
                'language' => $site->language,
            ];
        }
        
        return Craft::$app->getView()->renderTemplate(
            'translation-manager/utilities/index',
            [
                'stats' => $stats,
                'currentSiteId' => (int)$siteId,
                'allSiteStats' => $allSiteStats,
            ]
        );
    }
}
