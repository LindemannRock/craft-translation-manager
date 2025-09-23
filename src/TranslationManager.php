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
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    /**
     * @var Settings|null
     */
    private ?Settings $_settings = null;
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

        // Configure logging using the new logging library
        $settings = $this->getSettings();
        $logLevel = $settings->logLevel ?? 'info';
        error_log("TRANSLATION-MANAGER: Settings logLevel = " . ($settings->logLevel ?? 'NULL') . ", using: $logLevel");

        LoggingLibrary::configure([
            'pluginHandle' => $this->handle,
            'pluginName' => $this->name,
            'logLevel' => $logLevel,
            'enableLogViewer' => true,
            'permissions' => ['translationManager:viewTranslations'],
        ]);

        // DEBUG TEST: Log at all levels to see what appears
        $testTime = date('H:i:s');
        Craft::debug("DEBUG TEST at $testTime", 'translation-manager');
        Craft::info("INFO TEST at $testTime", 'translation-manager');
        Craft::warning("WARNING TEST at $testTime", 'translation-manager');
        Craft::error("ERROR TEST at $testTime", 'translation-manager');

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
                $event->rules['translation-manager'] = 'translation-manager/translations/index';
                $event->rules['translation-manager/translations'] = 'translation-manager/translations/index';
                $event->rules['translation-manager/translations/save'] = 'translation-manager/translations/save';
                $event->rules['translation-manager/translations/save-all'] = 'translation-manager/translations/save-all';
                $event->rules['translation-manager/translations/delete'] = 'translation-manager/translations/delete';
                $event->rules['translation-manager/export'] = 'translation-manager/export/index';
                $event->rules['translation-manager/export/download'] = 'translation-manager/export/download';
                $event->rules['translation-manager/export/selected'] = 'translation-manager/export/selected';
                $event->rules['translation-manager/export/files'] = 'translation-manager/export/files';
                $event->rules['translation-manager/export/formie-files'] = 'translation-manager/export/formie-files';
                $event->rules['translation-manager/export/site-files'] = 'translation-manager/export/site-files';
                $event->rules['translation-manager/import'] = 'translation-manager/import/index';
                $event->rules['translation-manager/import/check-existing'] = 'translation-manager/import/check-existing';
                $event->rules['translation-manager/import/history'] = 'translation-manager/import/history';
                $event->rules['translation-manager/import/clear-logs'] = 'translation-manager/import/clear-logs';
                $event->rules['translation-manager/settings'] = 'translation-manager/settings/index';
                $event->rules['translation-manager/settings/general'] = 'translation-manager/settings/general';
                $event->rules['translation-manager/settings/generation'] = 'translation-manager/settings/generation';
                $event->rules['translation-manager/settings/import-export'] = 'translation-manager/settings/import-export';
                $event->rules['translation-manager/settings/maintenance'] = 'translation-manager/settings/maintenance';
                $event->rules['translation-manager/settings/backup'] = 'translation-manager/settings/backup';
                $event->rules['translation-manager/settings/save'] = 'translation-manager/settings/save';
                $event->rules['translation-manager/settings/apply-skip-patterns'] = 'translation-manager/settings/apply-skip-patterns';
                $event->rules['translation-manager/settings/clear-formie'] = 'translation-manager/settings/clear-formie';
                $event->rules['translation-manager/settings/clear-site'] = 'translation-manager/settings/clear-site';
                $event->rules['translation-manager/settings/clear-all'] = 'translation-manager/settings/clear-all';
                $event->rules['translation-manager/maintenance/clean-unused'] = 'translation-manager/maintenance/clean-unused';
                $event->rules['translation-manager/maintenance/debug-search-page'] = 'translation-manager/maintenance/debug-search-page';
                $event->rules['translation-manager/maintenance/debug-search'] = 'translation-manager/maintenance/debug-search';
                $event->rules['translation-manager/maintenance/recapture-formie'] = 'translation-manager/maintenance/recapture-formie';

                // Debug route
                $event->rules['translation-manager/debug/test-search'] = 'translation-manager/debug/test-search';
                

                // Logs routes - use logging-library controller
                $event->rules['translation-manager/logs'] = 'logging-library/logs/index';
                $event->rules['translation-manager/logs/download'] = 'logging-library/logs/download';

                // Backup routes (also register as both plural and singular for compatibility)
                $event->rules['translation-manager/backups'] = 'translation-manager/backup/index';
                $event->rules['translation-manager/backup/create'] = 'translation-manager/backup/create';
                $event->rules['translation-manager/backup/restore'] = 'translation-manager/backup/restore';
                $event->rules['translation-manager/backup/delete'] = 'translation-manager/backup/delete';
                $event->rules['translation-manager/backup/download'] = 'translation-manager/backup/download';
                $event->rules['translation-manager/backups/create'] = 'translation-manager/backup/create';
                $event->rules['translation-manager/backups/restore'] = 'translation-manager/backup/restore';
                $event->rules['translation-manager/backups/delete'] = 'translation-manager/backup/delete';
                $event->rules['translation-manager/backups/download'] = 'translation-manager/backup/download';
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
            $item['label'] = $this->getSettings()->pluginName;

            // Use Craft's built-in language icon (same as the module used)
            $item['icon'] = '@app/icons/language.svg';

            // Always add the main translations item
            $item['subnav'] = [
                'translations' => [
                    'label' => 'Translations',
                    'url' => 'translation-manager',
                ],
            ];

            // Add logs section using the logging library (only if installed)
            if (Craft::$app->getPlugins()->isPluginInstalled('logging-library') &&
                Craft::$app->getPlugins()->isPluginEnabled('logging-library')) {
                $item = LoggingLibrary::addLogsNav($item, $this->handle, [
                    'translationManager:viewTranslations'
                ]);
            }

            if (Craft::$app->getUser()->checkPermission('translationManager:editSettings')) {
                $item['subnav']['settings'] = [
                    'label' => 'Settings',
                    'url' => 'translation-manager/settings',
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
            
            // Override with config file values
            $configPath = Craft::$app->getPath()->getConfigPath() . '/translation-manager.php';
            if (file_exists($configPath)) {
                $rawConfig = require $configPath;
                
                // Apply root-level config values
                foreach ($rawConfig as $key => $value) {
                    // Skip environment keys
                    if (!in_array($key, ['*', 'dev', 'staging', 'production', 'test'])) {
                        if (property_exists($settings, $key)) {
                            $settings->$key = $value;
                        }
                    }
                }
                
                // Apply environment-specific overrides
                $env = Craft::$app->getConfig()->getGeneral()->env ?? '*';
                if (isset($rawConfig[$env]) && is_array($rawConfig[$env])) {
                    foreach ($rawConfig[$env] as $key => $value) {
                        if (property_exists($settings, $key)) {
                            $settings->$key = $value;
                        }
                    }
                }
            }
            
            // CRITICAL: Validate settings even when loaded from config
            // This prevents config files from bypassing security validation
            if (!$settings->validate()) {
                $errors = $settings->getFirstErrors();
                $errorMessage = 'Invalid Translation Manager configuration: ' . implode(', ', $errors);
                
                Craft::error($errorMessage, __METHOD__);
                
                // For security, throw exception rather than silently using invalid config
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
            ->where(['like', 'job', 'CreateBackupJob'])
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

            Craft::info(
                sprintf('Scheduled initial backup job to run in 5 minutes (%s schedule)', $settings->backupSchedule),
                'translation-manager'
            );
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
            Craft::info('Backup scheduling disabled', 'translation-manager');
            return;
        }

        // Check if there's already a scheduled backup job in the queue
        $existingJob = (new \craft\db\Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', '%CreateBackupJob%'])
            ->andWhere(['fail' => false])
            ->andWhere(['timePushed' => null])
            ->exists();

        if ($existingJob) {
            Craft::info('Scheduled backup job already exists, not creating a new one', 'translation-manager');
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

        Craft::info('Scheduled backup job queued for ' . $settings->backupSchedule . ' schedule', 'translation-manager');
    }

    /**
     * Cancel any existing scheduled backup jobs
     */
    private function cancelScheduledBackupJobs(): void
    {
        $db = Craft::$app->getDb();

        // Delete any pending CreateBackupJob from queue
        $db->createCommand()
            ->delete('{{%queue}}', ['like', 'job', '%CreateBackupJob%'])
            ->execute();
    }
}
