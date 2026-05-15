<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Helper for resolving Craft site IDs from language codes
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\helpers;

use Craft;

/**
 * Site Language Helper
 *
 * @since 5.24.0
 */
class SiteLanguageHelper
{
    /**
     * Resolve a language code to a Craft site ID.
     *
     * First matching site wins when multiple sites share the same language.
     * Falls back to the primary site when no matching language site exists.
     */
    public static function getSiteIdForLanguage(string $language): int
    {
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            if ($site->language === $language || strcasecmp($site->language, $language) === 0) {
                return $site->id;
            }
        }

        return Craft::$app->getSites()->getPrimarySite()->id;
    }
}
