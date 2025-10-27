---
title: Configuration Reference
category: configuration
order: 2
description: Complete configuration file options and environment-specific settings
keywords: config, settings, environment, production, dev
relatedPages:
  - slug: config-overview
    title: Configuration Overview
  - slug: logging-guide
    title: Logging Guide
---

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
    // General Settings
    'pluginName' => 'Translation Manager',
    'logLevel' => 'error', // error, warning, info, or debug

    // Translation Sources
    'enableSiteTranslations' => true,
    'translationCategory' => 'messages',
    'skipPatterns' => [
        // 'ID',
        // 'Title',
        // 'Status',
    ],
    'enableFormieIntegration' => true,

    // File Generation
    'autoExport' => true,
    'exportPath' => '@root/translations',

    // Backup Settings
    'backupEnabled' => true,
    'backupSchedule' => 'manual', // manual, daily, weekly, monthly
    'backupRetentionDays' => 30,
    'backupOnImport' => true,
    'backupPath' => '@storage/translation-manager/backups',
    'backupVolumeUid' => null,

    // Interface Settings
    'itemsPerPage' => 100,
    'showContext' => false,
    'enableSuggestions' => false,

    // Auto-save Settings
    'autoSaveEnabled' => false,
    'autoSaveDelay' => 2,
];
```

### Multi-Environment Configuration

You can have different settings per environment:

```php
<?php
return [
    // Global settings
    '*' => [
        'pluginName' => 'Translation Manager',
        'enableFormieIntegration' => true,
        'logLevel' => 'error',
    ],

    // Development environment
    'dev' => [
        'logLevel' => 'debug',
        'autoExport' => false,
        'backupSchedule' => 'manual',
    ],

    // Staging environment
    'staging' => [
        'logLevel' => 'info',
        'autoExport' => true,
        'backupSchedule' => 'weekly',
    ],

    // Production environment
    'production' => [
        'logLevel' => 'warning',
        'autoExport' => true,
        'backupEnabled' => true,
        'backupSchedule' => 'daily',
        // 'backupVolumeUid' => 'your-volume-uid-here', // Use asset volume in production
    ],
];
```

### Using Environment Variables

All settings support environment variables:

```php
use craft\helpers\App;

return [
    'translationCategory' => App::env('TRANSLATION_CATEGORY') ?: 'messages',
    'backupEnabled' => (bool)App::env('TRANSLATION_BACKUP_ENABLED') ?: true,
    'backupPath' => App::env('TRANSLATION_BACKUP_PATH') ?: '@storage/translation-manager/backups',
    'backupVolumeUid' => App::env('TRANSLATION_BACKUP_VOLUME_UID') ?: null,
    'logLevel' => App::env('TRANSLATION_LOG_LEVEL') ?: 'error',
];
```

**Important:**
- ✅ Use `App::env('VAR_NAME')` - Craft 5 recommended approach
- ❌ Don't use `getenv('VAR_NAME')` - Not thread-safe
- ✅ Always import: `use craft\helpers\App;`

### Setting Descriptions

#### General Settings

- **pluginName**: Display name for the plugin in Craft CP navigation
  - **Type:** `string`
  - **Default:** `'Translation Manager'`
- **logLevel**: What types of messages to log
  - **Type:** `string`
  - **Options:** `'debug'`, `'info'`, `'warning'`, `'error'`
  - **Default:** `'error'`
  - **Note:** Debug level requires Craft's `devMode` to be enabled

#### Translation Source Settings

- **enableSiteTranslations**: Enable/disable site translation capturing
  - **Type:** `bool`
  - **Default:** `true`
- **translationCategory**: The category used for the `|t()` filter in templates
  - **Type:** `string`
  - **Default:** `'messages'`
- **skipPatterns**: Array of text patterns to skip when capturing site translations
  - **Type:** `array`
  - **Default:** `[]`
  - **Example:** `['ID', 'Title', 'Status']`
- **enableFormieIntegration**: Enable/disable automatic Formie form field capturing
  - **Type:** `bool`
  - **Default:** `true`

#### File Generation Settings

- **autoExport**: Automatically generate translation files when translations are saved
  - **Type:** `bool`
  - **Default:** `true`
- **exportPath**: Path where translation files are generated (supports aliases)
  - **Type:** `string`
  - **Default:** `'@root/translations'`

#### Backup Settings

- **backupEnabled**: Enable/disable the backup system
  - **Type:** `bool`
  - **Default:** `true`
- **backupSchedule**: How often to create automatic backups
  - **Type:** `string`
  - **Options:** `'manual'`, `'daily'`, `'weekly'`, `'monthly'`
  - **Default:** `'manual'`
- **backupRetentionDays**: How many days to keep automatic backups (0 = keep forever, manual backups always kept)
  - **Type:** `int`
  - **Default:** `30`
- **backupOnImport**: Create backup before importing translations
  - **Type:** `bool`
  - **Default:** `true`
- **backupPath**: Where to store backup files when not using a volume (supports aliases)
  - **Type:** `string`
  - **Default:** `'@storage/translation-manager/backups'`
- **backupVolumeUid**: Asset volume UID for storing backups (optional, overrides backupPath)
  - **Type:** `string|null`
  - **Default:** `null`

**Note:** Backups are organized into subfolders:
- `/scheduled/` - Automated daily/weekly/monthly backups
- `/imports/` - Backups created before CSV imports
- `/maintenance/` - Backups created before cleanup or clear operations
- `/manual/` - User-initiated backups (never auto-deleted)
- `/other/` - Any other backups

Scheduled backups use Craft's queue system and automatically restart if the queue is cleared.

#### Interface Settings

- **itemsPerPage**: Number of translations shown per page
  - **Type:** `int`
  - **Range:** `10-500`
  - **Default:** `100`
- **showContext**: Show where translations are used in the interface
  - **Type:** `bool`
  - **Default:** `false`
- **enableSuggestions**: Show translation suggestions based on similar existing translations
  - **Type:** `bool`
  - **Default:** `false`
- **autoSaveEnabled**: Enable automatic saving when leaving a field (CP only)
  - **Type:** `bool`
  - **Default:** `false`
- **autoSaveDelay**: Delay in seconds before auto-saving
  - **Type:** `int`
  - **Range:** `1-10`
  - **Default:** `2`

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
