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
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\LoggingLibrary;

use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\i18n\LocaleMappingPhpMessageSource;
use lindemannrock\translationmanager\listeners\MissingTranslationListener;
use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\services\ExportService;
use lindemannrock\translationmanager\services\FormieService;
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\services\TranslationsService;
use lindemannrock\translationmanager\utilities\TranslationStatsUtility;
use lindemannrock\translationmanager\variables\TranslationManagerVariable;
use yii\base\Event;
use yii\i18n\MessageSource;

/**
 * Translation Manager plugin
 *
 * @author LindemannRock
 * @copyright LindemannRock
 * @license Proprietary
 * @since 1.0.0
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


    /**
     * @inheritdoc
     * @since 1.0.0
     */
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

    /**
     * @inheritdoc
     * @since 1.0.0
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Bootstrap shared plugin functionality (Twig helper, logging)
        PluginHelper::bootstrap(
            $this,
            'translationHelper',
            ['translationManager:viewSystemLogs'],
            ['translationManager:downloadSystemLogs']
        );
        PluginHelper::applyPluginNameFromConfig($this);

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
            function(RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, $this->getCpUrlRules());
            }
        );

        // Register permissions
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $settings = $this->getSettings();
                $fullName = $settings->getFullName();
                $plural = $settings->getPluralLowerDisplayName();
                $formieName = self::getFormiePluginName();

                $event->permissions[] = [
                    'heading' => $fullName,
                    'permissions' => [
                        // Translations - grouped
                        'translationManager:manageTranslations' => [
                            'label' => Craft::t('translation-manager', 'Manage {plural}', ['plural' => $plural]),
                            'nested' => [
                                'translationManager:viewTranslations' => [
                                    'label' => Craft::t('translation-manager', 'View {plural}', ['plural' => $plural]),
                                ],
                                'translationManager:editTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Edit {plural}', ['plural' => $plural]),
                                ],
                                'translationManager:deleteTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Delete unused {plural}', ['plural' => $plural]),
                                ],
                            ],
                        ],
                        'translationManager:manageImportExport' => [
                            'label' => Craft::t('translation-manager', 'Manage import/export'),
                            'nested' => [
                                'translationManager:importTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Import {plural}', ['plural' => $plural]),
                                ],
                                'translationManager:exportTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Export {plural}', ['plural' => $plural]),
                                ],
                                'translationManager:viewImportHistory' => [
                                    'label' => Craft::t('translation-manager', 'View import history'),
                                ],
                                'translationManager:clearImportHistory' => [
                                    'label' => Craft::t('translation-manager', 'Clear import history'),
                                ],
                            ],
                        ],
                        'translationManager:generateTranslations' => [
                            'label' => Craft::t('translation-manager', 'Generate {name} files', ['name' => $settings->getLowerDisplayName()]),
                            'nested' => [
                                'translationManager:generateAllTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Generate all files'),
                                ],
                                'translationManager:generateFormieTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Generate {name} files', ['name' => $formieName]),
                                ],
                                'translationManager:generateSiteTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Generate site files'),
                                ],
                            ],
                        ],
                        'translationManager:manageBackups' => [
                            'label' => Craft::t('translation-manager', 'Manage backups'),
                            'nested' => [
                                'translationManager:createBackups' => [
                                    'label' => Craft::t('translation-manager', 'Create backups'),
                                ],
                                'translationManager:downloadBackups' => [
                                    'label' => Craft::t('translation-manager', 'Download backups'),
                                ],
                                'translationManager:restoreBackups' => [
                                    'label' => Craft::t('translation-manager', 'Restore backups'),
                                ],
                                'translationManager:deleteBackups' => [
                                    'label' => Craft::t('translation-manager', 'Delete backups'),
                                ],
                            ],
                        ],
                        'translationManager:maintenance' => [
                            'label' => Craft::t('translation-manager', 'Perform maintenance'),
                            'nested' => [
                                'translationManager:cleanUnused' => [
                                    'label' => Craft::t('translation-manager', 'Clean unused {plural}', ['plural' => $plural]),
                                ],
                                'translationManager:scanTemplates' => [
                                    'label' => Craft::t('translation-manager', 'Scan templates'),
                                ],
                                'translationManager:recaptureFormie' => [
                                    'label' => Craft::t('translation-manager', 'Recapture {name} {plural}', ['name' => $formieName, 'plural' => $plural]),
                                ],
                            ],
                        ],
                        'translationManager:clearTranslations' => [
                            'label' => Craft::t('translation-manager', 'Clear {plural}', ['plural' => $plural]),
                            'nested' => [
                                'translationManager:clearFormie' => [
                                    'label' => Craft::t('translation-manager', 'Clear {name} {plural}', ['name' => $formieName, 'plural' => $plural]),
                                ],
                                'translationManager:clearSite' => [
                                    'label' => Craft::t('translation-manager', 'Clear site {plural}', ['plural' => $plural]),
                                ],
                                'translationManager:clearAll' => [
                                    'label' => Craft::t('translation-manager', 'Clear all {plural}', ['plural' => $plural]),
                                ],
                            ],
                        ],
                        'translationManager:viewLogs' => [
                            'label' => Craft::t('translation-manager', 'View logs'),
                            'nested' => [
                                'translationManager:viewSystemLogs' => [
                                    'label' => Craft::t('translation-manager', 'View system logs'),
                                    'nested' => [
                                        'translationManager:downloadSystemLogs' => [
                                            'label' => Craft::t('translation-manager', 'Download system logs'),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'translationManager:editSettings' => [
                            'label' => Craft::t('translation-manager', 'Edit plugin settings'),
                        ],
                    ],
                ];
            }
        );

        // Register utilities
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = TranslationStatsUtility::class;
            }
        );

        // Register variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                $variable = $event->sender;
                $variable->set('translationManager', TranslationManagerVariable::class);
            }
        );

        // Register message source for translation category
        if ($this->getSettings()->enableSiteTranslations) {
            $this->registerFileMessageSource(); // Use file-based translations
        }

        // Register missing translation capture (runtime auto-capture)
        if ($this->getSettings()->captureMissingTranslations) {
            $this->registerMissingTranslationListener();
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

    /**
     * @inheritdoc
     * @since 1.0.0
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        if ($item) {
            // Use dynamic plugin name from settings
            $item['label'] = $this->getSettings()->getFullName();

            // Use Craft's built-in language icon (same as the module used)
            $item['icon'] = '@app/icons/language.svg';

            $user = Craft::$app->getUser();

            // Check view access to each section
            $hasTranslationsAccess = $user->checkPermission('translationManager:viewTranslations');
            $hasGenerateAccess = $user->checkPermission('translationManager:generateTranslations');
            $hasImportExportAccess = $user->checkPermission('translationManager:manageImportExport') ||
                $user->checkPermission('translationManager:importTranslations') ||
                $user->checkPermission('translationManager:exportTranslations') ||
                $user->checkPermission('translationManager:viewImportHistory') ||
                $user->checkPermission('translationManager:clearImportHistory');
            $hasMaintenanceAccess = $user->checkPermission('translationManager:maintenance') ||
                $user->checkPermission('translationManager:clearTranslations');
            $hasBackupAccess = $user->checkPermission('translationManager:manageBackups');

            $item['subnav'] = [];

            // Add Translations section (requires view permission)
            if ($hasTranslationsAccess) {
                $item['subnav']['translations'] = [
                    'label' => 'Translations',
                    'url' => 'translation-manager',
                ];
            }

            // Add Generate section
            if ($hasGenerateAccess) {
                $item['subnav']['generate'] = [
                    'label' => 'Generate',
                    'url' => 'translation-manager/generate',
                ];
            }

            // Add Import/Export section (requires import or export permission)
            if ($hasImportExportAccess) {
                $item['subnav']['import-export'] = [
                    'label' => 'Import/Export',
                    'url' => 'translation-manager/import-export',
                ];
            }

            // Add Maintenance section
            if ($hasMaintenanceAccess) {
                $item['subnav']['maintenance'] = [
                    'label' => 'Maintenance',
                    'url' => 'translation-manager/maintenance',
                ];
            }

            // Add Backups section
            if ($hasBackupAccess) {
                $item['subnav']['backups'] = [
                    'label' => 'Backups',
                    'url' => 'translation-manager/backups',
                ];
            }

            // Add logs section using the logging library
            if (PluginHelper::isPluginEnabled('logging-library')) {
                $item = LoggingLibrary::addLogsNav($item, $this->handle, [
                    'translationManager:viewSystemLogs',
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

    /**
     * @inheritdoc
     * @since 1.0.0
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(
            UrlHelper::cpUrl('translation-manager/settings')
        );
    }

    /**
     * @inheritdoc
     */
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
            /** @var Settings|null $settings */
            $settings = $this->createSettingsModel();

            // Load database values
            $settings = Settings::loadFromDatabase($settings);

            // Override with config file values using Craft's native multi-environment handling
            // This properly merges '*' with environment-specific configs (e.g., 'production')
            $config = Craft::$app->getConfig()->getConfigFromFile('translation-manager');
            if (!empty($config) && is_array($config)) {
                foreach ($config as $key => $value) {
                    if (property_exists($settings, $key)) {
                        $settings->$key = $value;
                    }
                }
            }

            // CRITICAL: Validate settings even when loaded from config
            // This prevents config files from bypassing security validation
            if (!$settings->validate()) {
                $errors = $settings->getFirstErrors();

                $this->logError('Invalid Translation Manager configuration', [
                    'errors' => $errors,
                    'configFile' => 'config/translation-manager.php',
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
            if ($oldSettings->backupEnabled !== $settings->backupEnabled ||
                $oldSettings->backupSchedule !== $settings->backupSchedule
            ) {
                $this->handleBackupScheduleChange($settings);
            }
        }
    }

    /**
     * Get the backup service
     *
     * @return BackupService
     * @since 1.0.0
     */
    public function getBackup(): BackupService
    {
        return $this->get('backup');
    }

    /**
     * Get all allowed sites
     *
     * @return array
     * @since 1.0.0
     */
    public function getAllowedSites(): array
    {
        return Craft::$app->getSites()->getAllSites();
    }

    /**
     * Check if site is allowed
     *
     * @param int $siteId
     * @return bool
     * @since 1.0.0
     */
    public function isSiteAllowed(int $siteId): bool
    {
        return Craft::$app->getSites()->getSiteById($siteId) !== null;
    }

    /**
     * Get unique language codes from all sites
     *
     * @return array<string> Array of unique language codes (e.g., ['en-US', 'ar', 'fr'])
     * @since 5.15.0
     */
    public function getUniqueLanguages(): array
    {
        $languages = [];
        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            if (!in_array($site->language, $languages, true)) {
                $languages[] = $site->language;
            }
        }

        return $languages;
    }

    /**
     * Get site language by site ID
     *
     * @param int $siteId
     * @return string|null
     * @since 1.0.0
     */
    public function getSiteLanguage(int $siteId): ?string
    {
        $site = Craft::$app->getSites()->getSiteById($siteId);
        return $site?->language;
    }

    /**
     * Get the configured name of the Formie plugin
     *
     * @return string
     * @since 1.0.0
     */
    public static function getFormiePluginName(): string
    {
        return PluginHelper::getPluginName('formie', 'Formie');
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
            'translation-manager/import/upload' => 'translation-manager/import/upload',
            'translation-manager/import/map' => 'translation-manager/import/map',
            'translation-manager/import/preview' => 'translation-manager/import/preview',
            'translation-manager/import/check-existing' => 'translation-manager/import/check-existing',
            'translation-manager/import/history' => 'translation-manager/import/history',
            'translation-manager/import/clear-logs' => 'translation-manager/import/clear-logs',
            'translation-manager/export' => 'translation-manager/export/index',
            'translation-manager/export/download' => 'translation-manager/export/download',
            'translation-manager/export/selected' => 'translation-manager/export/selected',
            'translation-manager/export/files' => 'translation-manager/export/files',
            'translation-manager/export/formie-files' => 'translation-manager/export/formie-files',
            'translation-manager/export/site-files' => 'translation-manager/export/site-files',
            'translation-manager/export/category-files' => 'translation-manager/export/category-files',

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
            'translation-manager/settings/<section:[\w-]+>' => 'translation-manager/settings/<section>',
        ];
    }

    /**
     * Register file-based message source for all enabled translation categories
     */
    private function registerFileMessageSource(): void
    {
        $i18n = Craft::$app->getI18n();
        $settings = $this->getSettings();
        $categories = $settings->getEnabledCategories();
        $basePath = $settings->getExportPath(); // Use the configured export path

        // Use the configured source language (language your template strings are written in)
        // This should NOT be based on primary site - it's the language of your source strings
        // e.g., if your templates have {{ 'Copyright'|t('category') }}, sourceLanguage should be 'en'
        $sourceLanguage = explode('-', $settings->sourceLanguage)[0]; // e.g., 'en' from 'en-US'

        // Get active locale mappings for the custom message source
        $localeMapping = $settings->getActiveLocaleMapping();

        // Register message source for each enabled category
        foreach ($categories as $category) {
            $i18n->translations[$category] = [
                'class' => LocaleMappingPhpMessageSource::class,
                'sourceLanguage' => $sourceLanguage, // Based on configured setting, not primary site
                'basePath' => $basePath,
                'forceTranslation' => true, // Force translation even for same language
                'fileMap' => [
                    $category => $category . '.php',
                ],
                'localeMapping' => $localeMapping, // Pass the locale mapping configuration
            ];
        }
    }

    /**
     * Register listener for missing translations (runtime auto-capture)
     */
    private function registerMissingTranslationListener(): void
    {
        Event::on(
            MessageSource::class,
            MessageSource::EVENT_MISSING_TRANSLATION,
            [MissingTranslationListener::class, 'handle']
        );
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
            ->exists();

        if (!$existingJob) {
            // Calculate delay based on schedule setting
            $delay = match ($settings->backupSchedule) {
                'daily' => 86400, // 24 hours
                'weekly' => 604800, // 7 days
                'monthly' => 2592000, // 30 days
                default => 86400,
            };

            // Create backup job
            $job = new \lindemannrock\translationmanager\jobs\CreateBackupJob([
                'reason' => 'scheduled',
                'reschedule' => true,
            ]);

            // Add to queue with the proper schedule delay
            Craft::$app->getQueue()->delay($delay)->push($job);

            $this->logInfo('Scheduled initial backup job', [
                'delay_seconds' => $delay,
                'schedule' => $settings->backupSchedule,
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
                ['like', 'job', 'CreateBackupJob'],
            ])
            ->execute();
    }
}
