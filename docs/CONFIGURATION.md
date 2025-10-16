# Translation Manager Configuration

## Configuration File

You can override plugin settings by creating a `translation-manager.php` file in your `config/` directory.

### Basic Setup

1. Copy `vendor/lindemannrock/translation-manager/src/config.php` to `config/translation-manager.php`
2. Modify the settings as needed

### Available Settings

```php
<?php
return [
    // Translation category for |t() filter (default: 'messages')
    'translationCategory' => 'messages',

    // Enable/disable integrations
    'enableFormieIntegration' => true,
    'enableSiteTranslations' => true,

    // Auto-save behavior
    'autoSaveEnabled' => false,
    'autoSaveDelay' => 2, // seconds

    // Interface settings
    'itemsPerPage' => 100,
    'showContext' => false,
    'enableSuggestions' => false,

    // File generation
    'autoExport' => true,
    'exportPath' => '@root/translations',

    // Backup configuration
    'backupEnabled' => true,
    'backupSchedule' => 'manual', // Options: 'manual', 'daily', 'weekly', 'monthly'
    'backupRetentionDays' => 30, // days (0 = keep forever, manual backups always kept)
    'backupOnImport' => true,
    'backupPath' => '@storage/translation-manager/backups',
    'backupVolumeUid' => null, // Optional: Asset volume UID for storing backups

    // Site translation skip patterns
    'skipPatterns' => [
        // 'ID',
        // 'Title',
        // 'Status',
    ],

    // Logging settings
    'logLevel' => 'error', // Options: 'debug', 'info', 'warning', 'error'
];
```

### Multi-Environment Configuration

You can have different settings per environment:

```php
<?php
return [
    // Global settings
    '*' => [
        'translationCategory' => 'messages',
        'enableFormieIntegration' => true,
        'logLevel' => 'error',
    ],

    // Development environment
    'dev' => [
        'autoExport' => false,
        'backupEnabled' => false,
        'logLevel' => 'debug', // Detailed logging for debugging
    ],

    // Staging environment
    'staging' => [
        'autoExport' => true,
        'backupSchedule' => 'daily',
        'logLevel' => 'info',
    ],

    // Production environment
    'production' => [
        'autoExport' => true,
        'backupEnabled' => true,
        'backupSchedule' => 'daily',
        'backupRetentionDays' => 60,
        'backupVolumeUid' => 'your-volume-uid-here', // Use asset volume in production
        'logLevel' => 'warning',
    ],
];
```

### Using Environment Variables

All settings support environment variables:

```php
return [
    'translationCategory' => getenv('TRANSLATION_CATEGORY') ?: 'messages',
    'backupEnabled' => getenv('TRANSLATION_BACKUP_ENABLED') === 'true',
    'backupPath' => getenv('TRANSLATION_BACKUP_PATH') ?: '@storage/translation-manager/backups',
    'backupVolumeUid' => getenv('TRANSLATION_BACKUP_VOLUME_UID') ?: null,
    'logLevel' => getenv('TRANSLATION_LOG_LEVEL') ?: 'error',
];
```

### Setting Descriptions

#### General Settings

- **translationCategory**: The category used for the `|t()` filter in templates (default: 'messages')
- **enableFormieIntegration**: Enable/disable automatic Formie form field capturing
- **enableSiteTranslations**: Enable/disable site translation capturing

#### Interface Settings

- **autoSaveEnabled**: Enable automatic saving when leaving a field (CP only)
- **autoSaveDelay**: Delay in seconds before auto-saving (1-10)
- **itemsPerPage**: Number of translations shown per page (10-500)
- **showContext**: Show where translations are used in the interface
- **enableSuggestions**: Show translation suggestions based on similar existing translations

#### Site Translation Settings

- **skipPatterns**: Array of text patterns to skip when capturing site translations (e.g., ['ID', 'Title', 'Status'])

#### Export Settings

- **autoExport**: Automatically generate translation files when translations are saved
- **exportPath**: Path where translation files are generated (supports aliases)
- **generatedFileHeader**: Header comment in generated files (`{date}` is replaced)

#### Backup Settings

- **backupEnabled**: Enable/disable the backup system
- **backupSchedule**: How often to create automatic backups ('manual', 'daily', 'weekly', 'monthly')
- **backupRetentionDays**: How many days to keep automatic backups (0 = keep forever, manual backups always kept)
- **backupOnImport**: Create backup before importing translations
- **backupPath**: Where to store backup files when not using a volume (supports aliases)
- **backupVolumeUid**: Asset volume UID for storing backups (optional, overrides backupPath)

#### Logging Settings

- **logLevel**: What types of messages to log ('debug', 'info', 'warning', 'error')
  - **error**: Critical errors only (default, production recommended)
  - **warning**: Errors and warnings
  - **info**: General information and successful operations
  - **debug**: Detailed debugging information (development only, requires devMode)

**Note**: Backups are organized into subfolders:
- `/scheduled/` - Automated daily/weekly/monthly backups
- `/imports/` - Backups created before CSV imports
- `/maintenance/` - Backups created before cleanup or clear operations
- `/manual/` - User-initiated backups (never auto-deleted)
- `/other/` - Any other backups

Scheduled backups use Craft's queue system and automatically restart if the queue is cleared.

#### Import Settings

- **importSizeLimit**: Maximum number of translations in a single import
- **importBatchSize**: How many translations to process per batch during import

### Path Aliases

The following aliases are supported in path settings:

**Export Paths (secure aliases only):**
- `@root` - Project root directory
- `@storage` - Storage directory (non-web-accessible)
- `@translations` - Translations directory

**Backup Paths (secure aliases only):**
- `@root` - Project root directory
- `@storage` - Storage directory (non-web-accessible)

**Security Note:** `@webroot` and `@config` are not allowed for security reasons.

### Precedence

Settings are loaded in this order (later overrides earlier):

1. Default plugin settings
2. Database-stored settings (from CP)
3. Config file settings
4. Environment-specific config settings

### Changing the Plugin Name

To change how the plugin appears in the CP navigation:

```php
// In config/app.php
return [
    'modules' => [
        'translation-manager' => [
            'class' => \lindemannrock\translationmanager\TranslationManager::class,
            'settings' => [
                'pluginName' => 'Translations', // Custom name
            ],
        ],
    ],
];
```

Or in a custom module:

```php
Event::on(
    TranslationManager::class,
    TranslationManager::EVENT_INIT,
    function() {
        TranslationManager::getInstance()->name = 'My Translations';
    }
);
```
