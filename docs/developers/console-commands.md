# Console Commands

Translation Manager provides console commands for automation and scripting.

## Command Help

Use the plugin help command when you need to discover available commands or confirm the correct command group.

```bash title="PHP"
php craft translation-manager/help
```

```bash title="DDEV"
ddev craft translation-manager/help
```

Clean by type help:

```bash title="PHP"
php craft translation-manager/help maintenance/clean-by-type
```

```bash title="DDEV"
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

Formie:

```bash title="PHP"
php craft translation-manager/translations/capture-provider formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/capture-provider formie
```

Freeform:

```bash title="PHP"
php craft translation-manager/translations/capture-provider freeform
```

```bash title="DDEV"
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

Use `--delay` when a deploy platform needs a short settling period after
migrations/project-config sync before generated translation files are written:

```bash title="PHP"
php craft translation-manager/translations/generate-all --delay=10
```

```bash title="DDEV"
ddev craft translation-manager/translations/generate-all --delay=10
```

Use `--verify` to check a sample of generated rows after writing. Verification
confirms the generated PHP files contain the expected values and that Craft can
resolve them through `Craft::t()` in the current runtime:

```bash title="PHP"
php craft translation-manager/translations/generate-all --delay=10 --verify=1
```

```bash title="DDEV"
ddev craft translation-manager/translations/generate-all --delay=10 --verify=1
```

Each `generate-all` run records its result in Translation Manager's generation
status table so deploy-hook, CLI, and Control Panel runs can be compared.

### `translation-manager/translations/generate-provider`

Generate PHP translation files for one form provider.

Formie:

```bash title="PHP"
php craft translation-manager/translations/generate-provider formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/generate-provider formie
```

Freeform:

```bash title="PHP"
php craft translation-manager/translations/generate-provider freeform
```

```bash title="DDEV"
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

### `translation-manager/translations/generate-category` @since(5.25.1)

Generate PHP translation files for one enabled site category.

```bash title="PHP"
php craft translation-manager/translations/generate-category messages
```

```bash title="DDEV"
ddev craft translation-manager/translations/generate-category messages
```

### `translation-manager/translations/import`

Import existing PHP translation files from disk into the database, preserving the translated values. Mirrors the Control Panel PHP import: it discovers every `{language}/{category}.php` file under the generation path and creates or updates rows for all languages.

Run with **no scope** to print a dry-run summary of what could be imported (per-file key counts and what would be skipped) — nothing is written. To actually import, pass `--all`, or narrow with `--language` and/or `--category`.

| Option | Description |
|--------|-------------|
| `--all` | Import every discovered file (required when no `--language`/`--category` is given) |
| `--language` | Only import files in this language directory (e.g. `ar`) |
| `--category` | Only import files for this category (e.g. `formie`) |

Dry-run summary:

```bash title="PHP"
php craft translation-manager/translations/import
```

```bash title="DDEV"
ddev craft translation-manager/translations/import
```

Import everything:

```bash title="PHP"
php craft translation-manager/translations/import --all
```

```bash title="DDEV"
ddev craft translation-manager/translations/import --all
```

Import one language:

```bash title="PHP"
php craft translation-manager/translations/import --language=ar
```

```bash title="DDEV"
ddev craft translation-manager/translations/import --language=ar
```

Import one category:

```bash title="PHP"
php craft translation-manager/translations/import --category=formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/import --category=formie
```

Import one language and category:

```bash title="PHP"
php craft translation-manager/translations/import --language=ar --category=formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/import --language=ar --category=formie
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

Clean all unused translations:

```bash title="PHP"
php craft translation-manager/maintenance/clean-by-type --type=all
```

```bash title="DDEV"
ddev craft translation-manager/maintenance/clean-by-type --type=all
```

Clean all unused form translations:

```bash title="PHP"
php craft translation-manager/maintenance/clean-by-type --type=forms
```

```bash title="DDEV"
ddev craft translation-manager/maintenance/clean-by-type --type=forms
```

Clean unused Formie translations:

```bash title="PHP"
php craft translation-manager/maintenance/clean-by-type --type=forms --provider=formie
```

```bash title="DDEV"
ddev craft translation-manager/maintenance/clean-by-type --type=forms --provider=formie
```

Clean unused Freeform translations:

```bash title="PHP"
php craft translation-manager/maintenance/clean-by-type --type=forms --provider=freeform
```

```bash title="DDEV"
ddev craft translation-manager/maintenance/clean-by-type --type=forms --provider=freeform
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

## Cron Examples

Daily backup at 3 AM:

```text
0 3 * * * cd /path/to/project && php craft translation-manager/backup/scheduled
```

Weekly template scan on Sunday at 2 AM:

```text
0 2 * * 0 cd /path/to/project && php craft translation-manager/maintenance/scan-templates
```
