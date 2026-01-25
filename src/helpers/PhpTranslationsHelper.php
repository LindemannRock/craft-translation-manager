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
 * @since 5.17.0
 */
class PhpTranslationsHelper
{
    /**
     * Find all PHP translation files in the configured export path
     *
     * @return array<string, array<string, array{value: string, label: string, language: string, category: string}>>
     * @since 5.17.0
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
                    'label' => "{$language}/{$category}.php",
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
     * @since 5.17.0
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
        $translations = self::safeParseFile($filePath);

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
     * Safely parse a PHP translation file without executing code
     *
     * Uses token_get_all() to validate that the file only contains a simple
     * `return ['key' => 'value'];` array structure. No code execution occurs.
     * Path must be within the configured export path.
     *
     * @param string $filePath
     * @return array<string, string>|null
     * @since 5.17.0
     */
    public static function safeParseFile(string $filePath): ?array
    {
        return self::parseFileInternal($filePath, false);
    }

    /**
     * Safely parse a PHP translation file for console commands
     *
     * Same as safeParseFile() but skips path validation for console use
     * where files may be outside the configured export path.
     *
     * @param string $filePath
     * @return array<string, string>|null
     * @since 5.17.0
     */
    public static function safeParseFileForConsole(string $filePath): ?array
    {
        return self::parseFileInternal($filePath, true);
    }

    /**
     * Internal file parsing implementation
     *
     * @param string $filePath
     * @param bool $skipPathValidation
     * @return array<string, string>|null
     */
    private static function parseFileInternal(string $filePath, bool $skipPathValidation): ?array
    {
        $realFilePath = realpath($filePath);

        if ($realFilePath === false) {
            Craft::warning("PHP import: Invalid file path - {$filePath}", 'translation-manager');
            return null;
        }

        // Verify it's a .php file
        if (pathinfo($realFilePath, PATHINFO_EXTENSION) !== 'php') {
            Craft::warning("PHP import: Not a PHP file - {$realFilePath}", 'translation-manager');
            return null;
        }

        // Path validation (skipped for console commands with trusted paths)
        if (!$skipPathValidation) {
            $settings = TranslationManager::getInstance()->getSettings();
            $allowedBasePath = realpath($settings->getExportPath());

            if ($allowedBasePath === false) {
                Craft::warning("PHP import: Invalid base path", 'translation-manager');
                return null;
            }

            // Security check: file must be within the allowed base path
            if (!str_starts_with($realFilePath, $allowedBasePath)) {
                Craft::warning("PHP import: File outside allowed path - {$realFilePath}", 'translation-manager');
                return null;
            }
        }

        try {
            $content = file_get_contents($realFilePath);
            if ($content === false) {
                Craft::warning("PHP import: Could not read file - {$realFilePath}", 'translation-manager');
                return null;
            }

            return self::parseTranslationArrayFromTokens($content, $realFilePath);
        } catch (\Throwable $e) {
            Craft::error("PHP import: Error parsing file - {$e->getMessage()}", 'translation-manager');
            return null;
        }
    }

    /**
     * Parse PHP translation file content using pure token-based extraction (no regex)
     *
     * Validates all tokens are safe and extracts key => value pairs directly from
     * the token stream. More robust than regex - handles all formatting variations.
     *
     * @param string $content PHP file content
     * @param string $filePath For error logging
     * @return array<string, string>|null
     */
    private static function parseTranslationArrayFromTokens(string $content, string $filePath): ?array
    {
        // Allowed tokens for a simple return array
        $allowedTokens = [
            T_OPEN_TAG,
            T_CLOSE_TAG,
            T_RETURN,
            T_ARRAY,
            T_CONSTANT_ENCAPSED_STRING,
            T_LNUMBER,
            T_DNUMBER,
            T_DOUBLE_ARROW,
            T_WHITESPACE,
            T_COMMENT,
            T_DOC_COMMENT,
        ];

        // Allowed single characters
        $allowedChars = ['[', ']', '(', ')', ',', ';'];

        $tokens = token_get_all($content);

        // First pass: validate all tokens are safe and filter out whitespace/comments
        $filteredTokens = [];

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $tokenId = $token[0];
                if (!in_array($tokenId, $allowedTokens, true)) {
                    $tokenName = token_name($tokenId);
                    Craft::warning("PHP import: Unsafe token '{$tokenName}' in file - {$filePath}", 'translation-manager');
                    return null;
                }
                // Skip whitespace and comments for easier parsing
                if ($tokenId !== T_WHITESPACE && $tokenId !== T_COMMENT && $tokenId !== T_DOC_COMMENT) {
                    $filteredTokens[] = $token;
                }
            } elseif (is_string($token)) {
                if (!in_array($token, $allowedChars, true)) {
                    Craft::warning("PHP import: Unsafe character '{$token}' in file - {$filePath}", 'translation-manager');
                    return null;
                }
                $filteredTokens[] = $token;
            }
        }

        // Second pass: extract key => value pairs from token stream
        return self::extractPairsFromTokens($filteredTokens, $filePath);
    }

    /**
     * Extract key => value pairs directly from filtered token stream
     *
     * Walks tokens looking for pattern: T_RETURN, '[' or T_ARRAY, then
     * pairs of T_CONSTANT_ENCAPSED_STRING around T_DOUBLE_ARROW
     *
     * @param array $tokens Filtered tokens (no whitespace/comments)
     * @param string $filePath For error logging
     * @return array<string, string>|null
     */
    private static function extractPairsFromTokens(array $tokens, string $filePath): ?array
    {
        $result = [];
        $tokenCount = count($tokens);
        $i = 0;

        // Find T_RETURN
        while ($i < $tokenCount) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_RETURN) {
                $i++;
                break;
            }
            $i++;
        }

        if ($i >= $tokenCount) {
            Craft::warning("PHP import: No return statement found - {$filePath}", 'translation-manager');
            return null;
        }

        // Next should be '[' or T_ARRAY
        $token = $tokens[$i] ?? null;
        $isArraySyntax = $token === '[' || (is_array($token) && $token[0] === T_ARRAY);

        if (!$isArraySyntax) {
            Craft::warning("PHP import: Return is not an array - {$filePath}", 'translation-manager');
            return null;
        }

        $i++; // Skip '[' or 'array'

        // If T_ARRAY, skip the '('
        if (is_array($tokens[$i - 1] ?? null) && ($tokens[$i - 1][0] ?? null) === T_ARRAY) {
            if (($tokens[$i] ?? null) === '(') {
                $i++;
            }
        }

        // Now parse key => value pairs
        while ($i < $tokenCount) {
            $token = $tokens[$i];

            // End of array
            if ($token === ']' || $token === ')') {
                break;
            }

            // Skip commas
            if ($token === ',') {
                $i++;
                continue;
            }

            // Expect: STRING => STRING
            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $keyToken = $token;

                // Next should be =>
                $i++;
                $arrow = $tokens[$i] ?? null;
                if (!is_array($arrow) || $arrow[0] !== T_DOUBLE_ARROW) {
                    $i++;
                    continue; // Skip malformed entry
                }

                // Next should be value (string or number)
                $i++;
                $valueToken = $tokens[$i] ?? null;
                if (!is_array($valueToken)) {
                    $i++;
                    continue; // Skip malformed entry
                }

                // Extract key and value
                $key = self::parseStringToken($keyToken);
                $value = self::parseValueToken($valueToken);

                if ($key !== null && $value !== null) {
                    $result[$key] = $value;
                }
            }

            $i++;
        }

        return $result;
    }

    /**
     * Parse a string token and handle escape sequences
     *
     * @param array $token Token array [type, value, line]
     * @return string|null
     */
    private static function parseStringToken(array $token): ?string
    {
        if ($token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        $raw = $token[1];
        $quote = $raw[0]; // ' or "
        $inner = substr($raw, 1, -1); // Remove quotes

        // Unescape the quote character
        $inner = str_replace('\\' . $quote, $quote, $inner);

        // Handle escape sequences based on quote type
        if ($quote === '"') {
            // Double-quoted: handle \n, \r, \t, \\
            $inner = str_replace('\\\\', "\x00ESC_BACKSLASH\x00", $inner);
            $inner = str_replace(['\\n', '\\r', '\\t'], ["\n", "\r", "\t"], $inner);
            $inner = str_replace("\x00ESC_BACKSLASH\x00", '\\', $inner);
        } else {
            // Single-quoted: only \\ is an escape sequence
            $inner = str_replace('\\\\', '\\', $inner);
        }

        return $inner;
    }

    /**
     * Parse a value token (string or number)
     *
     * @param array $token Token array [type, value, line]
     * @return string|null
     */
    private static function parseValueToken(array $token): ?string
    {
        if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
            return self::parseStringToken($token);
        }

        if ($token[0] === T_LNUMBER || $token[0] === T_DNUMBER) {
            return $token[1];
        }

        return null;
    }

    /**
     * Get available languages from scanned files
     *
     * @return array<string>
     * @since 5.17.0
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
     * @since 5.17.0
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
