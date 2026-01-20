<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Event listener for capturing missing translations at runtime
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\listeners;

use Craft;
use craft\helpers\Db;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\TranslationManager;
use yii\i18n\MissingTranslationEvent;

/**
 * Missing Translation Listener
 *
 * Captures translations at runtime when they're used but don't exist.
 * This complements the template scanner by catching:
 * - Translations in PHP code (Craft::t())
 * - Dynamic translation keys
 * - Anything the static scanner might miss
 */
class MissingTranslationListener
{
    use LoggingTrait;

    /**
     * @var array Cache of categories we've already checked this request
     */
    private static array $enabledCategoriesCache = [];

    /**
     * @var array Cache of translations we've already captured this request (to avoid duplicates)
     */
    private static array $capturedThisRequest = [];

    /**
     * Handle the missing translation event
     */
    public static function handle(MissingTranslationEvent $event): void
    {
        $plugin = TranslationManager::getInstance();
        if ($plugin === null) {
            return;
        }

        $settings = $plugin->getSettings();

        // Check if capture is enabled
        if (!$settings->captureMissingTranslations) {
            return;
        }

        // Check devMode restriction
        if ($settings->captureMissingOnlyDevMode && !Craft::$app->getConfig()->getGeneral()->devMode) {
            return;
        }

        $category = $event->category;
        $message = $event->message;
        $language = $event->language;

        // Apply locale mapping to use the mapped language for saving
        // This ensures translations are stored under the base locale (e.g., en instead of en-US)
        $language = $settings->mapLanguage($language);

        // Skip empty messages
        if (empty($message) || trim($message) === '') {
            return;
        }

        // Skip if category is not in our enabled list
        if (!self::isEnabledCategory($category, $settings)) {
            return;
        }

        // Skip Twig code patterns
        if (self::containsTwigCode($message)) {
            return;
        }

        // Skip if matches skip patterns
        if (self::matchesSkipPattern($message, $settings->skipPatterns)) {
            return;
        }

        // Skip if already captured this request (performance optimization)
        $cacheKey = md5($message . '|' . $language . '|' . $category);
        if (isset(self::$capturedThisRequest[$cacheKey])) {
            return;
        }
        self::$capturedThisRequest[$cacheKey] = true;

        // Check if translation already exists
        $sourceHash = md5($message);
        $existing = TranslationRecord::findOne([
            'sourceHash' => $sourceHash,
            'language' => $language,
            'category' => $category,
        ]);

        if ($existing !== null) {
            return;
        }

        // Create new pending translation
        try {
            $translation = new TranslationRecord();
            $translation->source = $message;
            $translation->sourceHash = $sourceHash;
            $translation->translationKey = $message;
            $translation->translation = null;
            $translation->language = $language;
            $translation->category = $category;
            $translation->context = 'runtime';
            $translation->status = 'pending';
            $translation->siteId = self::getSiteIdForLanguage($language);
            $translation->usageCount = 1;
            $translation->lastUsed = Db::prepareDateForDb(new \DateTime());
            $translation->dateCreated = Db::prepareDateForDb(new \DateTime());
            $translation->dateUpdated = Db::prepareDateForDb(new \DateTime());

            if ($translation->save()) {
                Craft::info(
                    "Auto-captured missing translation: '{$message}' [{$category}] ({$language})",
                    'translation-manager'
                );
            }
        } catch (\Throwable $e) {
            Craft::warning(
                "Failed to capture missing translation: {$e->getMessage()}",
                'translation-manager'
            );
        }
    }

    /**
     * Check if category is in our enabled list
     */
    private static function isEnabledCategory(string $category, $settings): bool
    {
        // Cache enabled categories for this request
        if (empty(self::$enabledCategoriesCache)) {
            self::$enabledCategoriesCache = $settings->getEnabledCategories();

            // Also include 'formie' if Formie integration is enabled
            if ($settings->enableFormieIntegration && !in_array('formie', self::$enabledCategoriesCache, true)) {
                self::$enabledCategoriesCache[] = 'formie';
            }
        }

        return in_array($category, self::$enabledCategoriesCache, true);
    }

    /**
     * Check if text contains Twig code that shouldn't be translated
     */
    private static function containsTwigCode(string $text): bool
    {
        return preg_match('/\{\{|\{%|\{#/', $text) === 1;
    }

    /**
     * Check if text matches any skip pattern
     */
    private static function matchesSkipPattern(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);
            if ($pattern === '') {
                continue;
            }

            // Case-insensitive contains check
            if (stripos($text, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get site ID for a language (for backwards compatibility)
     */
    private static function getSiteIdForLanguage(string $language): int
    {
        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            if ($site->language === $language || strcasecmp($site->language, $language) === 0) {
                return $site->id;
            }
        }

        return Craft::$app->getSites()->getPrimarySite()->id;
    }

    /**
     * Reset the request caches (useful for testing)
     */
    public static function resetCaches(): void
    {
        self::$enabledCategoriesCache = [];
        self::$capturedThisRequest = [];
    }
}
