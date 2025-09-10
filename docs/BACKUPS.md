# Translation Manager Backup System

## Overview

The Translation Manager includes a comprehensive backup system that automatically protects your translations before any potentially destructive operations.

## Backup Types

### 1. Manual Backups
- Created via the "Create Backup" button in the CP
- Never automatically deleted, regardless of retention settings
- Stored in `/manual/` subfolder
- Perfect for creating restore points before major changes

### 2. Scheduled Backups
- Automatically created based on your schedule (daily, weekly, monthly)
- Uses Craft's queue system with automatic recovery
- Stored in `/scheduled/` subfolder
- Subject to retention policy

### 3. Import Backups
- Automatically created before CSV imports (if enabled)
- Stored in `/imports/` subfolder
- Helps recover from bad imports

### 4. Maintenance Backups
- Created before cleanup or clear operations
- Stored in `/maintenance/` subfolder
- Safety net for destructive operations

## Configuration

### Basic Settings

```php
// In config/translation-manager.php
return [
    'backupEnabled' => true,
    'backupSchedule' => 'daily', // Options: 'manual', 'daily', 'weekly', 'monthly'
    'backupRetention' => 30, // days (0 = keep forever)
    'backupBeforeImport' => true,
    'backupBeforeRestore' => true,
    'backupPath' => '@storage/translation-manager/backups',
];
```

### Schedule Options

- **manual**: No automatic backups (default)
- **daily**: Creates backup every 24 hours
- **weekly**: Creates backup every 7 days
- **monthly**: Creates backup every 30 days

### Automatic Recovery

The backup system automatically recovers from queue failures:
1. On every page load, the plugin checks if a scheduled backup job exists
2. If no job is found and backups are enabled, it creates a new one
3. This ensures backups continue even after server restarts or queue clears

## Using the CP Interface

### Creating Manual Backups
1. Navigate to **Translations → Backups**
2. Click **Create Backup** button
3. Backup is created immediately with current timestamp

### Viewing Backups
The backup list shows:
- **Date**: When the backup was created
- **Type**: Folder organization (Scheduled, Import, Maintenance, Manual)
- **Reason**: Why the backup was created
- **Created By**: User who triggered the backup
- **Translations**: Number of translations backed up
- **Size**: Total backup size

### Restoring Backups
1. Find the backup you want to restore
2. Click the gear icon → **Restore**
3. Confirm the action (a safety backup is created first)
4. All translations are replaced with the backup version

### Downloading Backups
1. Click the gear icon → **Download**
2. Receive a ZIP file containing:
   - All translation data (JSON)
   - Generated PHP files
   - Metadata about the backup

## Console Commands

### Create Backup
```bash
# Create a manual backup
ddev craft translation-manager/backup/create

# Create backup with custom reason
ddev craft translation-manager/backup/create --reason="Before major update"
```

### Scheduled Backup
```bash
# Run scheduled backup (for cron jobs)
ddev craft translation-manager/backup/scheduled
```

This command:
- Checks if backup is due based on schedule
- Creates backup if needed
- Respects your retention policy

### List Backups
```bash
# List all backups
ddev craft translation-manager/backup/list

# List with details
ddev craft translation-manager/backup/list --detailed
```

## Folder Structure

```
storage/translation-manager/backups/
├── scheduled/           # Automated backups
│   ├── 2025-01-16_03-00-00/
│   └── 2025-01-15_03-00-00/
├── imports/            # Pre-import backups
│   └── 2025-01-16_14-30-00/
├── maintenance/        # Pre-cleanup backups
│   └── 2025-01-16_10-15-00/
├── manual/             # User-created backups
│   └── 2025-01-16_09-45-00/
└── other/              # Miscellaneous backups
```

## Retention Policy

- Backups are automatically cleaned based on `backupRetention` setting
- Manual backups are **never** automatically deleted
- Set retention to `0` to keep all backups forever
- Cleanup runs after each scheduled backup

## Best Practices

1. **Regular Manual Backups**: Create manual backups before major changes
2. **Monitor Disk Space**: Backups can accumulate, especially with daily schedules
3. **Test Restoration**: Periodically test restoring from backups
4. **External Backups**: Consider copying important backups off-server
5. **Queue Health**: Ensure Craft's queue is running for scheduled backups

## Troubleshooting

### Scheduled Backups Not Running

1. Check if queue is running:
   ```bash
   ddev craft queue/info
   ```

2. Manually run queue:
   ```bash
   ddev craft queue/run
   ```

3. For production, use supervisor or systemd to keep queue running

### Backup Restoration Fails

1. Check file permissions on backup directory
2. Ensure sufficient disk space
3. Check Craft logs for detailed errors
4. Try downloading and examining the backup ZIP

### Storage Issues

1. Monitor backup folder size
2. Adjust retention policy if needed
3. Manually delete old backups from `/scheduled/` if necessary
4. Remember: manual backups must be deleted manually

## Security Considerations

1. **Backup Path**: Ensure backup path is not web-accessible
2. **Permissions**: Restrict file permissions on backup directory
3. **Sensitive Data**: Backups contain all translation data
4. **Access Control**: Only admins with proper permissions can manage backups