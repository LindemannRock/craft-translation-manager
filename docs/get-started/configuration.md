# Configuration

Configure Translation Manager by creating a config file in your `config/` directory.

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `pluginName` | `string` | `'Translation Manager'` | The display name for the plugin (shown in CP menu and breadcrumbs) |
| `translationCategory` | `string` | `'messages'` | The translation category to use for site translations (e.g. |t('messages')) |
| `sourceLanguage` | `string` | `'en'` | The source language of template strings (language your |t() strings are written in) |
| `enableFormieIntegration` | `bool` | `true` | Whether to enable Formie form translation integration |
| `enableSiteTranslations` | `bool` | `true` | Whether to enable site translation capture |
| `autoExport` | `bool` | `true` | Whether to automatically export translations when saved |
| `exportPath` | `string` | `'@root/translations'` | The path where translation files should be exported |
| `itemsPerPage` | `int` | `100` | Number of items to show per page in the translation manager |
| `autoSaveEnabled` | `bool` | `false` | Whether to enable auto-save after typing stops |
| `autoSaveDelay` | `int` | `2` | Auto-save delay in seconds (how long to wait after typing stops) |
| `showContext` | `bool` | `false` | Whether to show the translation context in the CP interface |
| `logLevel` | `string` | `'error'` | The logging level for the plugin |
| `skipPatterns` | `array` | `[]` | List of text patterns to skip when capturing translations |
| `localeMapping` | `array` | `[]` | Maps regional locale variants to base locales (see Locale Mapping section below) |
| `integrationSettings` | `array` | `[]` | Dynamic integration settings for discovered integrations |
| `enableSuggestions` | `bool` | `false` | Whether to enable automatic translation suggestions (future feature) |
| `backupEnabled` | `bool` | `true` | Whether to enable automatic backups |
| `backupRetentionDays` | `int` | `30` | Number of days to keep backups (0 = keep forever) |
| `backupOnImport` | `bool` | `true` | Whether to create a backup before importing |
| `backupSchedule` | `string` | `'manual'` | Backup schedule (manual, daily, weekly) |
| `backupPath` | `string` | `'@storage/translation-manager/backups'` | The path where backups should be stored |
| `backupVolumeUid` | `string` | `null` | Asset volume UID for backup storage (null = use backupPath) |

## Example Configuration

```php
<?php
// config/{plugin-handle}.php

return [
    '*' => [
        'pluginName' => 'Translation Manager',
        'translationCategory' => 'messages',
        'sourceLanguage' => 'en',
        'enableFormieIntegration' => true,
        'enableSiteTranslations' => true,
        'autoExport' => true,
        'exportPath' => '@root/translations',
        'itemsPerPage' => 100,
        'autoSaveEnabled' => false,
        'autoSaveDelay' => 2,
        'showContext' => false,
        'logLevel' => 'error',
        'skipPatterns' => [],
        'integrationSettings' => [],
        'enableSuggestions' => false,
        'backupEnabled' => true,
        'backupRetentionDays' => 30,
        'backupOnImport' => true,
        'backupSchedule' => 'manual',
        'backupPath' => '@storage/translation-manager/backups',
        'backupVolumeUid' => null,
    ],
];
```

## Locale Mapping

Locale mapping allows you to consolidate regional locale variants to base locales, reducing translation duplication. For example, if you have sites using `en-US`, `en-GB`, and `en-AU`, you can map all of them to `en` so they share the same translation files.

### How It Works

When locale mapping is configured:

1. **Loading translations**: When a page with locale `en-US` loads, the plugin will look for translations in the `en` folder instead of `en-US`.

2. **Capturing translations**: When missing translations are captured at runtime, they are saved under the mapped locale (`en`) instead of the original locale (`en-US`).

This reduces the need to maintain separate translation files for each regional variant.

### Configuration via Settings UI

Navigate to **Translation Manager → Settings → Translation Sources** and scroll to the "Locale Mapping" section. Add mappings using the editable table:

| Source Locale | Maps To | Enabled |
|---------------|---------|---------|
| en-US         | en      | ✓       |
| en-GB         | en      | ✓       |
| fr-CA         | fr      | ✓       |

### Configuration via Config File

You can also configure locale mapping in your `config/translation-manager.php` file:

```php
<?php

return [
    '*' => [
        'localeMapping' => [
            ['source' => 'en-US', 'destination' => 'en', 'enabled' => true],
            ['source' => 'en-GB', 'destination' => 'en', 'enabled' => true],
            ['source' => 'fr-CA', 'destination' => 'fr', 'enabled' => true],
        ],
    ],
];
```

### Important Notes

- **Existing translations**: Locale mapping only affects how translations are loaded and captured going forward. Existing translations under the original locale will need to be migrated manually.

- **Validation**: The source and destination must be valid locale codes (e.g., `en`, `en-US`, `fr-CA`). You cannot map a locale to itself.

- **No duplicate sources**: Each source locale can only be mapped once. If you have duplicate source entries, validation will fail.
