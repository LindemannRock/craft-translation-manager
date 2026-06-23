<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Localize your Craft interface and Formie & Freeform forms across every language — from the Control Panel
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\Gql;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use lindemannrock\base\helpers\ColorHelper;
use lindemannrock\base\helpers\CpNavHelper;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\helpers\RecurringQueueHelper;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\logginglibrary\LoggingLibrary;

use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\gql\queries\TranslationQuery;
use lindemannrock\translationmanager\gql\types\TranslationType as GqlTranslationType;
use lindemannrock\translationmanager\helpers\FeatureGate;
use lindemannrock\translationmanager\i18n\HybridLocaleMappingMessageSource;
use lindemannrock\translationmanager\i18n\LocaleMappingDbMessageSource;
use lindemannrock\translationmanager\i18n\LocaleMappingPhpMessageSource;
use lindemannrock\translationmanager\i18n\MergedLocaleMappingPhpMessageSource;
use lindemannrock\translationmanager\jobs\CreateBackupJob;
use lindemannrock\translationmanager\listeners\MissingTranslationListener;
use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\services\AiTranslationService;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\services\GenerationService;
use lindemannrock\translationmanager\services\GenerationStatusService;
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\services\SourceService;
use lindemannrock\translationmanager\services\TranslationsService;
use lindemannrock\translationmanager\utilities\TranslationStatsUtility;
use lindemannrock\translationmanager\variables\TranslationManagerVariable;
use yii\base\Application as YiiApplication;
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
 * @property-read AiTranslationService $ai
 * @property-read GenerationService $generate
 * @property-read GenerationStatusService $generationStatus
 * @property-read BackupService $backup
 * @property-read SourceService $sources
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
     * @var bool Whether the plugin settings page is accessible when allowAdminChanges is false
     */
    public bool $hasReadOnlyCpSettings = true;

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
     */
    public static function config(): array
    {
        return [
            'components' => [
                'translations' => TranslationsService::class,
                'ai' => AiTranslationService::class,
                'generate' => GenerationService::class,
                'generationStatus' => GenerationStatusService::class,
                'backup' => BackupService::class,
                'integrations' => IntegrationService::class,
                'sources' => SourceService::class,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Register the Twig variable as early as possible in the request lifecycle.
        $this->registerTemplateVariable();

        // Bootstrap shared plugin functionality (Twig helper, logging)
        PluginHelper::bootstrap(
            $this,
            'translationHelper',
            ['translationManager:viewSystemLogs'],
            ['translationManager:downloadSystemLogs'],
            [
                'installExperience' => [
                    'headline' => Craft::t('translation-manager', 'Translation Manager'),
                    'body' => Craft::t('translation-manager', 'Manage translations, exports, and backups from one control panel workspace.'),
                    'ctaLabel' => Craft::t('translation-manager', 'Open Translation Manager'),
                    'ctaUrl' => 'translation-manager',
                    'redirectUri' => 'translation-manager',
                    'confettiPreset' => 'surprise',
                ],
                'colorSets' => [
                    'translationStatus' => [
                        'pending' => ColorHelper::getPaletteColor('orange'),
                        'draft' => ColorHelper::getPaletteColor('blue'),
                        'translated' => ColorHelper::getPaletteColor('green'),
                        'unused' => ColorHelper::getPaletteColor('gray'),
                    ],
                    // Type filter — must NOT clash with status colors.
                    'translationTypes' => [
                        'forms' => ColorHelper::getPaletteColor('indigo'),
                        'site' => ColorHelper::getPaletteColor('cyan'),
                    ],
                    // Origin filter + badge — must NOT clash with status or type
                    // (above) and must avoid green/red (reserved for active/no).
                    'translationOrigins' => [
                        'ai' => ColorHelper::getPaletteColor('purple'),
                        'manual' => ColorHelper::getPaletteColor('pink'),
                        'import' => ColorHelper::getPaletteColor('fuchsia'),
                        'system' => ColorHelper::getPaletteColor('sky'),
                    ],
                ],
            ]
        );
        PluginHelper::applyPluginNameFromConfig($this);

        // Register services
        $this->setComponents([
            'translations' => TranslationsService::class,
            'ai' => AiTranslationService::class,
            'generate' => GenerationService::class,
            'generationStatus' => GenerationStatusService::class,
            'backup' => BackupService::class,
            'integrations' => IntegrationService::class,
            'sources' => SourceService::class,
        ]);

        $this->registerGraphql();

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
                /** @var SourceService $sourceService */
                $sourceService = $this->get('sources');
                $editSourcePermissions = [];
                $approveSourcePermissions = [];
                $deleteUnusedSourcePermissions = [];
                $captureTranslationPermissions = [];
                $generateSourcePermissions = [];
                $deleteSourcePermissions = [];

                foreach ($sourceService->getAllSources() as $source) {
                    $editSourcePermissions[$sourceService->getSourcePermission(SourceService::ACTION_EDIT, $source->id)] = [
                        'label' => Craft::t('translation-manager', 'Edit {name} translations', ['name' => $source->label]),
                    ];
                    $approveSourcePermissions[$sourceService->getSourcePermission(SourceService::ACTION_APPROVE, $source->id)] = [
                        'label' => Craft::t('translation-manager', 'Approve {name} translations', ['name' => $source->label]),
                    ];
                    $deleteUnusedSourcePermissions[$sourceService->getSourcePermission(SourceService::ACTION_DELETE_UNUSED, $source->id)] = [
                        'label' => Craft::t('translation-manager', 'Delete unused {name} translations', ['name' => $source->label]),
                    ];
                    $captureTranslationPermissions[$sourceService->getSourcePermission(SourceService::ACTION_CAPTURE, $source->id)] = [
                        'label' => Craft::t('translation-manager', 'Capture {name} translations', ['name' => $source->label]),
                    ];
                    $generateSourcePermissions[$sourceService->getSourcePermission(SourceService::ACTION_GENERATE, $source->id)] = [
                        'label' => Craft::t('translation-manager', 'Generate {name} files', ['name' => $source->label]),
                    ];
                    $deleteSourcePermissions[$sourceService->getSourcePermission(SourceService::ACTION_DELETE, $source->id)] = [
                        'label' => Craft::t('translation-manager', 'Delete {name} translations', ['name' => $source->label]),
                    ];
                }

                $event->permissions[] = [
                    'heading' => $fullName,
                    'permissions' => [
                        // Translations - grouped
                        'translationManager:manageTranslations' => [
                            'label' => Craft::t('translation-manager', 'Manage {plural}', ['plural' => $plural]),
                            'nested' => [
                                'translationManager:editTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Edit {plural}', ['plural' => $plural]),
                                    'nested' => [
                                        $sourceService->getAllPermission(SourceService::ACTION_EDIT) => [
                                            'label' => Craft::t('translation-manager', 'Edit all translations'),
                                        ],
                                    ] + $editSourcePermissions,
                                ],
                                'translationManager:approveTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Approve {plural}', ['plural' => $plural]),
                                    'nested' => [
                                        $sourceService->getAllPermission(SourceService::ACTION_APPROVE) => [
                                            'label' => Craft::t('translation-manager', 'Approve all translations'),
                                        ],
                                    ] + $approveSourcePermissions,
                                ],
                            ],
                        ],
                        'translationManager:manageImportExport' => [
                            'label' => Craft::t('translation-manager', 'Manage Import/Export'),
                            'nested' => [
                                'translationManager:importTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Import {plural}', ['plural' => $plural]),
                                ],
                                'translationManager:exportTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Export {plural}', ['plural' => $plural]),
                                ],
                                'translationManager:clearImportHistory' => [
                                    'label' => Craft::t('translation-manager', 'Clear import history'),
                                ],
                            ],
                        ],
                        'translationManager:generateTranslations' => [
                            'label' => Craft::t('translation-manager', 'Generate {name} files', ['name' => $settings->getLowerDisplayName()]),
                            'nested' => [
                                $sourceService->getAllPermission(SourceService::ACTION_GENERATE) => [
                                    'label' => Craft::t('translation-manager', 'Generate all source files'),
                                ],
                            ] + $generateSourcePermissions,
                        ],
                        'translationManager:manageBackups' => [
                            'label' => Craft::t('translation-manager', 'Manage Backups'),
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
                            'label' => Craft::t('translation-manager', 'Perform Maintenance'),
                            'nested' => [
                                'translationManager:captureTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Capture translations'),
                                    'nested' => [
                                        $sourceService->getAllPermission(SourceService::ACTION_CAPTURE) => [
                                            'label' => Craft::t('translation-manager', 'Capture all translations'),
                                        ],
                                    ] + $captureTranslationPermissions,
                                ],
                                'translationManager:cleanMaintenanceArtifacts' => [
                                    'label' => Craft::t('translation-manager', 'Clean artifacts'),
                                ],
                                'translationManager:deleteUnusedTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Delete unused {plural}', ['plural' => $plural]),
                                    'nested' => [
                                        $sourceService->getAllPermission(SourceService::ACTION_DELETE_UNUSED) => [
                                            'label' => Craft::t('translation-manager', 'Delete all unused translations'),
                                        ],
                                    ] + $deleteUnusedSourcePermissions,
                                ],
                                'translationManager:deleteSourceTranslations' => [
                                    'label' => Craft::t('translation-manager', 'Delete {plural}', ['plural' => $plural]),
                                    'nested' => [
                                        $sourceService->getAllPermission(SourceService::ACTION_DELETE) => [
                                            'label' => Craft::t('translation-manager', 'Delete all translations'),
                                        ],
                                    ] + $deleteSourcePermissions,
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

        // Register message source for translation category
        if ($this->getSettings()->enableSiteTranslations) {
            $this->registerFileMessageSource();
            Event::on(
                YiiApplication::class,
                YiiApplication::EVENT_BEFORE_REQUEST,
                function(): void {
                    if ($this->getSettings()->enableSiteTranslations) {
                        $this->registerFileMessageSource();
                    }
                }
            );
        }

        // Register missing translation capture (runtime auto-capture)
        if ($this->getSettings()->captureMissingTranslations) {
            $this->registerMissingTranslationListener();
        }

        // Trigger integration service initialization (lightweight event handler registration)
        $this->get('integrations');

        // Register console controllers
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'lindemannrock\translationmanager\console\controllers';
        }

        // Schedule backup job if enabled
        $this->scheduleBackupJob();
    }

    /**
     * Register Translation Manager GraphQL types, queries, and schema permissions.
     */
    private function registerGraphql(): void
    {
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_TYPES,
            static function(RegisterGqlTypesEvent $event) {
                $event->types[] = GqlTranslationType::class;
            }
        );

        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_QUERIES,
            static function(RegisterGqlQueriesEvent $event) {
                foreach (TranslationQuery::getQueries() as $key => $value) {
                    $event->queries[$key] = $value;
                }
            }
        );

        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS,
            static function(RegisterGqlSchemaComponentsEvent $event) {
                if (self::$plugin === null) {
                    return;
                }

                $pluginName = self::$plugin->getSettings()->getFullName();

                $event->queries[$pluginName]['translationManager.all:read'] = [
                    'label' => Craft::t('translation-manager', 'Query {name} data', ['name' => $pluginName]),
                ];
            }
        );
    }

    /**
     * Register the translationManager Twig variable.
     *
     * This should only attach the CraftVariable init listener. Forcing Twig
     * globals during plugin init can instantiate Twig before Craft is ready.
     */
    private function registerTemplateVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                $variable = $event->sender;
                $variable->set('translationManager', TranslationManagerVariable::class);
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        if ($item) {
            // Use dynamic plugin name from settings
            $settings = $this->getSettings();
            $item['label'] = $settings->getFullName();

            $user = Craft::$app->getUser();
            $sections = $this->getCpSections($settings);
            $item['subnav'] = CpNavHelper::buildSubnav($user, $settings, $sections);

            // Add logs section using the logging library
            if (PluginHelper::isPluginEnabled('logging-library')) {
                $item = LoggingLibrary::addLogsNav($item, $this->handle, [
                    'translationManager:viewSystemLogs',
                ]);
            }

            if (Craft::$app->getUser()->checkPermission('translationManager:editSettings')) {
                $item['subnav']['settings'] = [
                    'label' => Craft::t('translation-manager', 'Settings'),
                    'url' => 'translation-manager/settings',
                    'match' => 'translation-manager/settings*', // Match all settings pages
                ];
            }

            // Hide from nav if no accessible subnav items
            if (empty($item['subnav'])) {
                return null;
            }
        }

        return $item;
    }

    /**
     * Get CP sections for nav + default route resolution
     *
     * @param Settings $settings
     * @param bool $includeTranslations
     * @param bool $includeLogs
     * @return array
     * @since 5.21.0
     */
    public function getCpSections(Settings $settings, bool $includeTranslations = true, bool $includeLogs = false): array
    {
        $sections = [];
        /** @var SourceService $sourceService */
        $sourceService = $this->get('sources');

        if ($includeTranslations) {
            $sections[] = [
                'key' => 'translations',
                'label' => Craft::t('translation-manager', 'Translations'),
                'url' => 'translation-manager',
                'permissionsAll' => ['translationManager:manageTranslations'],
            ];
        }

        $generatePermissions = [
            $sourceService->getAllPermission(SourceService::ACTION_GENERATE),
        ];
        foreach ($sourceService->getAllSources() as $source) {
            $generatePermissions[] = $sourceService->getSourcePermission(SourceService::ACTION_GENERATE, $source->id);
        }

        $sections[] = [
            'key' => 'generate',
            'label' => Craft::t('translation-manager', 'Generate'),
            'url' => 'translation-manager/generate',
            'permissionsAny' => $generatePermissions,
        ];

        $sections[] = [
            'key' => 'import-export',
            'label' => Craft::t('translation-manager', 'Import/Export'),
            'url' => 'translation-manager/import-export',
            'permissionsAll' => ['translationManager:manageImportExport'],
        ];

        $maintenancePermissions = [
            'translationManager:cleanMaintenanceArtifacts',
            $sourceService->getAllPermission(SourceService::ACTION_CAPTURE),
            $sourceService->getAllPermission(SourceService::ACTION_DELETE_UNUSED),
            $sourceService->getAllPermission(SourceService::ACTION_DELETE),
        ];
        foreach ($sourceService->getAllSources() as $source) {
            $maintenancePermissions[] = $sourceService->getSourcePermission(SourceService::ACTION_CAPTURE, $source->id);
            $maintenancePermissions[] = $sourceService->getSourcePermission(SourceService::ACTION_DELETE_UNUSED, $source->id);
            $maintenancePermissions[] = $sourceService->getSourcePermission(SourceService::ACTION_DELETE, $source->id);
        }

        $sections[] = [
            'key' => 'maintenance',
            'label' => Craft::t('translation-manager', 'Maintenance'),
            'url' => 'translation-manager/maintenance',
            'permissionsAll' => ['translationManager:maintenance'],
            'permissionsAny' => $maintenancePermissions,
        ];

        $sections[] = [
            'key' => 'backups',
            'label' => Craft::t('translation-manager', 'Backups'),
            'url' => 'translation-manager/backups',
            'permissionsAll' => ['translationManager:manageBackups'],
        ];

        if ($includeLogs) {
            $sections[] = [
                'key' => 'logs',
                'label' => Craft::t('translation-manager', 'Logs'),
                'url' => 'translation-manager/logs',
                'permissionsAll' => ['translationManager:viewSystemLogs'],
                'when' => fn() => PluginHelper::isPluginEnabled('logging-library'),
            ];
        }

        $sections[] = [
            'key' => 'settings',
            'label' => Craft::t('translation-manager', 'Settings'),
            'url' => 'translation-manager/settings',
            'permissionsAll' => ['translationManager:editSettings'],
        ];

        return $sections;
    }

    /**
     * @inheritdoc
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
    public function getReadOnlySettingsResponse(): mixed
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

            PluginHelper::applyConfigOverridesToSettings($settings, 'translation-manager');

            // Normalize legacy absolute paths to aliases before strict validation.
            $this->normalizeLegacyPathSettings($settings);
            $settings->runtimeTranslationSource = Settings::normalizeRuntimeTranslationSource($settings->runtimeTranslationSource);

            if (!$settings->validate()) {
                $errors = $settings->getFirstErrors();

                $this->logError('Invalid Translation Manager configuration', [
                    'errors' => $errors,
                    'configSource' => 'database and/or config/translation-manager.php',
                ]);
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
        // No-op: settings come from loadFromDatabase() in getSettings()
        // Reset cached settings so next getSettings() call loads fresh from DB
        $this->_settings = null;
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
     * Get the AI translation service
     *
     * @return AiTranslationService
     * @since 5.22.0
     */
    public function getAi(): AiTranslationService
    {
        FeatureGate::requireAiTranslationsEnabled();

        return $this->get('ai');
    }

    /**
     * Whether the internal AI translation feature is available for this request.
     *
     * @since 5.30.0
     */
    public function isAiFeatureEnabled(): bool
    {
        return FeatureGate::aiTranslationsEnabled();
    }

    private function normalizeLegacyPathSettings(Settings $settings): void
    {
        // Keep bootstrap resilient if a legacy value uses @root directly.
        if ($this->isRootAliasValue($settings->generationPath)) {
            $settings->generationPath = '@translations';
        }
        if ($this->isLegacyRootTranslationsValue($settings->generationPath)) {
            $settings->generationPath = $this->normalizeLegacyRootTranslationsValue($settings->generationPath);
        }
        if ($this->isRootAliasValue($settings->backupPath)) {
            $settings->backupPath = '@root/backups/translation-manager';
        }

        $normalizedGenerationPath = $this->normalizeAbsolutePathToAlias(
            $settings->generationPath,
            ['@translations']
        );
        if ($normalizedGenerationPath !== null) {
            $settings->generationPath = $normalizedGenerationPath;
        }
        if ($this->isRootAliasValue($settings->generationPath)) {
            $settings->generationPath = '@translations';
        }
        if ($this->isLegacyRootTranslationsValue($settings->generationPath)) {
            $settings->generationPath = $this->normalizeLegacyRootTranslationsValue($settings->generationPath);
        }

        $normalizedBackupPath = $this->normalizeAbsolutePathToAlias(
            $settings->backupPath,
            ['@storage', '@root']
        );
        if ($normalizedBackupPath !== null) {
            $settings->backupPath = $normalizedBackupPath;
        }
        if ($this->isRootAliasValue($settings->backupPath)) {
            $settings->backupPath = '@root/backups/translation-manager';
        }
    }

    private function isRootAliasValue(string $value): bool
    {
        $normalized = rtrim(trim($value), "/\\");
        return strcasecmp($normalized, '@root') === 0;
    }

    private function isLegacyRootTranslationsValue(string $value): bool
    {
        $normalized = rtrim(str_replace('\\', '/', trim($value)), '/');
        return strcasecmp($normalized, '@root/translations') === 0
            || str_starts_with(strtolower($normalized), '@root/translations/');
    }

    private function normalizeLegacyRootTranslationsValue(string $value): string
    {
        $normalized = rtrim(str_replace('\\', '/', trim($value)), '/');
        $suffix = substr($normalized, strlen('@root/translations'));

        return '@translations' . $suffix;
    }

    private function normalizeAbsolutePathToAlias(string $path, array $allowedAliases): ?string
    {
        $trimmedPath = trim($path);
        if ($trimmedPath === '' || str_starts_with($trimmedPath, '@')) {
            return null;
        }

        $normalizedPath = rtrim(str_replace('\\', '/', $trimmedPath), '/');

        foreach ($allowedAliases as $alias) {
            $resolvedAlias = Craft::getAlias($alias, false);
            if (!is_string($resolvedAlias) || $resolvedAlias === '') {
                continue;
            }

            $normalizedAlias = rtrim(str_replace('\\', '/', $resolvedAlias), '/');

            if ($normalizedPath === $normalizedAlias) {
                return $alias;
            }

            if (str_starts_with($normalizedPath, $normalizedAlias . '/')) {
                $suffix = substr($normalizedPath, strlen($normalizedAlias));
                return $alias . $suffix;
            }
        }

        return null;
    }

    /**
     * Get all allowed sites
     *
     * @return array
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
     */
    public function isSiteAllowed(int $siteId): bool
    {
        return Craft::$app->getSites()->getSiteById($siteId) !== null;
    }

    /**
     * Get unique canonical language codes from all sites
     *
     * Locale mappings are applied so regional source locales share the
     * configured destination language instead of creating duplicate rows.
     *
     * @return array<string> Array of unique language codes (e.g., ['en', 'ar', 'fr'])
     * @since 5.15.0
     */
    public function getUniqueLanguages(): array
    {
        $languages = [];
        $sites = Craft::$app->getSites()->getAllSites();
        $settings = $this->getSettings();

        foreach ($sites as $site) {
            $language = $settings->mapLanguage($site->language);
            if (!in_array($language, $languages, true)) {
                $languages[] = $language;
            }
        }

        return $languages;
    }

    /**
     * Get site language by site ID
     *
     * @param int $siteId
     * @return string|null
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

            // Generate routes (write PHP translation files to disk)
            'translation-manager/generate' => 'translation-manager/generate/index',
            'translation-manager/generate/files' => 'translation-manager/generate/files',
            'translation-manager/generate/provider-files' => 'translation-manager/generate/provider-files',
            'translation-manager/generate/site-files' => 'translation-manager/generate/site-files',
            'translation-manager/generate/category-files' => 'translation-manager/generate/category-files',

            // Import/Export routes (CSV/XLSX/JSON download for the user)
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

            // Maintenance routes
            'translation-manager/maintenance' => 'translation-manager/maintenance/index',
            'translation-manager/maintenance/clean-unused' => 'translation-manager/maintenance/clean-unused',
            'translation-manager/maintenance/capture-provider' => 'translation-manager/maintenance/capture-provider',

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
        // Note: Translation Manager uses a custom message source and dynamic categories.
        // This intentionally does not use PluginHelper::registerTranslations().
        $i18n = Craft::$app->getI18n();
        $settings = $this->getSettings();
        $categories = $this->getFileMessageSourceCategories();
        $basePath = $settings->getGenerationPath(); // Use the configured generation path

        // Use the configured source language (language your template strings are written in)
        // This should NOT be based on primary site - it's the language of your source strings
        // e.g., if your templates have {{ 'Copyright'|t('category') }}, sourceLanguage should be 'en'
        $sourceLanguage = explode('-', $settings->sourceLanguage)[0]; // e.g., 'en' from 'en-US'

        // Get active locale mappings for the custom message source
        $localeMapping = $settings->getActiveLocaleMapping();

        // Register message source for each enabled category
        foreach ($categories as $category) {
            $pluginTranslationsPath = $this->getPluginTranslationsPath($category);
            $messageSourceClass = $this->getRuntimeMessageSourceClass($settings, $pluginTranslationsPath);

            $i18n->translations[$category] = [
                'class' => $messageSourceClass,
                'sourceLanguage' => $sourceLanguage, // Based on configured setting, not primary site
                'basePath' => $basePath,
                'forceTranslation' => true, // Force translation even for same language
                'fileMap' => [
                    $category => $category . '.php',
                ],
                'localeMapping' => $localeMapping, // Pass the locale mapping configuration
            ];

            if ($pluginTranslationsPath !== null) {
                $i18n->translations[$category]['fallbackBasePath'] = $pluginTranslationsPath;
            }
        }
    }

    /**
     * @return class-string<MessageSource>
     */
    private function getRuntimeMessageSourceClass(Settings $settings, ?string $pluginTranslationsPath): string
    {
        return match ($settings->runtimeTranslationSource) {
            Settings::RUNTIME_SOURCE_DATABASE => LocaleMappingDbMessageSource::class,
            Settings::RUNTIME_SOURCE_HYBRID => HybridLocaleMappingMessageSource::class,
            default => $pluginTranslationsPath === null
                ? LocaleMappingPhpMessageSource::class
                : MergedLocaleMappingPhpMessageSource::class,
        };
    }

    /**
     * Return all categories whose generated files should be visible to Craft i18n.
     *
     * This includes normal configured site categories plus enabled integration
     * categories such as Formie and Freeform, even when those provider
     * categories are not also configured as manual template categories.
     *
     * @return string[]
     */
    private function getFileMessageSourceCategories(): array
    {
        $categories = $this->getSettings()->getEnabledCategories();

        /** @var IntegrationService $integrationService */
        $integrationService = $this->get('integrations');
        foreach ($integrationService->getEnabledIntegrations() as $integration) {
            $categories[] = $integration->getCategory();
        }

        return array_values(array_unique(array_filter($categories)));
    }

    /**
     * Return a plugin's native translations path when the category is an enabled plugin handle.
     */
    private function getPluginTranslationsPath(string $category): ?string
    {
        if (!PluginHelper::isPluginEnabled($category)) {
            return null;
        }

        $plugin = PluginHelper::getPlugin($category);
        if (!$plugin instanceof Plugin) {
            return null;
        }

        $path = $plugin->getBasePath() . DIRECTORY_SEPARATOR . 'translations';
        return is_dir($path) ? $path : null;
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
        $this->queueBackupJob($this->getSettings());
    }

    /**
     * Queue the next scheduled backup row for the provided settings.
     */
    private function queueBackupJob(Settings $settings): void
    {
        $schedule = $settings->getEffectiveBackupSchedule();

        if (!$settings->backupEnabled || $schedule === 'disabled') {
            return;
        }

        $nextRun = ScheduleHelper::calculateNext($schedule);
        $delay = ScheduleHelper::calculateDelaySeconds($schedule);

        if ($nextRun === null || $delay <= 0) {
            return;
        }

        $nextRunTime = DateFormatHelper::formatCompactDatetimeFromSettings(
            $nextRun,
            $settings,
            null,
            false,
            pluginHandle: 'translation-manager',
        );

        RecurringQueueHelper::ensurePending(
            pluginToken: 'translationmanager',
            jobClass: CreateBackupJob::class,
            delay: $delay,
            jobFactory: fn() => new CreateBackupJob([
                'reason' => 'scheduled',
                'reschedule' => true,
                'nextRunTime' => $nextRunTime,
            ]),
        );
    }

    /**
     * Handle backup schedule changes when settings are saved
     */
    public function handleBackupScheduleChange(Settings $settings, ?bool $oldBackupEnabled = null, ?string $oldBackupSchedule = null): void
    {
        $schedule = $settings->getEffectiveBackupSchedule();

        if (
            $oldBackupEnabled !== null &&
            $oldBackupSchedule !== null &&
            $oldBackupEnabled === $settings->backupEnabled &&
            $this->normalizeBackupSchedule($oldBackupSchedule) === $schedule
        ) {
            return;
        }

        $this->cancelScheduledBackupJobs();

        if (!$settings->backupEnabled || $schedule === 'disabled') {
            $this->logInfo('Backup scheduling disabled');
            return;
        }

        $this->queueBackupJob($settings);
    }

    /**
     * Cancel any existing scheduled backup jobs
     */
    private function cancelScheduledBackupJobs(): void
    {
        RecurringQueueHelper::deletePending('translationmanager', CreateBackupJob::class);
    }

    /**
     * Normalize backup schedule values.
     */
    private function normalizeBackupSchedule(string $schedule): string
    {
        $settings = new Settings();
        $settings->backupSchedule = $schedule;

        return $settings->getEffectiveBackupSchedule();
    }
}
