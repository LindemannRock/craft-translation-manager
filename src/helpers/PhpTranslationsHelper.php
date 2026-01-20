<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Helper for parsing PHP translation files
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\helpers;

use Craft;
use craft\helpers\FileHelper;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\TranslationManager;

/**
 * PHP Translations Helper
 *
 * Scans and parses PHP translation files for import
 *
 * @since 1.0.0
 */
class PhpTranslationsHelper
{
    /**
     * Find all PHP translation files in the configured export path
     *
     * @return array<string, array<string, array{value: string, label: string, language: string, category: string}>>
     */
    public static function findFiles(): array
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $basePath = $settings->getExportPath();

        if (!is_dir($basePath)) {
            return [];
        }

        $files = FileHelper::findFiles($basePath, ['only' => ['*.php']]);
        $result = [];

        foreach ($files as $file) {
            // Extract language and category from path
            // Expected structure: {basePath}/{language}/{category}.php
            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file);
            $parts = explode(DIRECTORY_SEPARATOR, $relativePath);

            if (count($parts) >= 2) {
                $language = $parts[0];
                $category = pathinfo($parts[1], PATHINFO_FILENAME);

                $groupKey = $language;
                $result[$groupKey][$file] = [
                    'value' => $file,
                    'label' => "{$category}.php",
                    'language' => $language,
                    'category' => $category,
                ];
            }
        }

        // Sort by language
        ksort($result);

        return $result;
    }

    /**
     * Parse a PHP translation file and compare with existing translations
     *
     * @param string $filePath Full path to PHP file
     * @param string $language Target language code
     * @param string $category Translation category
     * @return array{new: array, existing: array, unchanged: array}
     */
    public static function parseAndCompare(string $filePath, string $language, string $category): array
    {
        $messages = [
            'new' => [],
            'existing' => [],
            'unchanged' => [],
        ];

        if (!file_exists($filePath)) {
            return $messages;
        }

        // Safely load the PHP file
        $translations = self::safeInclude($filePath);

        if (!is_array($translations)) {
            return $messages;
        }

        // Get existing translations for this language and category
        /** @var array<string, TranslationRecord> $existing */
        $existing = TranslationRecord::find()
            ->where(['language' => $language, 'category' => $category])
            ->indexBy('translationKey')
            ->all();

        foreach ($translations as $key => $value) {
            // Skip empty keys
            if (empty(trim((string) $key))) {
                continue;
            }

            $key = (string) $key;
            $value = (string) $value;

            $existingRecord = $existing[$key] ?? null;

            if ($existingRecord instanceof TranslationRecord) {
                // Check if translation is different
                if ($existingRecord->translation !== $value) {
                    $messages['existing'][$key] = [
                        'key' => $key,
                        'newValue' => $value,
                        'oldValue' => $existingRecord->translation ?? '',
                        'status' => $existingRecord->status,
                    ];
                } else {
                    $messages['unchanged'][$key] = [
                        'key' => $key,
                        'value' => $value,
                        'status' => $existingRecord->status,
                    ];
                }
            } else {
                $messages['new'][$key] = [
                    'key' => $key,
                    'value' => $value,
                ];
            }
        }

        return $messages;
    }

    /**
     * Safely include a PHP file and return its contents
     *
     * @param string $filePath
     * @return array|null
     */
    private static function safeInclude(string $filePath): ?array
    {
        // Verify the file is within allowed paths
        $settings = TranslationManager::getInstance()->getSettings();
        $allowedBasePath = realpath($settings->getExportPath());
        $realFilePath = realpath($filePath);

        if ($allowedBasePath === false || $realFilePath === false) {
            Craft::warning("PHP import: Invalid path - base: {$allowedBasePath}, file: {$realFilePath}", 'translation-manager');
            return null;
        }

        // Security check: file must be within the allowed base path
        if (strpos($realFilePath, $allowedBasePath) !== 0) {
            Craft::warning("PHP import: File outside allowed path - {$realFilePath}", 'translation-manager');
            return null;
        }

        // Verify it's a .php file
        if (pathinfo($realFilePath, PATHINFO_EXTENSION) !== 'php') {
            Craft::warning("PHP import: Not a PHP file - {$realFilePath}", 'translation-manager');
            return null;
        }

        try {
            $result = include $realFilePath;
            return is_array($result) ? $result : null;
        } catch (\Throwable $e) {
            Craft::error("PHP import: Error including file - {$e->getMessage()}", 'translation-manager');
            return null;
        }
    }

    /**
     * Get available languages from scanned files
     *
     * @return array<string>
     */
    public static function getAvailableLanguages(): array
    {
        $files = self::findFiles();
        return array_keys($files);
    }

    /**
     * Get available categories from scanned files for a specific language
     *
     * @param string $language
     * @return array<string>
     */
    public static function getAvailableCategories(string $language): array
    {
        $files = self::findFiles();
        $categories = [];

        if (isset($files[$language])) {
            foreach ($files[$language] as $fileInfo) {
                $categories[] = $fileInfo['category'];
            }
        }

        return array_unique($categories);
    }
}
