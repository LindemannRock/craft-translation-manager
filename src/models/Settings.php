<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Settings model for plugin configuration
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use lindemannrock\base\traits\SettingsConfigTrait;
use lindemannrock\base\traits\SettingsDisplayNameTrait;
use lindemannrock\base\traits\SettingsPersistenceTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * Translation Manager Settings Model
 *
 * @since 1.0.0
 */
class Settings extends Model
{
    use LoggingTrait;
    use SettingsConfigTrait;
    use SettingsDisplayNameTrait;
    use SettingsPersistenceTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(static::pluginHandle());
    }

    /**
     * @var string The display name for the plugin (shown in CP menu and breadcrumbs)
     */
    public string $pluginName = 'Translation Manager';
    
    /**
     * @var string The translation category to use for site translations (e.g. |t('messages'))
     * @deprecated Use translationCategories instead
     */
    public string $translationCategory = 'messages';

    /**
     * @var array Multiple translation categories configuration
     * Format: [['key' => 'messages', 'enabled' => true], ['key' => 'emails', 'enabled' => true]]
     */
    public array $translationCategories = [];

    /**
     * @var string The source language of template strings (language your |t() strings are written in)
     * This should match the language your template strings like 'Copyright', 'Submit', etc. are written in.
     * Defaults to 'en' since most template strings are written in English.
     */
    public string $sourceLanguage = 'en';

    /**
     * @var bool Whether to enable Formie form translation integration
     */
    public bool $enableFormieIntegration = true;

    /**
     * @var bool Whether to enable site translation capture
     */
    public bool $enableSiteTranslations = true;

    /**
     * @var bool Whether to capture missing translations at runtime
     * When enabled, translations that don't exist will be automatically added when used
     */
    public bool $captureMissingTranslations = false;

    /**
     * @var bool Whether to only capture missing translations in devMode
     * Recommended to leave enabled to avoid performance overhead in production
     */
    public bool $captureMissingOnlyDevMode = true;

    /**
     * @var bool Whether to automatically export translations when saved
     */
    public bool $autoExport = true;

    /**
     * @var string The path where translation files should be exported
     */
    public string $exportPath = '@root/translations';
    
    /**
     * @var string The raw export path before parsing
     */
    private ?string $_rawExportPath = null;

    /**
     * @var int Number of items to show per page in the translation manager
     */
    public int $itemsPerPage = 100;
    
    /**
     * @var bool Whether to enable auto-save after typing stops
     */
    public bool $autoSaveEnabled = false;
    
    /**
     * @var int Auto-save delay in seconds (how long to wait after typing stops)
     */
    public int $autoSaveDelay = 2;


    /**
     * @var bool Whether to show the translation context in the CP interface
     */
    public bool $showContext = false;

    /**
     * @var string The logging level for the plugin
     */
    public string $logLevel = 'error';

    /**
     * @var array List of text patterns to skip when capturing translations
     */
    public array $skipPatterns = [];

    /**
     * @var array List of form handle patterns to exclude from Formie translation capture
     * Patterns are matched case-insensitively against form handles
     * Examples: ['-ar', '_ar', 'Ar', '(ar)'] would exclude forms like 'booking-ar', 'form_ar', 'formAr', 'form(ar)'
     */
    public array $excludeFormHandlePatterns = [];

    /**
     * @var array Dynamic integration settings for discovered integrations
     */
    public array $integrationSettings = [];

    /**
     * @var array Locale mapping configuration
     * Maps regional locale variants to base locales (e.g., en-US -> en, fr-CA -> fr)
     * Format: [['source' => 'en-US', 'destination' => 'en', 'enabled' => true], ...]
     */
    public array $localeMapping = [];

    /**
     * @var bool Whether to enable automatic translation suggestions (future feature)
     */
    public bool $enableSuggestions = false;
    
    /**
     * @var bool Whether to enable automatic backups
     */
    public bool $backupEnabled = true;
    
    /**
     * @var int Number of days to keep backups (0 = keep forever)
     */
    public int $backupRetentionDays = 30;
    
    /**
     * @var bool Whether to create a backup before importing
     */
    public bool $backupOnImport = true;
    
    /**
     * @var string Backup schedule (manual, daily, weekly)
     */
    public string $backupSchedule = 'manual';
    
    /**
     * @var string The path where backups should be stored
     */
    public string $backupPath = '@storage/translation-manager/backups';

    /**
     * @var string|null Asset volume UID for backup storage (null = use backupPath)
     */
    public ?string $backupVolumeUid = null;

    public function behaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['exportPath', 'backupPath'],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['pluginName', 'exportPath', 'sourceLanguage'], 'required'],
            [['pluginName'], 'string', 'max' => 100],
            [['translationCategory'], 'string', 'max' => 50],
            [['sourceLanguage'], 'string', 'max' => 10],
            [['sourceLanguage'], 'match', 'pattern' => '/^[a-z]{2}(-[A-Z]{2})?$/',
             'message' => 'Source language must be a valid locale code (e.g., "en", "en-US", "ar").', ],
            [['translationCategory'], 'match', 'pattern' => '/^[a-zA-Z][a-zA-Z0-9_-]*$/',
             'message' => 'Translation category must start with a letter and contain only letters, numbers, hyphens, and underscores.', ],
            [['translationCategory'], 'validateTranslationCategory'],
            [['translationCategories'], 'validateTranslationCategories'],
            [['exportPath'], 'validateExportPath'],
            [['backupPath'], 'validateBackupPath'],
            [['backupVolumeUid'], 'string'],
            [['itemsPerPage'], 'integer', 'min' => 10, 'max' => 500],
            [['autoSaveDelay'], 'integer', 'min' => 1, 'max' => 10],
            [['enableFormieIntegration', 'enableSiteTranslations', 'autoExport',
              'showContext', 'enableSuggestions', 'autoSaveEnabled', 'backupEnabled', 'backupOnImport', ], 'boolean'],
            [['skipPatterns', 'excludeFormHandlePatterns', 'translationCategories', 'localeMapping'], 'safe'],
            [['localeMapping'], 'validateLocaleMapping'],
            [['backupRetentionDays'], 'integer', 'min' => 0, 'max' => 365],
            [['backupSchedule'], 'in', 'range' => ['manual', 'daily', 'weekly']],
            [['logLevel'], 'in', 'range' => ['debug', 'info', 'warning', 'error']],
            [['logLevel'], 'validateLogLevel'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'pluginName' => 'Plugin Name',
            'translationCategory' => 'Translation Category (Deprecated)',
            'translationCategories' => 'Translation Categories',
            'sourceLanguage' => 'Source Language',
            'enableFormieIntegration' => 'Enable Formie Integration',
            'enableSiteTranslations' => 'Enable Site Translations',
            'autoExport' => 'Auto Export',
            'exportPath' => 'Export Path',
            'itemsPerPage' => 'Items Per Page',
            'autoSaveEnabled' => 'Enable Auto-Save',
            'autoSaveDelay' => 'Auto-Save Delay',
            'showContext' => 'Show Context',
            'skipPatterns' => 'Skip Patterns',
            'excludeFormHandlePatterns' => 'Exclude Form Handle Patterns',
            'enableSuggestions' => 'Enable Translation Suggestions',
            'backupEnabled' => 'Enable Backups',
            'backupRetentionDays' => 'Backup Retention Days',
            'backupOnImport' => 'Backup Before Import',
            'backupSchedule' => 'Backup Schedule',
            'backupPath' => 'Backup Path',
            'backupVolumeUid' => 'Backup Volume',
            'logLevel' => 'Log Level',
            'localeMapping' => 'Locale Mapping',
        ];
    }

    /**
     * Set skip patterns from string (for form submission)
     *
     * @param string|array $value
     * @since 1.0.0
     */
    public function setSkipPatterns($value): void
    {
        if (is_string($value)) {
            // If the textarea is empty, set to empty array
            if (trim($value) === '') {
                $this->skipPatterns = [];
            } else {
                $this->skipPatterns = array_filter(array_map('trim', explode("\n", $value)));
            }
        } elseif (is_array($value)) {
            $this->skipPatterns = $value;
        } else {
            $this->skipPatterns = [];
        }
    }

    /**
     * Set exclude form handle patterns from string (for form submission)
     *
     * @param string|array $value
     * @since 5.14.0
     */
    public function setExcludeFormHandlePatterns($value): void
    {
        if (is_string($value)) {
            if (trim($value) === '') {
                $this->excludeFormHandlePatterns = [];
            } else {
                $this->excludeFormHandlePatterns = array_filter(array_map('trim', explode("\n", $value)));
            }
        } elseif (is_array($value)) {
            $this->excludeFormHandlePatterns = $value;
        } else {
            $this->excludeFormHandlePatterns = [];
        }
    }

    /**
     * Reserved categories that cannot be used (conflict with Craft/Yii)
     */
    public const RESERVED_CATEGORIES = ['site', 'app', 'yii', 'craft'];

    /**
     * Validates the translation category (deprecated single category)
     *
     * @since 1.0.0
     */
    public function validateTranslationCategory($attribute, $params, $validator)
    {
        $category = $this->$attribute;

        // Skip validation if empty (using new translationCategories instead)
        if (empty($category)) {
            return;
        }

        // Must start with a letter and contain only letters, numbers, hyphens, and underscores
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $category)) {
            $this->addError($attribute, 'Translation category must start with a letter and contain only letters, numbers, hyphens, and underscores.');
            return;
        }

        // Check for reserved categories
        if (in_array(strtolower($category), self::RESERVED_CATEGORIES)) {
            $this->addError($attribute, 'Cannot use reserved category "' . $category . '". The following categories are reserved by Craft: site, app, yii, craft. Please use a unique identifier like your company name.');
        }
    }

    /**
     * Validates the translation categories array
     *
     * @since 5.0.0
     */
    public function validateTranslationCategories($attribute, $params, $validator): void
    {
        $categories = $this->$attribute;

        if (!is_array($categories)) {
            return;
        }

        foreach ($categories as $index => $categoryConfig) {
            if (!isset($categoryConfig['key']) || empty($categoryConfig['key'])) {
                $this->addError($attribute, "Category at row " . ($index + 1) . " must have a key.");
                continue;
            }

            $key = $categoryConfig['key'];

            // Must start with a letter and contain only letters, numbers, hyphens, and underscores
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $key)) {
                $this->addError($attribute, "Category \"{$key}\" must start with a letter and contain only letters, numbers, hyphens, and underscores.");
                continue;
            }

            // Check for reserved categories
            if (in_array(strtolower($key), self::RESERVED_CATEGORIES)) {
                $this->addError($attribute, "Cannot use reserved category \"{$key}\". Reserved categories: site, app, yii, craft.");
            }
        }
    }

    /**
     * Validates the locale mapping configuration array
     *
     * @since 5.17.0
     */
    public function validateLocaleMapping($attribute, $params, $validator): void
    {
        $mappings = $this->$attribute;

        if (!is_array($mappings)) {
            return;
        }

        $seenSources = [];

        foreach ($mappings as $index => $mapping) {
            // Check required fields
            if (!isset($mapping['source']) || empty($mapping['source'])) {
                $this->addError($attribute, "Locale mapping at row " . ($index + 1) . " must have a source locale.");
                continue;
            }

            if (!isset($mapping['destination']) || empty($mapping['destination'])) {
                $this->addError($attribute, "Locale mapping at row " . ($index + 1) . " must have a destination locale.");
                continue;
            }

            $source = $mapping['source'];
            $destination = $mapping['destination'];

            // Validate locale format (e.g., en, en-US, fr-CA)
            if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $source)) {
                $this->addError($attribute, "Source locale \"{$source}\" at row " . ($index + 1) . " must be a valid locale code (e.g., en, en-US, fr-CA).");
                continue;
            }

            if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $destination)) {
                $this->addError($attribute, "Destination locale \"{$destination}\" at row " . ($index + 1) . " must be a valid locale code (e.g., en, en-US, fr-CA).");
                continue;
            }

            // Prevent mapping to self
            if ($source === $destination) {
                $this->addError($attribute, "Locale mapping at row " . ($index + 1) . " cannot map \"{$source}\" to itself.");
                continue;
            }

            // Check for duplicate source mappings
            if (in_array($source, $seenSources, true)) {
                $this->addError($attribute, "Duplicate source locale \"{$source}\" found in locale mappings.");
                continue;
            }
            $seenSources[] = $source;
        }
    }

    /**
     * Gets all enabled translation category keys
     * Falls back to deprecated translationCategory if translationCategories is empty
     *
     * @return string[]
     * @since 5.0.0
     */
    public function getEnabledCategories(): array
    {
        // If new format has categories, use those
        if (!empty($this->translationCategories)) {
            $enabled = [];
            foreach ($this->translationCategories as $config) {
                if (isset($config['key']) && !empty($config['key'])) {
                    // Default to enabled if not specified
                    $isEnabled = $config['enabled'] ?? true;
                    if ($isEnabled) {
                        $enabled[] = $config['key'];
                    }
                }
            }
            if (!empty($enabled)) {
                return $enabled;
            }
        }

        // Fall back to deprecated single category
        if (!empty($this->translationCategory)) {
            return [$this->translationCategory];
        }

        // Default to 'messages' if nothing configured
        return ['messages'];
    }

    /**
     * Gets the primary (first enabled) translation category
     *
     * @return string
     * @since 5.0.0
     */
    public function getPrimaryCategory(): string
    {
        $enabled = $this->getEnabledCategories();
        return $enabled[0] ?? 'messages';
    }

    /**
     * Checks if a category is enabled
     *
     * @param string $category
     * @return bool
     * @since 5.0.0
     */
    public function isCategoryEnabled(string $category): bool
    {
        // Special case: 'formie' is always enabled if Formie integration is enabled
        if ($category === 'formie') {
            return $this->enableFormieIntegration;
        }

        return in_array($category, $this->getEnabledCategories(), true);
    }

    /**
     * Gets all categories including formie (if enabled)
     *
     * @return string[]
     * @since 5.0.0
     */
    public function getAllCategories(): array
    {
        $categories = $this->getEnabledCategories();

        // Add formie if integration is enabled and not already in list
        if ($this->enableFormieIntegration && !in_array('formie', $categories, true)) {
            $categories[] = 'formie';
        }

        return $categories;
    }

    /**
     * Gets active locale mappings as a lookup array
     *
     * Returns only enabled mappings in the format used by LocaleMappingPhpMessageSource:
     * ['en-US' => 'en', 'fr-CA' => 'fr']
     *
     * @return array<string, string> Lookup array of source => destination
     * @since 5.17.0
     */
    public function getActiveLocaleMapping(): array
    {
        $lookup = [];

        foreach ($this->localeMapping as $mapping) {
            // Skip if not enabled
            $enabled = $mapping['enabled'] ?? true;
            if (!$enabled) {
                continue;
            }

            // Skip if missing required fields
            if (empty($mapping['source']) || empty($mapping['destination'])) {
                continue;
            }

            $lookup[$mapping['source']] = $mapping['destination'];
        }

        return $lookup;
    }

    /**
     * Maps a language code using the active locale mapping.
     *
     * @param string $language The original language code
     * @return string The mapped language code (or original if no mapping exists)
     * @since 5.17.0
     */
    public function mapLanguage(string $language): string
    {
        $mapping = $this->getActiveLocaleMapping();
        return $mapping[$language] ?? $language;
    }

    /**
     * Validates the log level
     *
     * @since 1.0.0
     */
    public function validateLogLevel($attribute, $params, $validator)
    {
        $logLevel = $this->$attribute;

        // Reset session warning when devMode is true - allows warning to show again if devMode changes
        if (Craft::$app->getConfig()->getGeneral()->devMode && !Craft::$app->getRequest()->getIsConsoleRequest()) {
            Craft::$app->getSession()->remove('tm_debug_config_warning');
        }

        // Debug level is only allowed when devMode is enabled - auto-fallback to info
        if ($logLevel === 'debug' && !Craft::$app->getConfig()->getGeneral()->devMode) {
            $this->$attribute = 'info';

            // Only log warning once per session for config overrides
            if ($this->isOverriddenByConfig('logLevel')) {
                if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
                    // Web request - use session to prevent duplicate warnings
                    if (Craft::$app->getSession()->get('tm_debug_config_warning') === null) {
                        $this->logWarning('Log level "debug" from config file changed to "info" because devMode is disabled', [
                            'configFile' => 'config/translation-manager.php',
                        ]);
                        Craft::$app->getSession()->set('tm_debug_config_warning', true);
                    }
                } else {
                    // Console request - just log without session
                    $this->logWarning('Log level "debug" from config file changed to "info" because devMode is disabled', [
                        'configFile' => 'config/translation-manager.php',
                    ]);
                }
            } else {
                // Database setting - save the correction
                $this->logWarning('Log level automatically changed from "debug" to "info" because devMode is disabled');
                $this->saveToDatabase();
            }
        }
    }

    /**
     * Validates the export path to prevent directory traversal attacks
     *
     * @since 1.0.0
     */
    public function validateExportPath($attribute, $params, $validator)
    {
        // Skip validation if empty (will be caught by required rule)
        if (empty($this->$attribute)) {
            return;
        }
        
        // The path might already be resolved by EnvAttributeParserBehavior
        // So we need to check both the original form and resolved form
        $path = $this->$attribute;
        
        // Check for directory traversal attempts
        if (strpos($path, '..') !== false) {
            $this->addError($attribute, 'Export path cannot contain directory traversal sequences (..)');
            return;
        }
        
        // Check if path is already resolved (doesn't start with @)
        if (strpos($path, '@') !== 0) {
            // Explicitly reject web-accessible paths for security
            $webRoot = Craft::getAlias('@webroot');
            if ($webRoot && strpos($path, $webRoot) === 0) {
                $this->addError($attribute, 'Export paths cannot be in web-accessible directories for security');
                return;
            }
            
            // Path is resolved - validate against resolved allowed paths
            $allowedResolvedPaths = [
                Craft::getAlias('@root'),
                Craft::getAlias('@storage'),
                Craft::getAlias('@translations'),
            ];
            
            $isValid = false;
            foreach ($allowedResolvedPaths as $allowedPath) {
                if ($allowedPath && ($path === $allowedPath || strpos($path, $allowedPath . '/') === 0)) {
                    // Only allow exact match or subdirectory with proper separator
                    $isValid = true;
                    break;
                }
            }
        } else {
            // Path is unresolved - validate against aliases
            $allowedAliases = ['@root', '@storage', '@translations'];
            
            $isValid = false;
            foreach ($allowedAliases as $allowedAlias) {
                if (strpos($path, $allowedAlias) === 0) {
                    $isValid = true;
                    break;
                }
            }
        }
        
        if (!$isValid) {
            $this->addError($attribute, 'Export path must start with @root, @storage, or @translations (secure locations only)');
        }
    }

    /**
     * Validates the backup path to prevent directory traversal attacks
     *
     * @since 1.0.0
     */
    public function validateBackupPath($attribute, $params, $validator)
    {
        // Skip validation if empty (will be caught by required rule)
        if (empty($this->$attribute)) {
            return;
        }
        
        $path = $this->$attribute;
        
        // Check for directory traversal attempts
        if (strpos($path, '..') !== false) {
            $this->addError($attribute, Craft::t('translation-manager', 'Backup path cannot contain directory traversal sequences (..)'));
            return;
        }
        
        // Check if path is already resolved (doesn't start with @)
        if (strpos($path, '@') !== 0) {
            // Path is resolved - validate against resolved allowed paths
            $allowedResolvedPaths = [
                Craft::getAlias('@root'),
                Craft::getAlias('@storage'),
            ];
            
            $isValid = false;
            foreach ($allowedResolvedPaths as $allowedPath) {
                if ($allowedPath && ($path === $allowedPath || strpos($path, $allowedPath . '/') === 0)) {
                    // Only allow exact match or subdirectory with proper separator
                    $isValid = true;
                    break;
                }
            }
        } else {
            // Path is unresolved - validate against aliases
            $allowedAliases = ['@root', '@storage'];
            
            $isValid = false;
            foreach ($allowedAliases as $allowedAlias) {
                if (strpos($path, $allowedAlias) === 0) {
                    $isValid = true;
                    break;
                }
            }
        }
        
        if (!$isValid) {
            $this->addError($attribute, Craft::t('translation-manager', 'Backup path must start with @root or @storage (secure locations only, never web-accessible)'));
            return;
        }

        // Resolve the alias to check actual path
        try {
            $resolvedPath = Craft::getAlias($path);
            $webroot = Craft::getAlias('@webroot');

            // Prevent backups in web-accessible directory
            if (str_starts_with($resolvedPath, $webroot)) {
                $this->addError(
                    $attribute,
                    Craft::t('translation-manager', 'Backup path cannot be in a web-accessible directory (@webroot)')
                );
                return;
            }
        } catch (\Exception $e) {
            $this->addError($attribute, Craft::t('translation-manager', 'Invalid backup path: {error}', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Returns the full export path
     *
     * @return string
     * @since 1.0.0
     */
    public function getExportPath(): string
    {
        $path = Craft::getAlias($this->exportPath);
        
        // Additional safety check
        if (strpos($path, '..') !== false) {
            throw new \Exception('Invalid export path');
        }
        
        // Real path resolution to prevent symlink attacks
        $realPath = realpath($path);
        if ($realPath === false) {
            // Path doesn't exist yet - validate parent directory
            $parentDir = dirname($path);
            $realParent = realpath($parentDir);
            if ($realParent === false) {
                throw new \Exception('Invalid export path or parent directory');
            }
            // Return the intended path if parent is valid
            return $path;
        }
        
        // Verify the real path is still within allowed directories
        $validPaths = [
            Craft::getAlias('@root'),
            Craft::getAlias('@storage'),
            Craft::getAlias('@translations'),
        ];
        
        $isValid = false;
        foreach ($validPaths as $validPath) {
            $realValidPath = realpath($validPath);
            if ($realValidPath !== false && strpos($realPath, $realValidPath) === 0) {
                $isValid = true;
                break;
            }
        }
        
        if (!$isValid) {
            throw new \Exception('Export path resolved outside allowed directories');
        }
        
        return $realPath;
    }
    
    /**
     * Returns the full backup path
     *
     * @return string
     * @since 1.0.0
     */
    public function getBackupPath(): string
    {
        // If a volume is selected, use its path
        if ($this->backupVolumeUid) {
            $volume = Craft::$app->getVolumes()->getVolumeByUid($this->backupVolumeUid);
            if ($volume) {
                try {
                    // Get the filesystem configuration
                    $fs = $volume->getFs();

                    // For volumes, we should return a display-friendly path
                    // The actual storage will be handled by VolumeBackupService
                    $volumeName = $volume->name;
                    return "Volume: {$volumeName}/translation-manager/backups";
                } catch (\Exception $e) {
                    // Log the error and fall back
                    $this->logError('Failed to get volume path', ['error' => $e->getMessage()]);
                }
            }
        }

        // No volume selected - use regular backup path and properly resolve it
        $rawPath = $this->backupPath;
        $path = Craft::getAlias($rawPath);

        // If path is null or empty, use default
        if (empty($path)) {
            $defaultPath = '@storage/translation-manager/backups';
            $path = Craft::getAlias($defaultPath);
        }

        // Additional safety checks
        if (strpos($path, '..') !== false) {
            throw new \Exception('Invalid backup path');
        }

        // Prevent backups from being saved in the root directory
        $rootPath = Craft::getAlias('@root');
        if ($path === $rootPath || $path === '/' || $path === '') {
            // Force a safe default path
            $path = Craft::getAlias('@storage/translation-manager/backups');
            $this->logWarning('Backup path was pointing to root directory. Using safe default', ['path' => $path]);
        }

        // Return the resolved path
        return $path;
    }

    // =========================================================================
    // Trait Configuration Methods
    // =========================================================================

    /**
     * Database table name for settings storage
     */
    protected static function tableName(): string
    {
        return 'translationmanager_settings';
    }

    /**
     * Plugin handle for config file resolution
     */
    protected static function pluginHandle(): string
    {
        return 'translation-manager';
    }

    /**
     * Fields that should be cast to boolean
     */
    protected static function booleanFields(): array
    {
        return [
            'enableFormieIntegration',
            'enableSiteTranslations',
            'autoExport',
            'showContext',
            'enableSuggestions',
            'autoSaveEnabled',
            'backupEnabled',
            'backupOnImport',
            'captureMissingTranslations',
            'captureMissingOnlyDevMode',
        ];
    }

    /**
     * Fields that should be cast to integer
     */
    protected static function integerFields(): array
    {
        return [
            'itemsPerPage',
            'autoSaveDelay',
            'backupRetentionDays',
        ];
    }

    /**
     * Fields that should be JSON encoded/decoded
     */
    protected static function jsonFields(): array
    {
        return [
            'skipPatterns',
            'excludeFormHandlePatterns',
            'translationCategories',
            'localeMapping',
        ];
    }

    /**
     * Fields to exclude from database save
     */
    protected static function excludeFromSave(): array
    {
        return ['integrationSettings'];
    }
}
