# Configuration

Configure Translation Manager by creating a config file at `config/translation-manager.php`.

## Configuration Options

### Translation Sources

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `pluginName` | `string` | `'Translation Manager'` | Display name for the plugin (shown in CP menu and breadcrumbs) |
| `translationCategory` | `string` | `'messages'` | **Deprecated** — use `translationCategories` instead. Single translation category for site translations |
| `translationCategories` | `array` | `[]` | Multiple translation categories. Format: `[['key' => 'messages', 'enabled' => true]]`. Falls back to `translationCategory` if empty |
| `sourceLanguage` | `string` | `'en'` | Source language of template strings — the language your `\|t()` keys are written in (see [Source Language](#source-language)) |
| `enableFormieIntegration` | `bool` | `true` | Enable Formie form translation integration |
| `enableFreeformIntegration` | `bool` | `true` | Enable Freeform form translation integration |
| `enableSiteTranslations` | `bool` | `true` | Enable site translation capture from `\|t()` calls |
| `captureMissingTranslations` | `bool` | `false` | Capture missing translations at runtime (auto-add when used) |
| `captureMissingOnlyDevMode` | `bool` | `true` | Only capture missing translations when devMode is enabled (recommended) |
| `excludeFormHandlePatterns` | `array` | `[]` | Form handle patterns to exclude from Formie capture (e.g., `['-ar', '_ar']`) @since(5.14.0) |
| `skipPatterns` | `array` | `[]` | Text patterns to skip when capturing site translations (see [Skip Patterns](#skip-patterns)) |
| `localeMapping` | `array` | `[]` | Maps regional locale variants to base locales (see [Locale Mapping](#locale-mapping)) @since(5.17.0) |

### Source Language

**Source Language** (set under **Settings → Translation Sources**) is the language your `|t()` keys are written in — the literal text inside `{{ 'Copyright'|t('messages') }}`. It defaults to `en` and should match your *keys*, not necessarily your primary site language.

Why it matters: Translation Manager treats the source language as **already translated**. A string in that language is stored with the key as its own value and a **Translated** status, so it never lands in your Pending queue — you translate *into* your other site languages, never into the source. At runtime, a request in the source language returns the original key text as-is.

The Control Panel offers your site languages; in `config/translation-manager.php` you can set any code matching `xx` or `xx-XX` (e.g. `en`, `en-US`). Region variants are matched by base language, so source `en` also covers `en-US`.

> **Set this once, at setup, to match your `|t()` keys.** Changing it later only re-classifies which language is the "original" — it does **not** migrate, rewrite, or delete existing translations, and it does not regenerate files on its own (only a [generation path](#generation) change does that). Strings you already captured in the *old* source language stay in the database — still flagged Translated with the key as their value — but are now treated as an ordinary target language, while the *new* source language becomes the original going forward. If you must change it after translations exist, review those rows and regenerate afterwards.

### Interface

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `itemsPerPage` | `int` | `100` | Items per page in the translation list (10–500) |
| `autoSaveEnabled` | `bool` | `false` | Enable auto-save after typing stops |
| `autoSaveDelay` | `int` | `2` | Auto-save delay in seconds (1–10) |
| `requireApproval` | `bool` | `false` | Require an approver before edited translations become *Translated*. When enabled, editors can change text but only users with the *Approve Translations* permission can mark a translation as *Translated* (see [Approval Workflow](#approval-workflow)) |

### Approval Workflow

By default any user who can edit translations can also publish them as *Translated*. Turning on **Require Approval Before Publish** (the `requireApproval` setting, under **Settings → General**) splits those two responsibilities:

- **Editors** (users with *Edit Translations*) can change translation text, but their saves land as **Draft** rather than going live.
- **Approvers** (users with the *Approve Translations* permission) save straight to **Translated**, which stamps their name and the time into the Reviewed By / Reviewed At columns.

Use this when translations are drafted by one team and signed off by another. Leave it `false` for a single-editor workflow where review is not needed. For the day-to-day Draft → Translated flow and the bulk **Mark Draft** / **Mark Translated** actions, see [Managing translations → Approval workflow](../template-guides/managing-translations.md#approval-workflow).

### Skip Patterns

`skipPatterns` keeps noise out of your translation list by excluding matching strings from [auto-capture](../template-guides/basic-usage.md#auto-capture-missing-strings). Enter one pattern per line in **Settings → Translation Sources → Skip Patterns** (or as an array in config). Matching is a **case-insensitive substring** test — a string is skipped if any pattern appears anywhere within it (not a glob or regex). Common entries are field-name fragments you never want as copy, such as `ID`, `Title`, or `Status`.

```php
'skipPatterns' => ['ID', 'Title', 'Status'],
```

Skip patterns only affect **site** translation capture — they don't touch form-provider fields.

> **Removing strings you've already captured.** Skip patterns only stop *new* captures. To purge existing rows that match, the Skip Patterns settings panel shows an **Apply Skip Patterns to Existing Translations** button once at least one pattern is set. This permanently deletes every matching site translation across all sites and **cannot be undone** — and unlike the [Maintenance](../feature-tour/maintenance.md) clears, it does not take a backup first. Export first if you're unsure.

### Generation

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `autoGenerate` | `bool` | `true` | Automatically generate PHP translation files when translations are saved |
| `runtimeTranslationSource` | `string` | `'php-files'` | Runtime source for managed Craft translation categories. Options: `php-files`, `database`, `hybrid` @since(5.29.0) |
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

### Runtime Translation Source

This setting controls what Craft uses when a managed category is requested with
`Craft::t()` or Twig's `|t` filter.

Start with this rule of thumb:

- Use `php-files` for standard hosting where the same filesystem is used
  by deploy commands and frontend requests.
- Use `hybrid` for edge, split-runtime, containerized, or
  serverless-style hosting where a post-deploy command can generate and verify
  PHP files, but live frontend requests may still not read those files
  reliably.
- Use `database` only when you intentionally want Translation Manager's database
  rows to be the only runtime source, usually for diagnostics or a fully
  database-owned category.

| Mode | What it reads | Use when | Limitations |
|------|---------------|----------|-------------|
| `php-files` | Generated PHP files in `translations/{language}/{category}.php` | Default mode for traditional hosting where web and CLI runtimes share the same translation files | Frontend output depends on the live web runtime seeing the generated files |
| `database` | Translated rows stored in Translation Manager's database tables | Diagnostics, testing, or installs where Translation Manager should fully own the runtime category | Does not fall back to committed PHP files or native provider files for missing keys |
| `hybrid` | PHP files first, then Translation Manager database rows override matching keys | Split-runtime, edge, or containerized hosting where deploy hooks can write and verify PHP files but frontend requests may not reliably consume them | The category still must be enabled in Translation Manager, and PHP fallback files must match the category name |

`hybrid` does not replace generation. Keep your post-deploy
`generate-all --delay=10 --verify=1` step so PHP files stay current for
standard Craft loading, exports, native plugin fallbacks, and any keys that do
not have translated database rows yet. The difference is runtime priority:
translated database rows win, and PHP files fill gaps.

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
        'runtimeTranslationSource' => 'php-files',
        // Must resolve to Craft's @translations root exactly.
        'generationPath' => '@translations',
        'itemsPerPage' => 100,
        'autoSaveEnabled' => false,
        'autoSaveDelay' => 2,
        'requireApproval' => false,
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
