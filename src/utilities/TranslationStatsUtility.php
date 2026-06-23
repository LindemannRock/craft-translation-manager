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
        $settings = $plugin->getSettings();
        $languages = $plugin->getUniqueLanguages();

        $request = Craft::$app->getRequest();
        $language = (string) $request->getParam('language', '');
        if ($language === '') {
            $language = Craft::$app->getSites()->getCurrentSite()->language;
        }
        $language = $settings->mapLanguage($language);

        if (!in_array($language, $languages, true)) {
            $language = $languages[0] ?? $settings->mapLanguage(Craft::$app->getSites()->getCurrentSite()->language);
        }

        $stats = [
            'total' => 0,
            'translated' => 0,
            'pending' => 0,
            'unused' => 0,
            'forms' => 0,
            'site' => 0,
        ];

        if ($user->getIdentity() && $user->checkPermission('translationManager:manageTranslations')) {
            $stats = $plugin->translations->getStatistics(null, $language);
        }

        return Craft::$app->getView()->renderTemplate(
            'translation-manager/utilities/index',
            [
                'stats' => $stats,
                'currentLanguage' => $language,
                'languages' => $languages,
            ]
        );
    }
}
