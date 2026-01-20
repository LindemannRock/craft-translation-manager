# Console Commands

Translation Manager provides console commands for automation and scripting.

## Translation Commands

### Capture Formie

Capture all translations from existing Formie forms:

```bash
php craft translation-manager/translations/capture-formie
```

### Export Commands

```bash
# Export all translations to PHP files
php craft translation-manager/translations/export-all

# Export Formie translations only
php craft translation-manager/translations/export-formie

# Export site translations only
php craft translation-manager/translations/export-site
```

### Import Formie

Import existing Formie translation files to database:

```bash
php craft translation-manager/translations/import-formie
```

## Maintenance Commands

### Template Scanner

```bash
# Scan templates to identify unused translations
php craft translation-manager/maintenance/scan-templates

# Preview what would be marked unused (no changes)
php craft translation-manager/maintenance/preview-scan
```

### Clean Unused

```bash
# Clean all unused translations
php craft translation-manager/maintenance/clean-unused

# Clean by type
php craft translation-manager/maintenance/clean-by-type --type=all
php craft translation-manager/maintenance/clean-by-type --type=site
php craft translation-manager/maintenance/clean-by-type --type=formie
```

## Backup Commands

### Create Backup

```bash
# Create manual backup
php craft translation-manager/backup/create

# Create with custom reason
php craft translation-manager/backup/create --reason="Before major update"
```

### Scheduled Backup

Run scheduled backup (for cron jobs):

```bash
php craft translation-manager/backup/scheduled
```

### List Backups

```bash
php craft translation-manager/backup/list
```

### Clean Old Backups

Clean backups based on retention settings:

```bash
php craft translation-manager/backup/clean
```

## Debug Commands

### Search Debug

Test search functionality:

```bash
php craft translation-manager/debug/search "search term"
```

### Recent Translations

List recent translations:

```bash
php craft translation-manager/debug/recent
php craft translation-manager/debug/recent 50  # Limit to 50
```

## Cron Examples

### Daily Backup

```bash
# Run daily at 3 AM
0 3 * * * cd /path/to/project && php craft translation-manager/backup/scheduled
```

### Weekly Template Scan

```bash
# Run weekly on Sunday at 2 AM
0 2 * * 0 cd /path/to/project && php craft translation-manager/maintenance/scan-templates
```
