# Console Commands

Translation Manager provides console commands for automation and scripting.

## Translation Commands

### `translation-manager/translations/capture-formie`

Capture all translations from existing Formie forms and store them in the database.

```bash title="PHP"
php craft translation-manager/translations/capture-formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/capture-formie
```

### `translation-manager/translations/export-all`

Export all translations (Formie + site) to PHP translation files.

```bash title="PHP"
php craft translation-manager/translations/export-all
```

```bash title="DDEV"
ddev craft translation-manager/translations/export-all
```

### `translation-manager/translations/export-formie`

Export Formie translations only to PHP translation files.

```bash title="PHP"
php craft translation-manager/translations/export-formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/export-formie
```

### `translation-manager/translations/export-site`

Export site translations only to PHP translation files.

```bash title="PHP"
php craft translation-manager/translations/export-site
```

```bash title="DDEV"
ddev craft translation-manager/translations/export-site
```

### `translation-manager/translations/import-formie`

Import existing Formie translation files from disk into the database.

```bash title="PHP"
php craft translation-manager/translations/import-formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/import-formie
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

Clean unused translations by type. The `--type` option is required.

| Option | Values | Description |
|--------|--------|-------------|
| `--type` | `all`, `site`, `formie` | Type of translations to clean |

```bash title="PHP"
php craft translation-manager/maintenance/clean-by-type --type=all
```

```bash title="DDEV"
ddev craft translation-manager/maintenance/clean-by-type --type=all
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

Run a scheduled backup. Use this for cron jobs — it respects the backup schedule settings.

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

### `translation-manager/debug/search`

Test search functionality against the translation database.

```bash title="PHP"
php craft translation-manager/debug/search "search term"
```

```bash title="DDEV"
ddev craft translation-manager/debug/search "search term"
```

### `translation-manager/debug/recent`

List recent translations. Defaults to the last 10 entries.

| Argument | Type | Default | Description |
|----------|------|---------|-------------|
| `limit` | `int` | `10` | Number of recent translations to show |

```bash title="PHP"
php craft translation-manager/debug/recent
```

```bash title="DDEV"
ddev craft translation-manager/debug/recent
```

With a custom limit:

```bash title="PHP"
php craft translation-manager/debug/recent 50
```

```bash title="DDEV"
ddev craft translation-manager/debug/recent 50
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
