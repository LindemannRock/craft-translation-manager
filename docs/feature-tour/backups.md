# Backup System

The Translation Manager includes a comprehensive backup system that automatically protects your translations before any potentially destructive operations.

## Backup Types

### Manual Backups

- Created via the "Create Backup" button in the CP
- Never automatically deleted, regardless of retention settings
- Stored in `/manual/` subfolder
- Perfect for creating restore points before major changes

### Scheduled Backups

- Automatically created based on your schedule (daily, weekly, monthly)
- Uses Craft's queue system with automatic recovery
- Stored in `/scheduled/` subfolder
- Subject to retention policy

### Import Backups

- Automatically created before CSV imports (if enabled)
- Stored in `/imports/` subfolder
- Helps recover from bad imports

### Maintenance Backups

- Created before cleanup or clear operations
- Stored in `/maintenance/` subfolder
- Safety net for destructive operations

### Restore/Other Backups

- Created before restore operations
- Stored in `/other/` subfolder
- Safety backup when restoring previous backups

## Configuration

```php
// config/translation-manager.php
return [
    'backupEnabled' => true,
    'backupSchedule' => 'daily', // manual, daily, weekly
    'backupRetentionDays' => 30, // days (0 = keep forever)
    'backupOnImport' => true,
];
```

### Schedule Options

| Schedule | Frequency |
|----------|-----------|
| `manual` | No automatic backups (default) |
| `daily` | Every 24 hours |
| `weekly` | Every 7 days |

## Using the Control Panel

### Creating Backups

1. Navigate to **Translation Manager → Backups**
2. Click **Create Backup Now**
3. Backup is created with current timestamp

### Viewing Backups

The backup list shows:

- **Date**: When created
- **Type**: Folder organization
- **Reason**: Why it was created
- **Translations**: Number backed up
- **Size**: Total size

### Restoring Backups

1. Find the backup to restore
2. Click gear icon → **Restore**
3. Confirm (safety backup created first)
4. All translations replaced with backup version

### Downloading Backups

Click gear icon → **Download** to get a ZIP containing:

- Translation data (JSON)
- Generated PHP files
- Backup metadata

## Console Commands

```bash
# Create manual backup
php craft translation-manager/backup/create

# Create with custom reason
php craft translation-manager/backup/create --reason="Before update"

# Run scheduled backup (for cron)
php craft translation-manager/backup/scheduled

# List all backups
php craft translation-manager/backup/list
```

## Storage Structure

```
storage/translation-manager/backups/
├── scheduled/      # Automated backups
├── imports/        # Pre-import backups
├── maintenance/    # Pre-cleanup backups
├── manual/         # User-created backups
└── other/          # Miscellaneous backups
```

## Cloud Storage Support

Store backups in any Craft asset volume:

- Amazon S3
- Servd
- Wasabi
- Any cloud provider with Craft volume support

Configure in **Settings → Backup → Backup Storage Volume**.

## Retention Policy

- Automatic cleanup based on `backupRetention` setting
- **Manual backups are never automatically deleted**
- Set to `0` to keep all backups forever
