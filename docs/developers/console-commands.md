# Console Commands

Translation Manager provides console commands for automation and scripting.

## Command Help

Use the plugin help command when you need to discover available commands or confirm the correct command group.

```bash title="PHP"
php craft translation-manager/help
php craft translation-manager/help maintenance/clean-by-type
```

```bash title="DDEV"
ddev craft translation-manager/help
ddev craft translation-manager/help maintenance/clean-by-type
```

Craft's native help also works when you already know the exact command:

```bash title="PHP"
php craft help translation-manager/maintenance/clean-by-type
```

```bash title="DDEV"
ddev craft help translation-manager/maintenance/clean-by-type
```

## Translation Commands

### `translation-manager/translations/capture-provider`

Capture all translations from an existing form provider and store them in the database.

```bash title="PHP"
php craft translation-manager/translations/capture-provider formie
php craft translation-manager/translations/capture-provider freeform
```

```bash title="DDEV"
ddev craft translation-manager/translations/capture-provider formie
ddev craft translation-manager/translations/capture-provider freeform
```

### `translation-manager/translations/generate-all`

Generate all PHP translation files (enabled form providers + site) from the database.

```bash title="PHP"
php craft translation-manager/translations/generate-all
```

```bash title="DDEV"
ddev craft translation-manager/translations/generate-all
```

### `translation-manager/translations/generate-provider`

Generate PHP translation files for one form provider.

```bash title="PHP"
php craft translation-manager/translations/generate-provider formie
php craft translation-manager/translations/generate-provider freeform
```

```bash title="DDEV"
ddev craft translation-manager/translations/generate-provider formie
ddev craft translation-manager/translations/generate-provider freeform
```

### `translation-manager/translations/generate-site`

Generate site PHP translation files only.

```bash title="PHP"
php craft translation-manager/translations/generate-site
```

```bash title="DDEV"
ddev craft translation-manager/translations/generate-site
```

### `translation-manager/translations/import`

Import existing PHP translation files from disk into the database, preserving the translated values. Mirrors the Control Panel PHP import: it discovers every `{language}/{category}.php` file under the generation path and creates or updates rows for all languages.

Run with **no scope** to print a dry-run summary of what could be imported (per-file key counts and what would be skipped) — nothing is written. To actually import, pass `--all`, or narrow with `--language` and/or `--category`.

| Option | Description |
|--------|-------------|
| `--all` | Import every discovered file (required when no `--language`/`--category` is given) |
| `--language` | Only import files in this language directory (e.g. `ar`) |
| `--category` | Only import files for this category (e.g. `formie`) |

```bash title="PHP"
php craft translation-manager/translations/import                  # dry-run summary, imports nothing
php craft translation-manager/translations/import --all            # import everything
php craft translation-manager/translations/import --language=ar
php craft translation-manager/translations/import --category=formie
php craft translation-manager/translations/import --language=ar --category=formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/import
ddev craft translation-manager/translations/import --all
ddev craft translation-manager/translations/import --language=ar --category=formie
```

### `translation-manager/translations/ai-draft`

Translate pending rows into AI drafts for a required target language. The command uses the AI provider from settings unless `--provider` is supplied.

| Argument | Description |
|----------|-------------|
| `language` | Required target language, such as `ar` or `de` |

| Option | Values | Default | Description |
|--------|--------|---------|-------------|
| `--limit` | integer | `50` | Maximum number of pending rows to process |
| `--type` | `all`, `forms`, `site` | `all` | Translation type filter |
| `--provider` | provider handle | settings default | AI provider to use for this run |

```bash title="PHP"
php craft translation-manager/translations/ai-draft ar
php craft translation-manager/translations/ai-draft de --limit=100 --type=site --provider=mock
```

```bash title="DDEV"
ddev craft translation-manager/translations/ai-draft ar
ddev craft translation-manager/translations/ai-draft de --limit=100 --type=site --provider=mock
```

## Maintenance Commands

### `translation-manager/maintenance/scan-templates`

Scan templates to identify unused translations.

```bash title="PHP"
php craft translation-manager/maintenance/scan-templates
```

```bash title="DDEV"
ddev craft translation-manager/maintenance/scan-templates
```

### `translation-manager/maintenance/preview-scan`

Preview what would be marked unused without making any changes.

```bash title="PHP"
php craft translation-manager/maintenance/preview-scan
```

```bash title="DDEV"
ddev craft translation-manager/maintenance/preview-scan
```

### `translation-manager/maintenance/clean-unused`

Clean all unused translations.

```bash title="PHP"
php craft translation-manager/maintenance/clean-unused
```

```bash title="DDEV"
ddev craft translation-manager/maintenance/clean-unused
```

### `translation-manager/maintenance/clean-by-type`

Clean unused translations by type. The `--type` option is required. Use `--provider` with `--type=forms` to narrow cleanup to one forms provider.

| Option | Values | Description |
|--------|--------|-------------|
| `--type` | `all`, `site`, `forms` | Type of translations to clean |
| `--provider` | `formie`, `freeform` | Optional forms provider filter; only valid with `--type=forms` |

```bash title="PHP"
php craft translation-manager/maintenance/clean-by-type --type=all
php craft translation-manager/maintenance/clean-by-type --type=forms
php craft translation-manager/maintenance/clean-by-type --type=forms --provider=formie
```

```bash title="DDEV"
ddev craft translation-manager/maintenance/clean-by-type --type=all
ddev craft translation-manager/maintenance/clean-by-type --type=forms
ddev craft translation-manager/maintenance/clean-by-type --type=forms --provider=formie
```

## Backup Commands

### `translation-manager/backup/create`

Create a manual backup of current translations.

| Option | Type | Description |
|--------|------|-------------|
| `--reason` | `string` | Optional reason for the backup |

```bash title="PHP"
php craft translation-manager/backup/create
```

```bash title="DDEV"
ddev craft translation-manager/backup/create
```

With a reason:

```bash title="PHP"
php craft translation-manager/backup/create --reason="Before major update"
```

```bash title="DDEV"
ddev craft translation-manager/backup/create --reason="Before major update"
```

### `translation-manager/backup/scheduled`

Run a scheduled backup. Translation Manager normally schedules backups through Craft's queue; this command is useful for manual checks or direct cron setups and respects the backup schedule settings.

```bash title="PHP"
php craft translation-manager/backup/scheduled
```

```bash title="DDEV"
ddev craft translation-manager/backup/scheduled
```

### `translation-manager/backup/list`

List all existing backups.

```bash title="PHP"
php craft translation-manager/backup/list
```

```bash title="DDEV"
ddev craft translation-manager/backup/list
```

### `translation-manager/backup/clean`

Clean old backups based on retention settings.

```bash title="PHP"
php craft translation-manager/backup/clean
```

```bash title="DDEV"
ddev craft translation-manager/backup/clean
```

## Debug Commands

### `translation-manager/debug/test-ai`

Test the configured AI provider, or an explicit provider, with a live sample translation.

| Option | Default | Description |
|--------|---------|-------------|
| `--provider` | settings default | AI provider handle to test |
| `--target-language` | `de` | Target language for the sample translation. `--targetLanguage` is also accepted for backward compatibility |
| `--text` | `Welcome to our agency website.` | Sample text to translate |

```bash title="PHP"
php craft translation-manager/debug/test-ai
php craft translation-manager/debug/test-ai --provider=openai
php craft translation-manager/debug/test-ai --provider=gemini --target-language=ar --text="Welcome to our agency"
```

```bash title="DDEV"
ddev craft translation-manager/debug/test-ai
ddev craft translation-manager/debug/test-ai --provider=openai
ddev craft translation-manager/debug/test-ai --provider=gemini --target-language=ar --text="Welcome to our agency"
```

## Cron Examples

Daily backup at 3 AM:

```bash
0 3 * * * cd /path/to/project && php craft translation-manager/backup/scheduled
```

Weekly template scan on Sunday at 2 AM:

```bash
0 2 * * 0 cd /path/to/project && php craft translation-manager/maintenance/scan-templates
```
