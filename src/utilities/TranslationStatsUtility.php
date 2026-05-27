<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Utility for displaying translation statistics
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
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
        return '@lindemannrock/translationmanager/icon-mask.svg';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $plugin = TranslationManager::getInstance();
        $user = Craft::$app->getUser();
        $allowedSites = $plugin->getAllowedSites();

        // Get site from URL parameter, fallback to current CP site
        $request = Craft::$app->getRequest();
        $siteId = (int) $request->getParam('siteId', 0);

        $allowedSiteIds = array_map(fn($site) => (int) $site->id, $allowedSites);
        if (!$siteId || !in_array($siteId, $allowedSiteIds, true)) {
            $firstAllowedSite = reset($allowedSites);
            $siteId = (int) ($firstAllowedSite?->id ?? Craft::$app->getSites()->getCurrentSite()->id);
        }

        $stats = [
            'total' => 0,
            'translated' => 0,
            'pending' => 0,
            'unused' => 0,
            'formie' => 0,
            'site' => 0,
            'siteInfo' => null,
        ];
        $allSiteStats = [];

        if ($user->getIdentity() && $user->checkPermission('translationManager:manageTranslations')) {
            $stats = $plugin->translations->getStatistics($siteId);

            foreach ($allowedSites as $site) {
                $allSiteStats[$site->id] = $plugin->translations->getStatistics($site->id);
                $allSiteStats[$site->id]['siteInfo'] = [
                    'id' => $site->id,
                    'name' => $site->name,
                    'language' => $site->language,
                ];
            }
        }

        return Craft::$app->getView()->renderTemplate(
            'translation-manager/utilities/index',
            [
                'stats' => $stats,
                'currentSiteId' => $siteId,
                'allSiteStats' => $allSiteStats,
                'allSites' => $allowedSites,
            ]
        );
    }
}
