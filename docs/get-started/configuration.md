# Configuration

Configure Translation Manager by creating a config file at `config/translation-manager.php`.

## Configuration Options

### Translation Sources

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `pluginName` | `string` | `'Translation Manager'` | Display name for the plugin (shown in CP menu and breadcrumbs) |
| `translationCategory` | `string` | `'messages'` | **Deprecated** — use `translationCategories` instead. Single translation category for site translations |
| `translationCategories` | `array` | `[]` | Multiple translation categories. Format: `[['key' => 'messages', 'enabled' => true]]`. Falls back to `translationCategory` if empty |
| `sourceLanguage` | `string` | `'en'` | Source language of template strings (language your `\|t()` strings are written in) |
| `enableFormieIntegration` | `bool` | `true` | Enable Formie form translation integration |
| `enableSiteTranslations` | `bool` | `true` | Enable site translation capture from `\|t()` calls |
| `captureMissingTranslations` | `bool` | `false` | Capture missing translations at runtime (auto-add when used) |
| `captureMissingOnlyDevMode` | `bool` | `true` | Only capture missing translations when devMode is enabled (recommended) |
| `excludeFormHandlePatterns` | `array` | `[]` | Form handle patterns to exclude from Formie capture (e.g., `['-ar', '_ar']`) @since(5.14.0) |
| `skipPatterns` | `array` | `[]` | Text patterns to skip when capturing translations |
| `localeMapping` | `array` | `[]` | Maps regional locale variants to base locales (see [Locale Mapping](#locale-mapping)) @since(5.17.0) |

### Interface

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `itemsPerPage` | `int` | `100` | Items per page in the translation list (10–500) |
| `autoSaveEnabled` | `bool` | `false` | Enable auto-save after typing stops |
| `autoSaveDelay` | `int` | `2` | Auto-save delay in seconds (1–10) |
| `showContext` | `bool` | `false` | Show translation context column in the CP |
| `enableSuggestions` | `bool` | `false` | Enable automatic translation suggestions (future feature) |

### Export

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `autoExport` | `bool` | `true` | Automatically export PHP translation files when translations are saved |
| `exportPath` | `string` | `'@root/translations'` | Export directory. Supports `$VARIABLE` env vars. Must be under `@root`, `@storage`, or `@translations` |

### Backup

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `backupEnabled` | `bool` | `true` | Enable automatic backups |
| `backupRetentionDays` | `int` | `30` | Days to keep backups (0 = keep forever, max 365) |
| `backupOnImport` | `bool` | `true` | Create a backup before importing |
| `backupSchedule` | `string` | `'manual'` | Backup schedule: `manual`, `daily`, `weekly` |
| `backupPath` | `string` | `'@storage/translation-manager/backups'` | Backup directory. Supports `$VARIABLE` env vars. Must be under `@root` or `@storage` |
| `backupVolumeUid` | `string\|null` | `null` | Asset volume UID for cloud backup storage (overrides `backupPath`) |

### Logging

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `logLevel` | `string` | `'error'` | Log level: `error`, `warning`, `info`, `debug`. Debug requires devMode — auto-corrected to `info` otherwise |
| `integrationSettings` | `array` | `[]` | Dynamic integration settings (managed by the plugin, not typically set in config) |

## Example Configuration

```php
<?php
// config/translation-manager.php

return [
    '*' => [
        'pluginName' => 'Translation Manager',
        'translationCategories' => [
            ['key' => 'messages', 'enabled' => true],
        ],
        'sourceLanguage' => 'en',
        'enableFormieIntegration' => true,
        'enableSiteTranslations' => true,
        'captureMissingTranslations' => false,
        'captureMissingOnlyDevMode' => true,
        'autoExport' => true,
        'exportPath' => '@root/translations',
        'itemsPerPage' => 100,
        'autoSaveEnabled' => false,
        'autoSaveDelay' => 2,
        'showContext' => false,
        'logLevel' => 'error',
        'skipPatterns' => [],
        'backupEnabled' => true,
        'backupRetentionDays' => 30,
        'backupOnImport' => true,
        'backupSchedule' => 'manual',
        'backupPath' => '@storage/translation-manager/backups',
        'backupVolumeUid' => null,
    ],
];
```

## Locale Mapping @since(5.17.0)

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
