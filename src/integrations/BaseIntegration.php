<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Base class for all translation integrations
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\integrations;

use craft\base\Component;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\interfaces\TranslationIntegrationInterface;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Base Integration Class
 *
 * Provides common functionality for all translation integrations
 *
 * @since 1.5.0
 */
abstract class BaseIntegration extends Component implements TranslationIntegrationInterface
{
    use LoggingTrait;

    /**
     * @var bool Whether this integration is enabled
     */
    public bool $enabled = true;

    /**
     * @var array Integration-specific configuration
     */
    public array $config = [];

    /**
     * Script family mapping for language codes
     * Maps language codes to their primary writing script
     */
    private const LANGUAGE_SCRIPT_MAP = [
        // Latin script languages
        'en' => 'latin', 'en-US' => 'latin', 'en-GB' => 'latin',
        'de' => 'latin', 'de-DE' => 'latin', 'de-AT' => 'latin', 'de-CH' => 'latin',
        'fr' => 'latin', 'fr-FR' => 'latin', 'fr-CA' => 'latin',
        'es' => 'latin', 'es-ES' => 'latin', 'es-MX' => 'latin',
        'it' => 'latin', 'pt' => 'latin', 'pt-BR' => 'latin',
        'nl' => 'latin', 'sv' => 'latin', 'da' => 'latin', 'no' => 'latin',
        'fi' => 'latin', 'pl' => 'latin', 'cs' => 'latin', 'sk' => 'latin',
        'hu' => 'latin', 'ro' => 'latin', 'hr' => 'latin', 'sl' => 'latin',
        'et' => 'latin', 'lv' => 'latin', 'lt' => 'latin',
        'id' => 'latin', 'ms' => 'latin', 'vi' => 'latin',
        'tr' => 'latin', 'az' => 'latin',

        // Arabic script languages
        'ar' => 'arabic', 'ar-SA' => 'arabic', 'ar-AE' => 'arabic', 'ar-EG' => 'arabic',
        'fa' => 'arabic', 'fa-IR' => 'arabic', // Persian
        'ur' => 'arabic', 'ur-PK' => 'arabic', // Urdu
        'ps' => 'arabic', // Pashto
        'ku' => 'arabic', // Kurdish (Arabic script variant)

        // CJK languages
        'zh' => 'chinese', 'zh-CN' => 'chinese', 'zh-TW' => 'chinese', 'zh-HK' => 'chinese',
        'ja' => 'japanese', 'ja-JP' => 'japanese',
        'ko' => 'korean', 'ko-KR' => 'korean',

        // Cyrillic script languages
        'ru' => 'cyrillic', 'ru-RU' => 'cyrillic',
        'uk' => 'cyrillic', 'uk-UA' => 'cyrillic',
        'bg' => 'cyrillic', 'sr' => 'cyrillic', 'mk' => 'cyrillic',
        'be' => 'cyrillic', 'kk' => 'cyrillic', 'ky' => 'cyrillic',

        // Hebrew
        'he' => 'hebrew', 'he-IL' => 'hebrew',
        'yi' => 'hebrew', // Yiddish

        // Greek
        'el' => 'greek', 'el-GR' => 'greek',

        // Thai
        'th' => 'thai', 'th-TH' => 'thai',

        // Devanagari script languages
        'hi' => 'devanagari', 'hi-IN' => 'devanagari',
        'mr' => 'devanagari', 'ne' => 'devanagari', 'sa' => 'devanagari',

        // Bengali
        'bn' => 'bengali', 'bn-BD' => 'bengali', 'bn-IN' => 'bengali',

        // Tamil
        'ta' => 'tamil', 'ta-IN' => 'tamil',

        // Telugu
        'te' => 'telugu',

        // Kannada
        'kn' => 'kannada',

        // Malayalam
        'ml' => 'malayalam',

        // Gujarati
        'gu' => 'gujarati',

        // Punjabi (Gurmukhi)
        'pa' => 'gurmukhi',

        // Georgian
        'ka' => 'georgian',

        // Armenian
        'hy' => 'armenian',
    ];

    /**
     * Unicode regex patterns for each script family
     */
    private const SCRIPT_PATTERNS = [
        'latin' => '/[\x{0041}-\x{007A}\x{00C0}-\x{00FF}\x{0100}-\x{017F}\x{0180}-\x{024F}]/u',
        'arabic' => '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u',
        'chinese' => '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{20000}-\x{2A6DF}]/u',
        'japanese' => '/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u',
        'korean' => '/[\x{AC00}-\x{D7AF}\x{1100}-\x{11FF}\x{3130}-\x{318F}]/u',
        'cyrillic' => '/[\x{0400}-\x{04FF}\x{0500}-\x{052F}]/u',
        'hebrew' => '/[\x{0590}-\x{05FF}\x{FB1D}-\x{FB4F}]/u',
        'greek' => '/[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}]/u',
        'thai' => '/[\x{0E00}-\x{0E7F}]/u',
        'devanagari' => '/[\x{0900}-\x{097F}\x{A8E0}-\x{A8FF}]/u',
        'bengali' => '/[\x{0980}-\x{09FF}]/u',
        'tamil' => '/[\x{0B80}-\x{0BFF}]/u',
        'telugu' => '/[\x{0C00}-\x{0C7F}]/u',
        'kannada' => '/[\x{0C80}-\x{0CFF}]/u',
        'malayalam' => '/[\x{0D00}-\x{0D7F}]/u',
        'gujarati' => '/[\x{0A80}-\x{0AFF}]/u',
        'gurmukhi' => '/[\x{0A00}-\x{0A7F}]/u',
        'georgian' => '/[\x{10A0}-\x{10FF}]/u',
        'armenian' => '/[\x{0530}-\x{058F}]/u',
    ];

    /**
     * Get the translations service
     */
    protected function getTranslationsService()
    {
        return TranslationManager::getInstance()->translations;
    }

    /**
     * Create or update a translation with proper logging
     * Automatically skips text that is not in the source language
     *
     * @param string $text The text to translate
     * @param string $context The translation context/key
     * @return mixed The created/updated translation record, or null if skipped
     */
    protected function createTranslation(string $text, string $context)
    {
        $integrationName = $this->getName();

        // Check if text is in source language (skip non-source language text)
        if (!$this->isTextInSourceLanguage($text)) {
            $this->logDebug("Skipping non-source-language text", [
                'integration' => $integrationName,
                'text' => mb_substr($text, 0, 50) . (mb_strlen($text) > 50 ? '...' : ''),
                'context' => $context,
            ]);
            return null;
        }

        $this->logDebug("Capturing translation", [
            'integration' => $integrationName,
            'text' => $text,
            'context' => $context,
        ]);

        return $this->getTranslationsService()->createOrUpdateTranslation($text, $context);
    }

    /**
     * Mark translations as unused with proper logging
     *
     * @param array $translationIds Array of translation IDs to mark as unused
     * @return int Number of translations marked as unused
     */
    protected function markTranslationsUnused(array $translationIds): int
    {
        $marked = 0;

        foreach ($translationIds as $id) {
            $record = \lindemannrock\translationmanager\records\TranslationRecord::findOne($id);
            if ($record && $record->status !== 'unused') {
                $record->status = 'unused';
                if ($record->save()) {
                    $marked++;
                    $this->logInfo("Marked translation as unused", [
                        'id' => $id,
                        'key' => $record->translationKey,
                        'context' => $record->context,
                        'integration' => $this->getName(),
                    ]);
                }
            }
        }

        return $marked;
    }

    /**
     * Get translations managed by this integration
     *
     * @param array $criteria Additional search criteria
     * @return array Translation records
     */
    protected function getIntegrationTranslations(array $criteria = []): array
    {
        // Add integration-specific filtering
        $criteria['type'] = $this->getTranslationType();

        return $this->getTranslationsService()->getTranslations($criteria);
    }

    /**
     * Get the translation type identifier for this integration
     * Used for filtering translations by integration type
     */
    abstract protected function getTranslationType(): string;

    /**
     * Default implementation returns empty config schema
     * Override in specific integrations to provide settings
     *
     * @since 1.5.0
     */
    public function getConfigSchema(): array
    {
        return [
            'enabled' => [
                'type' => 'boolean',
                'label' => "Enable {$this->getName()} Integration",
                'default' => true,
            ],
        ];
    }

    /**
     * Default validation accepts any config
     * Override for specific validation rules
     *
     * @since 1.5.0
     */
    public function validateConfig(array $config): array
    {
        return [];
    }

    /**
     * Default statistics implementation
     * Override for integration-specific stats
     *
     * @since 1.5.0
     */
    public function getStatistics(): array
    {
        $translations = $this->getIntegrationTranslations();

        return [
            'name' => $this->getName(),
            'total' => count($translations),
            'translated' => count(array_filter($translations, fn($t) => $t['status'] === 'translated')),
            'pending' => count(array_filter($translations, fn($t) => $t['status'] === 'pending')),
            'unused' => count(array_filter($translations, fn($t) => $t['status'] === 'unused')),
        ];
    }

    // =========================================================================
    // Script Detection Methods (for source language filtering)
    // =========================================================================

    /**
     * Get the script family for a language code
     *
     * @param string $languageCode Language code (e.g., 'en', 'en-US', 'ar', 'zh-CN')
     * @return string Script family name (e.g., 'latin', 'arabic', 'chinese')
     */
    protected function getScriptForLanguage(string $languageCode): string
    {
        // Direct lookup
        if (isset(self::LANGUAGE_SCRIPT_MAP[$languageCode])) {
            return self::LANGUAGE_SCRIPT_MAP[$languageCode];
        }

        // Try base language code (e.g., 'en' from 'en-US')
        $baseCode = explode('-', $languageCode)[0];
        if (isset(self::LANGUAGE_SCRIPT_MAP[$baseCode])) {
            return self::LANGUAGE_SCRIPT_MAP[$baseCode];
        }

        // Default to latin for unknown languages
        $this->logWarning("Unknown language code, defaulting to latin script", [
            'languageCode' => $languageCode,
        ]);

        return 'latin';
    }

    /**
     * Check if text contains characters from a specific script
     *
     * @param string $text The text to check
     * @param string $script The script family to check for
     * @return bool True if text contains characters from the script
     */
    protected function textContainsScript(string $text, string $script): bool
    {
        if (!isset(self::SCRIPT_PATTERNS[$script])) {
            $this->logWarning("Unknown script pattern", ['script' => $script]);
            return true; // Default to true (don't skip) for unknown scripts
        }

        return (bool) preg_match(self::SCRIPT_PATTERNS[$script], $text);
    }

    /**
     * Check if text appears to be in the source language (based on script)
     *
     * For Latin-based source languages (en, de, fr, etc.):
     *   - Returns true if text contains ONLY Latin characters (no Arabic, CJK, etc.)
     *   - Returns false if text contains ANY non-Latin script characters
     *
     * For non-Latin source languages (ar, zh, ja, etc.):
     *   - Returns true if text contains characters from the source script
     *   - Returns false if text contains NO characters from the source script
     *
     * @param string $text The text to check
     * @param string|null $sourceLanguage Source language code (uses settings if null)
     * @return bool True if text appears to be in source language
     */
    protected function isTextInSourceLanguage(string $text, ?string $sourceLanguage = null): bool
    {
        // Skip empty or whitespace-only text
        if (trim($text) === '') {
            return true; // Allow empty text through
        }

        // Get source language from settings if not provided
        if ($sourceLanguage === null) {
            $sourceLanguage = TranslationManager::getInstance()->getSettings()->sourceLanguage;
        }

        $sourceScript = $this->getScriptForLanguage($sourceLanguage);

        // For Latin-based source languages, we want to EXCLUDE text that contains non-Latin scripts
        if ($sourceScript === 'latin') {
            // Check for presence of any non-Latin script
            $nonLatinScripts = ['arabic', 'chinese', 'japanese', 'korean', 'cyrillic', 'hebrew',
                                'greek', 'thai', 'devanagari', 'bengali', 'tamil', 'telugu',
                                'kannada', 'malayalam', 'gujarati', 'gurmukhi', 'georgian', 'armenian', ];

            foreach ($nonLatinScripts as $script) {
                if ($this->textContainsScript($text, $script)) {
                    $this->logDebug("Text contains non-source script, skipping", [
                        'text' => mb_substr($text, 0, 50) . (mb_strlen($text) > 50 ? '...' : ''),
                        'sourceLanguage' => $sourceLanguage,
                        'detectedScript' => $script,
                    ]);
                    return false;
                }
            }

            return true; // No non-Latin scripts found
        }

        // For non-Latin source languages, text must contain characters from that script
        if (!$this->textContainsScript($text, $sourceScript)) {
            $this->logDebug("Text does not contain source script, skipping", [
                'text' => mb_substr($text, 0, 50) . (mb_strlen($text) > 50 ? '...' : ''),
                'sourceLanguage' => $sourceLanguage,
                'expectedScript' => $sourceScript,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Check if a form should be excluded based on patterns
     * Checks both form handle and title against exclusion patterns
     *
     * @param string $formHandle The form handle to check
     * @param string|null $formTitle The form title/name to check (optional)
     * @return bool True if form should be excluded
     */
    protected function isFormExcluded(string $formHandle, ?string $formTitle = null): bool
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $patterns = $settings->excludeFormHandlePatterns ?? [];

        if (empty($patterns)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);
            if (empty($pattern)) {
                continue;
            }

            // Case-insensitive check if handle contains the pattern
            if (stripos($formHandle, $pattern) !== false) {
                $this->logInfo("Form excluded by handle pattern", [
                    'handle' => $formHandle,
                    'title' => $formTitle,
                    'pattern' => $pattern,
                    'matchedIn' => 'handle',
                ]);
                return true;
            }

            // Also check form title if provided
            if ($formTitle !== null && stripos($formTitle, $pattern) !== false) {
                $this->logInfo("Form excluded by title pattern", [
                    'handle' => $formHandle,
                    'title' => $formTitle,
                    'pattern' => $pattern,
                    'matchedIn' => 'title',
                ]);
                return true;
            }
        }

        return false;
    }
}
