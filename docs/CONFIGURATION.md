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
    // Translation category for |t() filter (default: 'alhatab')
    'translationCategory' => 'site',

    // Enable/disable integrations
    'enableFormieIntegration' => true,
    'enableSiteTranslations' => true,

    // Auto-save behavior
    'autoSaveEnabled' => false,
    'autoSaveDelay' => 2, // seconds

    // Display settings
    'itemsPerPage' => 100,
    'showContext' => true,

    // File generation
    'autoExport' => true,
    'exportPath' => '@root/translations',
    'generatedFileHeader' => 'Auto-generated: {date}',

    // Backup configuration
    'backupEnabled' => true,
    'backupSchedule' => 'daily', // Options: 'manual', 'daily', 'weekly', 'monthly'
    'backupRetentionDays' => 30, // days (0 = keep forever, manual backups always kept)
    'backupOnImport' => true,
    'backupPath' => '@storage/translation-manager/backups',

    // Security settings
    'exportPath' => '@translations', // Secure aliases only: @root, @storage, @translations
    'backupPath' => '@storage/backups', // Secure aliases only: @root, @storage (never web-accessible)

    // Import settings
    'importSizeLimit' => 5000, // max translations per import
    'importBatchSize' => 50, // translations per batch

    // Deduplication
    'enableDeduplication' => true,
];
```

### Multi-Environment Configuration

You can have different settings per environment:

```php
<?php
return [
    // Global settings
    '*' => [
        'translationCategory' => 'site',
        'enableFormieIntegration' => true,
    ],
    
    // Development environment
    'dev' => [
        'autoExport' => false,
        'backupEnabled' => false,
    ],
    
    // Staging environment
    'staging' => [
        'autoExport' => true,
        'backupSchedule' => 'daily',
    ],
    
    // Production environment
    'production' => [
        'autoExport' => true,
        'backupEnabled' => true,
        'backupSchedule' => 'hourly',
        'backupRetention' => 60,
    ],
];
```

### Using Environment Variables

All settings support environment variables:

```php
return [
    'translationCategory' => getenv('TRANSLATION_CATEGORY') ?: 'site',
    'backupEnabled' => getenv('TRANSLATION_BACKUP_ENABLED') === 'true',
    'backupPath' => getenv('TRANSLATION_BACKUP_PATH') ?: '@storage/backups',
];
```

### Setting Descriptions

#### General Settings

- **translationCategory**: The category used for the `|t()` filter in templates
- **enableFormieIntegration**: Enable/disable automatic Formie form field capturing
- **enableSiteIntegration**: Enable/disable site translation capturing

#### Display Settings

- **autoSave**: Enable automatic saving when leaving a field (CP only)
- **autoSaveDelay**: Delay in milliseconds before auto-saving
- **itemsPerPage**: Number of translations shown per page
- **showContext**: Show where translations are used

#### Export Settings

- **autoExport**: Automatically generate translation files when translations are saved
- **exportPath**: Path where translation files are generated (supports aliases)
- **generatedFileHeader**: Header comment in generated files (`{date}` is replaced)

#### Backup Settings

- **backupEnabled**: Enable/disable the backup system
- **backupSchedule**: How often to create automatic backups ('manual', 'daily', 'weekly', 'monthly')
- **backupRetention**: How many days to keep backups (0 = keep forever, manual backups always kept)
- **backupBeforeImport**: Create backup before importing translations
- **backupBeforeRestore**: Create backup before restoring from another backup
- **backupPath**: Where to store backup files (supports aliases)

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