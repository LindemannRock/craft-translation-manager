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
| `enableFreeformIntegration` | `bool` | `true` | Enable Freeform form translation integration |
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
| `enableSuggestions` | `bool` | `false` | Enable automatic translation suggestions (future feature) |

### Generation

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `autoGenerate` | `bool` | `true` | Automatically generate PHP translation files when translations are saved |
| `runtimeTranslationSource` | `string` | `'generated-files'` | Runtime source for managed Craft translation categories. Options: `generated-files`, `database`, `database-with-php-fallback` |
| `generationPath` | `string` | `'@translations'` | Generation directory. Supports `@translations` or `$VARIABLE` env vars that resolve exactly to `@translations` |

Generated PHP files must live at Craft's translations root. Translation Manager
validates `generationPath` against `@translations` exactly because Craft loads
runtime translation files from that root. Values such as
`@root/translations/test`, `@translations/test`, or any other subfolder are
rejected and generation falls back to `@translations`.

Use this:

```php
'generationPath' => '@translations',
```

For traditional single-runtime hosting, keep `runtimeTranslationSource` at the
default `generated-files` mode. On split-runtime or edge-hosted deployments
where deploy hooks write translation files in a different runtime than frontend
requests read from, use `database-with-php-fallback`. That mode reads managed
translations from Translation Manager's database rows while preserving native
plugin PHP translations as a fallback.

### Runtime Translation Source

This setting controls what Craft uses when a managed category is requested with
`Craft::t()` or Twig's `|t` filter.

| Mode | What it reads | Use when | Limitations |
|------|---------------|----------|-------------|
| `generated-files` | Generated PHP files in `translations/{language}/{category}.php` | Default mode for traditional hosting where web and CLI runtimes share the same translation files | Frontend output depends on the live web runtime seeing the generated files |
| `database` | Translated rows stored in Translation Manager's database tables | Diagnostics, testing, or installs where Translation Manager should fully own the runtime category | Does not fall back to committed PHP files or native provider files for missing keys |
| `database-with-php-fallback` | PHP files first, then Translation Manager database rows override matching keys | Split-runtime, edge, or containerized hosting where deploy hooks can write and verify PHP files but frontend requests may not reliably consume them | The category still must be enabled in Translation Manager, and PHP fallback files must match the category name |

Hybrid fallback follows Craft's normal category/file naming:

```twig
{{ 'Welcome'|t('valid') }}
```

looks for:

```text
translations/{language}/valid.php
```

Then Translation Manager overlays translated database rows for category
`valid`. If no translated database row exists for `Welcome`, the PHP file value
is used. If a translated database row exists, the database value wins.

If your project needs the physical translations directory somewhere else,
change Craft's `@translations` alias for the project instead of pointing
Translation Manager at a subfolder.

### Backup

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `backupEnabled` | `bool` | `true` | Enable automatic backups |
| `backupRetentionDays` | `int` | `30` | Days to keep backups (0 = keep forever, max 365) |
| `backupOnImport` | `bool` | `true` | Create a backup before importing |
| `backupSchedule` | `string` | `'disabled'` | Backup schedule: `disabled`, `daily`, `weekly`, `monthly` |
| `backupPath` | `string` | `'@storage/translation-manager/backups'` | Backup directory. Supports `$VARIABLE` env vars. Must be under `@root` or `@storage` |
| `backupVolumeUid` | `string\|null` | `null` | Asset volume UID for backup storage (overrides `backupPath`). Local volumes inside `@webroot` are rejected; remote volume access must be restricted in the storage provider. |

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
        'enableFreeformIntegration' => true,
        'enableSiteTranslations' => true,
        'captureMissingTranslations' => false,
        'captureMissingOnlyDevMode' => true,
        'autoGenerate' => true,
        'runtimeTranslationSource' => 'generated-files',
        // Must resolve to Craft's @translations root exactly.
        'generationPath' => '@translations',
        'itemsPerPage' => 100,
        'autoSaveEnabled' => false,
        'autoSaveDelay' => 2,
        'logLevel' => 'error',
        'skipPatterns' => [],
        'backupEnabled' => true,
        'backupRetentionDays' => 30,
        'backupOnImport' => true,
        'backupSchedule' => 'disabled',
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

## Translations

Translation Manager includes translations for 12 languages. See [Translations](../resources/translations.md) for the full list and override instructions.
