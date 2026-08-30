# Configuration

Most Translation Manager settings live in **Translation Manager → Settings** and are stored in the `translationmanager_settings` database table. Use `config/translation-manager.php` when a value must be consistent across environments or managed in deployment configuration.

A config-file value overrides the stored value and makes the matching Control Panel field read-only. The config file is multi-environment aware, using the same `'*'`, `dev`, `staging`, and `production` groups as Craft's other config files.

## Copy the config file

For advanced configuration, copy the packaged template into your project:

```bash
cp vendor/lindemannrock/craft-translation-manager/src/config.php config/translation-manager.php
```

You can also create the file yourself using the [complete example](#complete-example) at the end of this page.

## General

These values appear under **Settings → General**.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `pluginName` | `string` | `'Translation Manager'` | Display name shown in the Control Panel navigation and breadcrumbs |
| `requireApproval` | `bool` | `false` | Save non-approver edits as *Draft* until an approver publishes them |
| `logLevel` | `string` | `'error'` | Logging threshold: `error`, `warning`, `info`, or `debug`; `debug` falls back to `info` outside devMode |

### Approval workflow

Enable **Require Approval Before Publish** when translators and reviewers have separate responsibilities. Editors without the matching approve permission save changes as **Draft**; an all-source or source-specific approver can publish them as **Translated**.

For the daily Draft → Translated flow and bulk **Mark Draft** / **Mark Translated** actions, see [Managing translations](../template-guides/managing-translations.md#approval-workflow).

## Translation Sources

These values appear under **Settings → Translation Sources**. They decide which site categories Translation Manager owns and which language is treated as the original.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableSiteTranslations` | `bool` | `true` | Enable managed site/template translation categories |
| `translationCategories` | `array` | `[]` | Category rows in the form `[['key' => 'messages', 'enabled' => true]]`; when empty, the deprecated `translationCategory` fallback is used |
| `translationCategory` | `string` | `'messages'` | Deprecated single-category fallback; use `translationCategories` for new configuration |
| `sourceLanguage` | `string` | `'en'` | Language used by the literal text in your `|t()` keys |
| `skipPatterns` | `array` | `[]` | Case-insensitive substrings excluded from new site-string capture |

### Source language

Set **Source Language** to the language your `|t()` keys are written in—the literal text inside `{{ 'Copyright'|t('messages') }}`. It should match your keys, not necessarily your primary site's language.

Translation Manager treats the source language as already translated. A source-language row uses the key as its value and starts as **Translated**, while your other languages become the translation targets. The Control Panel offers languages used by your sites; config accepts locale codes such as `en`, `en-US`, or `ar`.

> [!WARNING]
> Set the source language before capture. Changing it later does not migrate, rewrite, or delete existing translations, and it does not regenerate files by itself. Review rows from the old source language and regenerate after making a deliberate change.

### Skip patterns

`skipPatterns` keeps known noise out of [auto-capture](../template-guides/basic-usage.md#auto-capture-missing-strings). Each value is a case-insensitive substring, not a glob or regular expression:

```php
'skipPatterns' => ['ID', 'Title', 'Status'],
```

Skip patterns affect site categories only; they do not exclude Formie or Freeform fields.

> [!CAUTION]
> Skip patterns only prevent new capture. **Apply Skip Patterns to Existing Translations** permanently deletes every matching site row and does not create a safety backup. Export first if you may need those rows again.

## Locale Mapping @since(5.17.0)

**Settings → Locale Mapping** consolidates regional variants onto a base locale. For example, mapping `en-US` and `en-GB` to `en` lets both variants share the `en` rows and generated files.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `localeMapping` | `array` | `[]` | Rows containing `source`, `destination`, and `enabled` values |

```php
'localeMapping' => [
    ['source' => 'en-US', 'destination' => 'en', 'enabled' => true],
    ['source' => 'en-GB', 'destination' => 'en', 'enabled' => true],
    ['source' => 'fr-CA', 'destination' => 'fr', 'enabled' => true],
],
```

Mapping affects future loading and capture. Existing source-locale rows are not moved automatically; use **Maintenance → Cleanup → Migrate & Delete** when consolidating an existing locale. A source can appear only once, and it cannot map to itself.

## Auto-Capture

These values appear under **Settings → Auto-Capture**.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `captureMissingTranslations` | `bool` | `false` | Add missing strings from enabled categories to the database when they are requested |
| `captureMissingOnlyDevMode` | `bool` | `true` | Restrict runtime capture to requests where Craft's devMode is enabled |

Keep **Only in devMode** enabled for the usual workflow: capture in development or staging, translate and generate, then deploy the finished files.

## File Generation

These values appear under **Settings → File Generation**.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `autoGenerate` | `bool` | `true` | Regenerate managed PHP translation files after supported translation changes |
| `runtimeTranslationSource` | `string` | `'php-files'` | Runtime mode: `php-files`, `database`, or `hybrid` @since(5.29.0) |
| `generationPath` | `string` | `'@translations'` | Generation root; it must resolve exactly to Craft's `@translations` alias |

Values such as `@root/translations/test` and `@translations/test` are rejected because Craft loads runtime translation files from the translations root. To move the physical directory, change the project's `@translations` alias instead.

### Runtime translation source

| Mode | What it reads | Best fit | Limitation |
|------|---------------|----------|------------|
| `php-files` | Generated PHP files under `translations/{language}/{category}.php` | Standard hosting where deploy and web runtimes share the filesystem | Frontend requests must see the generated files |
| `database` | Translated Translation Manager database rows only | Diagnostics or intentionally database-owned categories | Missing rows do not fall back to PHP files |
| `hybrid` | PHP files as fallback, with translated database rows overriding matching keys | Edge, containerized, serverless, or split-runtime hosting | Categories must remain enabled and fallback filenames must match them |

Hybrid mode does not replace generation. Keep a post-deploy `generate-all --delay=10 --verify=1` step so fallback files, exports, and provider-native paths stay current.

## Interface

These values belong to **Settings → Interface** unless noted otherwise.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `itemsPerPage` | `int` | `100` | Rows per page in the translation list; accepted range 10–500 |
| `autoSaveEnabled` | `bool` | `false` | Save a changed translation when its field loses focus |

> [!NOTE]
> `autoSaveDelay` was removed in Translation Manager 5.35.0 because auto-save has always run when a changed field loses focus and never used the configured value. Upgrades remove the stored column, and legacy config files that still contain the key are safely ignored. Rolling the migration back recreates an integer column with a default of `2`; it cannot recover previously stored non-default values.

### Base display and export overrides

The Interface page also exposes base-owned display and export controls. Leave them unset to inherit from `config/lindemannrock-base.php`; set them here only when Translation Manager needs a plugin-specific override.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `timeFormat` | `string\|null` | `null` | `'12'` or `'24'` time display override |
| `monthFormat` | `string\|null` | `null` | `'numeric'`, `'short'`, or `'long'` month display override |
| `dateOrder` | `string\|null` | `null` | `'dmy'`, `'mdy'`, or `'ymd'` date order override |
| `dateSeparator` | `string\|null` | `null` | `'/'`, `'-'`, or `'.'` date separator override |
| `showSeconds` | `bool\|null` | `null` | Whether displayed timestamps include seconds |
| `exports` | `array\|null` | `null` | Public export-format hash with `csv`, `json`, and `excel` booleans |

The public config key is `exports`; internal settings fields such as `exportsCsv` are not config-file keys.

## Backup

These values appear under **Settings → Backup**.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `backupEnabled` | `bool` | `true` | Enable manual, scheduled, and supported safety-backup behavior |
| `backupOnImport` | `bool` | `true` | Select backup creation by default for imports |
| `backupSchedule` | `string` | `'disabled'` | `disabled`, `daily`, `weekly`, or `monthly` |
| `backupRetentionDays` | `int` | `30` | Days to retain automatic backups; `0` keeps them forever, maximum 365 |
| `backupVolumeUid` | `string\|null` | `null` | Authoritative Craft asset volume UID; overrides `backupPath` |
| `backupPath` | `string` | `'@storage/translation-manager/backups'` | Custom local path used only when no volume is selected; must stay under `@root` or `@storage` and outside webroot |

When backups are enabled, operations that promise a safety backup must finish it before destructive work. The guarded family includes restore, maintenance cleanup, provider deletion, site-translation deletion, category deletion, **Delete All**, and imports whose backup option is enabled. Deleting selected unused rows from the Translations screen does not promise or create a backup.

A configured volume is authoritative across creation, listing, size, download, restore, deletion, and retention, including its configured subpath. If that volume is missing or unavailable, operations fail closed instead of falling back to local storage, and the unchanged UID recovers when the same volume becomes available again.

Craft Cloud's application filesystem is ephemeral. Use a volume backed by Craft Cloud's **Cloud** filesystem type for persistent backups; custom paths and local-filesystem volumes are not durable there. See Craft's [local filesystem guidance](https://craftcms.com/docs/cloud/assets.html#local).

For the complete storage, integrity, and retention lifecycle, see [Backup system](../feature-tour/backups.md).

## Integrations

These values appear under **Settings → Integrations** when the corresponding provider is installed.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableFormieIntegration` | `bool` | `true` | Enable the built-in Formie provider |
| `enableFreeformIntegration` | `bool` | `true` | Enable the built-in Freeform provider |
| `excludeFormHandlePatterns` | `array` | `[]` | Case-insensitive substrings matched against form handles or titles across enabled form providers @since(5.14.0) |

For example, exclude language-specific duplicate forms with:

```php
'excludeFormHandlePatterns' => ['(Ar)', 'booking-ar', 'PricesAr'],
```

See [Integrations](../integrations/overview.md) for provider capture, generation, runtime behavior, and source-specific permissions.

## Complete example

This example includes every public scalar and nested config surface. Remove values you want to manage in the Control Panel, and keep only intentional overrides.

```php
<?php
// config/translation-manager.php

return [
    '*' => [
        'pluginName' => 'Translation Manager',
        'logLevel' => 'error',
        'requireApproval' => false,

        'enableSiteTranslations' => true,
        'translationCategories' => [
            ['key' => 'messages', 'enabled' => true],
        ],
        // Deprecated fallback used only when translationCategories is empty.
        'translationCategory' => 'messages',
        'sourceLanguage' => 'en',
        'skipPatterns' => [],
        'localeMapping' => [],

        'captureMissingTranslations' => false,
        'captureMissingOnlyDevMode' => true,

        'autoGenerate' => true,
        'runtimeTranslationSource' => 'php-files',
        'generationPath' => '@translations',

        'itemsPerPage' => 100,
        'autoSaveEnabled' => false,

        'backupEnabled' => true,
        'backupOnImport' => true,
        'backupSchedule' => 'disabled',
        'backupRetentionDays' => 30,
        'backupVolumeUid' => null,
        'backupPath' => '@storage/translation-manager/backups',

        'enableFormieIntegration' => true,
        'enableFreeformIntegration' => true,
        'excludeFormHandlePatterns' => [],

        // Optional base-setting overrides for Translation Manager only.
        // 'timeFormat' => '24',
        // 'monthFormat' => 'short',
        // 'dateOrder' => 'dmy',
        // 'dateSeparator' => '/',
        // 'showSeconds' => false,
        // 'exports' => [
        //     'csv' => true,
        //     'json' => false,
        //     'excel' => true,
        // ],
    ],

    'dev' => [
        'logLevel' => 'debug',
        'autoGenerate' => false,
        'captureMissingTranslations' => true,
    ],

    'staging' => [
        'logLevel' => 'info',
        'backupSchedule' => 'weekly',
    ],

    'production' => [
        'logLevel' => 'warning',
        'autoGenerate' => true,
        'backupEnabled' => true,
        'backupSchedule' => 'daily',
        // 'backupVolumeUid' => 'your-volume-uid-here',
    ],
];
```

## Translations

Translation Manager includes Control Panel translations for 12 languages. See [Translations](../resources/translations.md) for the complete list and override instructions.
