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
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\FileHelper;

/**
 * Translation Manager Settings Model
 */
class Settings extends Model
{
    /**
     * @var string The display name for the plugin (shown in CP menu and breadcrumbs)
     */
    public string $pluginName = 'Translation Manager';
    
    /**
     * @var string The translation category to use for site translations (e.g. |t('messages'))
     */
    public string $translationCategory = 'messages';

    /**
     * @var bool Whether to enable Formie form translation integration
     */
    public bool $enableFormieIntegration = true;

    /**
     * @var bool Whether to enable site translation capture
     */
    public bool $enableSiteTranslations = true;

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
     * @var array Dynamic integration settings for discovered integrations
     */
    public array $integrationSettings = [];

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
            [['pluginName', 'translationCategory', 'exportPath'], 'required'],
            [['pluginName'], 'string', 'max' => 100],
            [['translationCategory'], 'string', 'max' => 50],
            [['translationCategory'], 'match', 'pattern' => '/^[a-zA-Z][a-zA-Z0-9_-]*$/',
             'message' => 'Translation category must start with a letter and contain only letters, numbers, hyphens, and underscores.'],
            [['translationCategory'], 'validateTranslationCategory'],
            [['exportPath'], 'validateExportPath'],
            [['backupPath'], 'validateBackupPath'],
            [['backupVolumeUid'], 'string'],
            [['itemsPerPage'], 'integer', 'min' => 10, 'max' => 500],
            [['autoSaveDelay'], 'integer', 'min' => 1, 'max' => 10],
            [['enableFormieIntegration', 'enableSiteTranslations', 'autoExport',
              'showContext', 'enableSuggestions', 'autoSaveEnabled', 'backupEnabled', 'backupOnImport'], 'boolean'],
            [['skipPatterns'], 'safe'],
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
            'translationCategory' => 'Translation Category',
            'enableFormieIntegration' => 'Enable Formie Integration',
            'enableSiteTranslations' => 'Enable Site Translations',
            'autoExport' => 'Auto Export',
            'exportPath' => 'Export Path',
            'itemsPerPage' => 'Items Per Page',
            'autoSaveEnabled' => 'Enable Auto-Save',
            'autoSaveDelay' => 'Auto-Save Delay',
            'showContext' => 'Show Context',
            'skipPatterns' => 'Skip Patterns',
            'enableSuggestions' => 'Enable Translation Suggestions',
            'backupEnabled' => 'Enable Backups',
            'backupRetentionDays' => 'Backup Retention Days',
            'backupOnImport' => 'Backup Before Import',
            'backupSchedule' => 'Backup Schedule',
            'backupPath' => 'Backup Path',
            'backupVolumeUid' => 'Backup Volume',
            'logLevel' => 'Log Level',
        ];
    }

    /**
     * Validates the translation category
     */
    public function validateTranslationCategory($attribute, $params, $validator)
    {
        $category = $this->$attribute;

        // Must start with a letter and contain only letters, numbers, hyphens, and underscores
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $category)) {
            $this->addError($attribute, 'Translation category must start with a letter and contain only letters, numbers, hyphens, and underscores.');
            return;
        }

        // Check for reserved categories
        $reserved = ['site', 'app', 'yii', 'craft'];
        if (in_array(strtolower($category), $reserved)) {
            $this->addError($attribute, 'Cannot use reserved category "' . $category . '". The following categories are reserved by Craft: site, app, yii, craft. Please use a unique identifier like your company name.');
        }
    }

    /**
     * Validates the log level
     */
    public function validateLogLevel($attribute, $params, $validator)
    {
        $logLevel = $this->$attribute;

        // Reset session warning when devMode is true - allows warning to show again if devMode changes
        if (Craft::$app->getConfig()->getGeneral()->devMode) {
            Craft::$app->getSession()->remove('tm_debug_config_warning');
        }

        // Debug level is only allowed when devMode is enabled - auto-fallback to info
        if ($logLevel === 'debug' && !Craft::$app->getConfig()->getGeneral()->devMode) {
            $this->$attribute = 'info';

            // Only log warning once per session for config overrides
            if ($this->isOverriddenByConfig('logLevel')) {
                if (Craft::$app->getSession()->get('tm_debug_config_warning') === null) {
                    Craft::warning('Log level "debug" from config file changed to "info" because devMode is disabled. Please update your config/translation-manager.php file.', 'translation-manager');
                    Craft::$app->getSession()->set('tm_debug_config_warning', true);
                }
            } else {
                // Database setting - save the correction
                Craft::warning('Log level automatically changed from "debug" to "info" because devMode is disabled. This setting has been saved.', 'translation-manager');
                $this->saveToDatabase();
            }
        }
    }

    /**
     * Validates the export path to prevent directory traversal attacks
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
            $this->addError($attribute, 'Backup path cannot contain directory traversal sequences (..)'); 
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
            $this->addError($attribute, 'Backup path must start with @root or @storage (secure locations only, never web-accessible)');
        }
    }
    
    /**
     * Returns the full export path
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
                    Craft::error('Failed to get volume path: ' . $e->getMessage(), 'translation-manager');
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
            Craft::warning('Backup path was pointing to root directory. Using safe default: ' . $path, 'translation-manager');
        }

        // Return the resolved path
        return $path;
    }

    /**
     * Load settings from database
     *
     * @param Settings|null $settings Optional existing settings instance
     * @return self
     */
    public static function loadFromDatabase(?Settings $settings = null): self
    {
        if ($settings === null) {
            $settings = new self();
        }
        
        // Check if table exists before querying
        $db = Craft::$app->getDb();
        $tableSchema = $db->getSchema()->getTableSchema('{{%translationmanager_settings}}');
        
        if ($tableSchema === null) {
            // Table doesn't exist yet, return default settings
            return $settings;
        }
        
        try {
            // Load from database
            $row = (new Query())
                ->from('{{%translationmanager_settings}}')
                ->where(['id' => 1])
                ->one();
            
            if ($row) {
                // Handle JSON/array fields
                if (isset($row['skipPatterns'])) {
                    $row['skipPatterns'] = $row['skipPatterns'] ? explode("\n", $row['skipPatterns']) : [];
                }
                
                // Apply database values
                $safeAttributes = $settings->safeAttributes();
                foreach ($safeAttributes as $attribute) {
                    if (!property_exists($settings, $attribute) || !isset($row[$attribute])) {
                        continue;
                    }
                    
                    // Handle boolean casting
                    if (in_array($attribute, ['enableFormieIntegration', 'enableSiteTranslations', 'autoExport',
                                               'showContext', 'enableSuggestions', 'autoSaveEnabled',
                                               'backupEnabled', 'backupOnImport'])) {
                        $settings->$attribute = (bool)$row[$attribute];
                    }
                    // Handle integer casting
                    elseif (in_array($attribute, ['itemsPerPage', 'autoSaveDelay', 'backupRetentionDays'])) {
                        $settings->$attribute = (int)$row[$attribute];
                    }
                    // Handle nullable strings
                    elseif (in_array($attribute, ['backupVolumeUid'])) {
                        $settings->$attribute = $row[$attribute] ?: null;
                    }
                    // Regular assignment for strings and arrays
                    else {
                        $settings->$attribute = $row[$attribute];
                    }
                }
            }
        } catch (\Exception $e) {
            // If there's any error, return default settings
            Craft::error('Failed to load Translation Manager settings from database: ' . $e->getMessage(), 'translation-manager');
        }
        
        return $settings;
    }
    

    /**
     * Check if a setting is being overridden by config file
     * 
     * @param string $attribute The setting attribute name
     * @return bool
     */
    public function isOverriddenByConfig(string $attribute): bool
    {
        // Get the config file path
        $configPath = \Craft::$app->getPath()->getConfigPath() . '/translation-manager.php';

        if (!file_exists($configPath)) {
            return false;
        }

        // Load the raw config file
        $rawConfig = require $configPath;

        // Get the current environment
        $env = \Craft::$app->getConfig()->getGeneral()->env ?? '*';

        // Environment keys to skip
        $envKeys = ['*', 'dev', 'staging', 'production', 'test'];

        // Check environment-specific config first (highest priority)
        $hasEnvConfig = isset($rawConfig[$env]) && is_array($rawConfig[$env]) && array_key_exists($attribute, $rawConfig[$env]);

        // Check if the attribute exists in the root config
        $hasRootConfig = array_key_exists($attribute, $rawConfig) && !in_array($attribute, $envKeys);

        if (!$hasEnvConfig && !$hasRootConfig) {
            return false;
        }

        // Special case for logLevel: if config has 'debug' and devMode is true,
        // then it's NOT an override - it's a valid setting
        if ($attribute === 'logLevel') {
            // Get the config value (env-specific takes precedence)
            $configValue = null;
            if ($hasEnvConfig) {
                $configValue = $rawConfig[$env][$attribute];
            } elseif ($hasRootConfig) {
                $configValue = $rawConfig[$attribute];
            }

            if ($configValue === 'debug' && \Craft::$app->getConfig()->getGeneral()->devMode) {
                return false; // Debug is valid when devMode=true, so not an "override"
            }
        }

        return true;
    }
    
    /**
     * Get the config file value for a setting
     * 
     * @param string $attribute The setting attribute name
     * @return mixed|null
     */
    public function getConfigFileValue(string $attribute): mixed
    {
        $handle = 'translation-manager';
        $configService = \Craft::$app->getConfig();
        $config = $configService->getConfigFromFile($handle);
        
        if (!$config) {
            return null;
        }
        
        // Check environment-specific config first
        $env = \Craft::$app->getConfig()->getGeneral()->env ?? '*';
        if (isset($config[$env][$attribute])) {
            return $config[$env][$attribute];
        }
        
        // Then check base config
        if (isset($config[$attribute])) {
            return $config[$attribute];
        }
        
        return null;
    }

    /**
     * Save settings to database
     *
     * @return bool
     */
    public function saveToDatabase(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $db = Craft::$app->getDb();
        
        // Get specific attributes to save
        $attributes = [
            'pluginName' => $this->pluginName,
            'translationCategory' => $this->translationCategory,
            'enableFormieIntegration' => (int)$this->enableFormieIntegration,
            'enableSiteTranslations' => (int)$this->enableSiteTranslations,
            'autoExport' => (int)$this->autoExport,
            'exportPath' => $this->exportPath,
            'itemsPerPage' => $this->itemsPerPage,
            'autoSaveEnabled' => (int)$this->autoSaveEnabled,
            'autoSaveDelay' => $this->autoSaveDelay,
            'showContext' => (int)$this->showContext,
            'enableSuggestions' => (int)$this->enableSuggestions,
            'skipPatterns' => is_array($this->skipPatterns) ? implode("\n", $this->skipPatterns) : '',
            'backupEnabled' => (int)$this->backupEnabled,
            'backupRetentionDays' => $this->backupRetentionDays,
            'backupOnImport' => (int)$this->backupOnImport,
            'backupSchedule' => $this->backupSchedule,
            'backupPath' => $this->backupPath,
            'backupVolumeUid' => $this->backupVolumeUid,
            'logLevel' => $this->logLevel,
            'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
        ];
        
        // Update existing settings (we know there's always one row from migration)
        try {
            $result = $db->createCommand()
                ->update('{{%translationmanager_settings}}', $attributes, ['id' => 1])
                ->execute();
            
            return $result !== false;
        } catch (\Exception $e) {
            Craft::error('Failed to save Translation Manager settings: ' . $e->getMessage(), 'translation-manager');
            return false;
        }
    }
}