<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Comprehensive translation management system for Formie forms and site content
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\UrlHelper;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\UrlManager;
use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\services\TranslationsService;
use lindemannrock\translationmanager\services\FormieService;
use lindemannrock\translationmanager\services\ExportService;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\utilities\TranslationStatsUtility;
use lindemannrock\translationmanager\variables\TranslationManagerVariable;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\logginglibrary\LoggingLibrary;

/**
 * Translation Manager plugin
 *
 * @author LindemannRock
 * @copyright LindemannRock
 * @license Proprietary
 *
 * @property-read TranslationsService $translations
 * @property-read FormieService $formie
 * @property-read ExportService $export
 * @property-read BackupService $backup
 * @property-read Settings $settings
 * @method Settings getSettings()
 */
class TranslationManager extends Plugin
{
    use LoggingTrait;

    /**
     * @var string Plugin schema version for migrations
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool Whether the plugin provides a control panel settings page
     */
    public bool $hasCpSettings = true;

    /**
     * @var TranslationManager|null
     */
    public static ?TranslationManager $plugin = null;

    /**
     * @var Settings|null
     */
    private ?Settings $_settings = null;

    /**
     * @var bool Whether the plugin registers its own control panel section
     */
    public bool $hasCpSection = true;


    public static function config(): array
    {
        return [
            'components' => [
                'translations' => TranslationsService::class,
                'formie' => FormieService::class, // Legacy - will be deprecated
                'export' => ExportService::class,
                'backup' => BackupService::class,
                'integrations' => IntegrationService::class, // New integration system
            ],
        ];
    }

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Configure logging using the new logging library
        $settings = $this->getSettings();

        LoggingLibrary::configure([
            'pluginHandle' => $this->handle,
            'pluginName' => $settings->getFullName(),
            'logLevel' =>  $settings->logLevel ?? 'error',
            'itemsPerPage' => $settings->itemsPerPage ?? 50,
            'permissions' => ['translationManager:viewLogs'],
        ]);

        // Override plugin name from config if available, otherwise use from database settings
        $configFileSettings = Craft::$app->getConfig()->getConfigFromFile('translation-manager');
        $configPath = Craft::$app->getPath()->getConfigPath() . '/translation-manager.php';

        // Need to check raw config file for root-level settings since Craft only returns env-specific values
        if (file_exists($configPath)) {
            $rawConfig = require $configPath;
            if (isset($rawConfig['pluginName'])) {
                $this->name = $rawConfig['pluginName'];
            }
        } else {
            // Get from database settings
            $settings = $this->getSettings();
            if ($settings && !empty($settings->pluginName)) {
                $this->name = $settings->pluginName;
            }
        }

        // Register services
        $this->setComponents([
            'translations' => TranslationsService::class,
            'formie' => FormieService::class, // Legacy - will be deprecated
            'export' => ExportService::class,
            'backup' => BackupService::class,
            'integrations' => IntegrationService::class, // New integration system
        ]);

        // Register CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, $this->getCpUrlRules());
            }
        );

        // Register permissions
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'Translation Manager',
                    'permissions' => [
                        'translationManager:viewTranslations' => [
                            'label' => 'View translations',
                        ],
                        'translationManager:editTranslations' => [
                            'label' => 'Edit translations',
                            'nested' => [
                                'translationManager:deleteTranslations' => [
                                    'label' => 'Delete translations',
                                ],
                                'translationManager:exportTranslations' => [
                                    'label' => 'Export translations',
                                ],
                            ],
                        ],
                        'translationManager:viewLogs' => [
                            'label' => 'View logs',
                        ],
                        'translationManager:editSettings' => [
                            'label' => 'Edit plugin settings',
                        ],
                    ],
                ];
            }
        );

        // Register utilities
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = TranslationStatsUtility::class;
            }
        );

        // Register Twig extension for plugin name helpers
        Craft::$app->view->registerTwigExtension(new \lindemannrock\translationmanager\twigextensions\PluginNameExtension());

        // Register variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('translationManager', TranslationManagerVariable::class);
            }
        );

        // Register message source for translation category
        if ($this->getSettings()->enableSiteTranslations) {
            $this->registerFileMessageSource(); // Use file-based translations
        }

        // Trigger integration service initialization (lightweight event handler registration)
        $this->get('integrations');

        // DISABLED: Old Formie hooks - testing new integration system
        // if ($this->getSettings()->enableFormieIntegration && class_exists('verbb\formie\Formie')) {
        //     $this->formie->registerFormieHooks();
        // }

        // Register console controllers
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'lindemannrock\translationmanager\console\controllers';
        }

        // Schedule backup job if enabled
        $this->scheduleBackupJob();
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        if ($item) {
            // Use dynamic plugin name from settings
            $item['label'] = $this->getSettings()->getFullName();

            // Use Craft's built-in language icon (same as the module used)
            $item['icon'] = '@app/icons/language.svg';

            // Always add the main translations item
            $item['subnav'] = [
                'translations' => [
                    'label' => 'Translations',
                    'url' => 'translation-manager',
                ],
            ];

            // Add Generate section (visible to anyone who can view translations)
            if (Craft::$app->getUser()->checkPermission('translationManager:viewTranslations')) {
                $item['subnav']['generate'] = [
                    'label' => 'Generate',
                    'url' => 'translation-manager/generate',
                ];
            }

            // Add Import/Export section (visible to anyone who can view translations)
            if (Craft::$app->getUser()->checkPermission('translationManager:viewTranslations')) {
                $item['subnav']['import-export'] = [
                    'label' => 'Import/Export',
                    'url' => 'translation-manager/import-export',
                ];
            }

            // Add Maintenance section (visible to anyone who can view translations)
            if (Craft::$app->getUser()->checkPermission('translationManager:viewTranslations')) {
                $item['subnav']['maintenance'] = [
                    'label' => 'Maintenance',
                    'url' => 'translation-manager/maintenance',
                ];
            }

            // Add Backups section (visible to anyone who can view translations)
            if (Craft::$app->getUser()->checkPermission('translationManager:viewTranslations')) {
                $item['subnav']['backups'] = [
                    'label' => 'Backups',
                    'url' => 'translation-manager/backups',
                ];
            }

            // Add logs section using the logging library (only if installed)
            if (Craft::$app->getPlugins()->isPluginInstalled('logging-library') &&
                Craft::$app->getPlugins()->isPluginEnabled('logging-library')) {
                $item = LoggingLibrary::addLogsNav($item, $this->handle, [
                    'translationManager:viewLogs'
                ]);
            }

            if (Craft::$app->getUser()->checkPermission('translationManager:editSettings')) {
                $item['subnav']['settings'] = [
                    'label' => 'Settings',
                    'url' => 'translation-manager/settings',
                    'match' => 'translation-manager/settings*', // Match all settings pages
                ];
            }
        }

        return $item;
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(
            UrlHelper::cpUrl('translation-manager/settings')
        );
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    public function getSettings(): ?Model
    {
        if ($this->_settings === null) {

            // Create base settings
            $settings = $this->createSettingsModel();

            // Load database values
            $settings = Settings::loadFromDatabase($settings);

            // Override ONLY with explicitly set config file values
            $configPath = Craft::$app->getPath()->getConfigPath() . '/translation-manager.php';
            if (file_exists($configPath)) {
                $rawConfig = require $configPath;

                // Apply root-level config values (only if explicitly set)
                foreach ($rawConfig as $key => $value) {
                    // Skip environment keys
                    if (!in_array($key, ['*', 'dev', 'staging', 'production', 'test'])) {
                        if (property_exists($settings, $key) && array_key_exists($key, $rawConfig)) {
                            $settings->$key = $value;
                        }
                    }
                }

                // Apply environment-specific overrides (highest priority)
                $env = Craft::$app->getConfig()->getGeneral()->env ?? '*';
                if (isset($rawConfig[$env]) && is_array($rawConfig[$env])) {
                    foreach ($rawConfig[$env] as $key => $value) {
                        if (property_exists($settings, $key) && array_key_exists($key, $rawConfig[$env])) {
                            $settings->$key = $value;
                        }
                    }
                }
            }

            // CRITICAL: Validate settings even when loaded from config
            // This prevents config files from bypassing security validation
            if (!$settings->validate()) {
                $errors = $settings->getFirstErrors();

                $this->logError('Invalid Translation Manager configuration', [
                    'errors' => $errors,
                    'configFile' => 'config/translation-manager.php'
                ]);

                // For security, throw exception rather than silently using invalid config
                $errorMessage = 'Invalid Translation Manager configuration: ' . implode(', ', $errors);
                throw new \Exception($errorMessage . ' Please check your config/translation-manager.php file.');
            }

            $this->_settings = $settings;
        }

        return $this->_settings;
    }

    /**
     * @inheritdoc
     */
    public function setSettings(array|Model $settings): void
    {
        $oldSettings = $this->getSettings();
        $this->_settings = null;
        parent::setSettings($settings);

        // If it's a model, save to database
        if ($settings instanceof Settings) {
            $settings->saveToDatabase();

            // Check if backup schedule changed
            if ($oldSettings && (
                $oldSettings->backupEnabled !== $settings->backupEnabled ||
                $oldSettings->backupSchedule !== $settings->backupSchedule
            )) {
                $this->handleBackupScheduleChange($settings);
            }
        }
    }

    /**
     * Get the backup service
     *
     * @return BackupService
     */
    public function getBackup(): BackupService
    {
        return $this->get('backup');
    }

    /**
     * Check if this is a Pro license (future implementation)
     */
    public function isPro(): bool
    {
        // TODO: Implement license checking when commercialized
        return false; // Always free for now
    }

    /**
     * Get sites allowed for the current license
     */
    public function getAllowedSites(): array
    {
        if ($this->isPro()) {
            return Craft::$app->getSites()->getAllSites();
        }

        // Free version: Primary site + one additional (max 2 sites)
        $allSites = Craft::$app->getSites()->getAllSites();
        $primary = Craft::$app->getSites()->getPrimarySite();

        if (count($allSites) <= 2) {
            return $allSites; // All sites allowed if 2 or fewer
        }

        // More than 2 sites - limit to primary + first non-primary
        $allowedSites = [$primary];

        foreach ($allSites as $site) {
            if ($site->id !== $primary->id && count($allowedSites) < 2) {
                $allowedSites[] = $site;
                break;
            }
        }

        return $allowedSites;
    }

    /**
     * Check if site is allowed for current license
     */
    public function isSiteAllowed(int $siteId): bool
    {
        $allowedSites = $this->getAllowedSites();
        foreach ($allowedSites as $site) {
            if ($site->id === $siteId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the configured name of the Formie plugin
     *
     * @return string
     */
    public static function getFormiePluginName(): string
    {
        if (class_exists('verbb\formie\Formie')) {
            $formie = Craft::$app->getPlugins()->getPlugin('formie');
            if ($formie && method_exists($formie, 'getSettings')) {
                $settings = $formie->getSettings();
                if ($settings && property_exists($settings, 'pluginName') && $settings->pluginName) {
                    return $settings->pluginName;
                }
            }
        }

        // Default fallback
        return 'Formie';
    }


    /**
     * Get CP URL rules
     */
    private function getCpUrlRules(): array
    {
        return [
            // Translations routes (main section)
            'translation-manager' => 'translation-manager/translations/index',
            'translation-manager/translations' => 'translation-manager/translations/index',
            'translation-manager/translations/save' => 'translation-manager/translations/save',
            'translation-manager/translations/save-all' => 'translation-manager/translations/save-all',
            'translation-manager/translations/delete' => 'translation-manager/translations/delete',

            // Generate routes
            'translation-manager/generate' => 'translation-manager/generate/index',

            // Import/Export routes
            'translation-manager/import-export' => 'translation-manager/import-export/index',
            'translation-manager/import' => 'translation-manager/import/index',
            'translation-manager/import/check-existing' => 'translation-manager/import/check-existing',
            'translation-manager/import/history' => 'translation-manager/import/history',
            'translation-manager/import/clear-logs' => 'translation-manager/import/clear-logs',
            'translation-manager/export' => 'translation-manager/export/index',
            'translation-manager/export/download' => 'translation-manager/export/download',
            'translation-manager/export/selected' => 'translation-manager/export/selected',
            'translation-manager/export/files' => 'translation-manager/export/files',
            'translation-manager/export/formie-files' => 'translation-manager/export/formie-files',
            'translation-manager/export/site-files' => 'translation-manager/export/site-files',

            // Maintenance routes
            'translation-manager/maintenance' => 'translation-manager/maintenance/index',
            'translation-manager/maintenance/clean-unused' => 'translation-manager/maintenance/clean-unused',
            'translation-manager/maintenance/debug-search-page' => 'translation-manager/maintenance/debug-search-page',
            'translation-manager/maintenance/debug-search' => 'translation-manager/maintenance/debug-search',
            'translation-manager/maintenance/recapture-formie' => 'translation-manager/maintenance/recapture-formie',

            // Backup routes
            'translation-manager/backups' => 'translation-manager/backup/index',
            'translation-manager/backup/get-backups' => 'translation-manager/backup/get-backups',
            'translation-manager/backup/create' => 'translation-manager/backup/create',
            'translation-manager/backup/restore' => 'translation-manager/backup/restore',
            'translation-manager/backup/delete' => 'translation-manager/backup/delete',
            'translation-manager/backup/download' => 'translation-manager/backup/download',

            // Settings routes
            'translation-manager/settings' => 'translation-manager/settings/index',
            'translation-manager/settings/<section:\w+>' => 'translation-manager/settings/<section>',

            // Logging routes
            'translation-manager/logs' => 'logging-library/logs/index',
            'translation-manager/logs/download' => 'logging-library/logs/download',
        ];
    }

    /**
     * Register file-based message source for site translations
     */
    private function registerFileMessageSource(): void
    {
        $i18n = Craft::$app->getI18n();
        $settings = $this->getSettings();
        $category = $settings->translationCategory;
        $basePath = $settings->getExportPath(); // Use the configured export path

        // Get the primary site's base language (without locale)
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $sourceLanguage = explode('-', $primarySite->language)[0]; // e.g., 'en' from 'en-US'

        $i18n->translations[$category] = [
            'class' => 'yii\i18n\PhpMessageSource',
            'sourceLanguage' => $sourceLanguage, // Now dynamic based on primary site
            'basePath' => $basePath,
            'forceTranslation' => true, // Force translation even for same language
            'fileMap' => [
                $category => $category . '.php',
            ],
        ];
    }


    /**
     * Schedule backup job if enabled
     * Called on every plugin init to ensure job is always in queue
     */
    private function scheduleBackupJob(): void
    {
        $settings = $this->getSettings();

        // Only schedule if backups are enabled and not manual
        if (!$settings->backupEnabled || $settings->backupSchedule === 'manual') {
            return;
        }

        // Check if a backup job is already scheduled
        $existingJob = (new \craft\db\Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'translationmanager'])
            ->andWhere(['like', 'job', 'CreateBackupJob'])
            ->andWhere(['<=', 'timePushed', time() + 86400]) // Within next 24 hours
            ->exists();

        if (!$existingJob) {
            // Create backup job
            $job = new \lindemannrock\translationmanager\jobs\CreateBackupJob([
                'reason' => 'scheduled',
                'reschedule' => true,
            ]);

            // Add to queue with a small initial delay
            // The job will re-queue itself to run at the scheduled interval
            Craft::$app->getQueue()->delay(5 * 60)->push($job); // 5 minute initial delay

            $this->logInfo('Scheduled initial backup job', [
                'delay' => '5 minutes',
                'schedule' => $settings->backupSchedule
            ]);
        }
    }

    /**
     * Handle backup schedule changes when settings are saved
     */
    private function handleBackupScheduleChange(Settings $settings): void
    {
        if (!$settings->backupEnabled || $settings->backupSchedule === 'manual') {
            // Cancel any existing scheduled backup jobs
            $this->cancelScheduledBackupJobs();
            $this->logInfo('Backup scheduling disabled');
            return;
        }

        // Check if there's already a scheduled backup job in the queue
        $existingJob = (new \craft\db\Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'translationmanager'])
            ->andWhere(['like', 'job', 'CreateBackupJob'])
            ->andWhere(['fail' => false])
            ->andWhere(['timePushed' => null])
            ->exists();

        if ($existingJob) {
            $this->logInfo('Scheduled backup job already exists, not creating a new one');
            return;
        }

        // Schedule the first backup job with appropriate delay
        $delay = match ($settings->backupSchedule) {
            'daily' => 86400, // 24 hours
            'weekly' => 604800, // 7 days
            'monthly' => 2592000, // 30 days
            default => 86400,
        };

        $job = new \lindemannrock\translationmanager\jobs\CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]);

        // Add job with delay
        Craft::$app->getQueue()->delay($delay)->push($job);

        $this->logInfo('Scheduled backup job queued', ['schedule' => $settings->backupSchedule]);
    }

    /**
     * Cancel any existing scheduled backup jobs
     */
    private function cancelScheduledBackupJobs(): void
    {
        $db = Craft::$app->getDb();

        // Delete any pending CreateBackupJob from queue
        $db->createCommand()
            ->delete('{{%queue}}', [
                'and',
                ['like', 'job', 'translationmanager'],
                ['like', 'job', 'CreateBackupJob']
            ])
            ->execute();
    }
}
