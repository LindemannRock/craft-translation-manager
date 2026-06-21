# Backup system

Translation Manager protects your translations before anything destructive happens. Every import, cleanup, clear, and restore creates a backup first — and you can create your own restore points any time — so a bad import or an over-eager cleanup is always recoverable.

## What you'll use it for

- Creating a restore point before a big import or a round of cleanup
- Recovering after a CSV import goes wrong
- Running scheduled (daily/weekly/monthly) safety backups without thinking about it
- Keeping backups off-server in cloud storage (S3, Servd, Wasabi)
- Handing a portable ZIP of translations to another environment

## Create and restore a backup in the Control Panel

1. Go to **Translation Manager → Backups**.
2. Click **Create Backup Now** — the backup is captured with the current timestamp and a reason.
3. To roll back, find a backup in the list, click the gear icon → **Restore**, and confirm. A fresh safety backup is taken before the restore runs, so the restore itself is reversible.

![Backups list in the Translation Manager Control Panel](images/backups-list.webp)

The list shows each backup's **date**, **type** (which folder it lives in), **reason**, **translation count**, and **size**.

## Backup types

Each kind of backup lives in its own subfolder so you can tell at a glance why it was created:

| Type | When it's created | Folder | Retention |
|------|-------------------|--------|-----------|
| **Manual** | You click *Create Backup* | `/manual/` | Never auto-deleted |
| **Scheduled** | On your daily/weekly/monthly schedule (via Craft's queue, with automatic recovery) | `/scheduled/` | Subject to retention policy |
| **Import** | Before a CSV import (when enabled) | `/imports/` | Subject to retention policy |
| **Maintenance** | Before a cleanup or clear operation | `/maintenance/` | Subject to retention policy |
| **Restore / other** | Before a restore (the safety backup) | `/other/` | Subject to retention policy |

## Configuration

```php
// config/translation-manager.php
return [
    'backupEnabled' => true,
    'backupSchedule' => 'daily', // disabled, daily, weekly, monthly
    'backupRetentionDays' => 30, // days (0 = keep forever)
    'backupOnImport' => true,
];
```

### Schedule options

| Schedule | Frequency |
|----------|-----------|
| `disabled` | No automatic scheduled backups (default) |
| `daily` | Daily |
| `weekly` | Weekly |
| `monthly` | Monthly |

Craft stores a scheduled backup's queue description when the row is queued, so date/time format changes apply to newly queued rows; existing delayed rows keep their old label until they run or are requeued. Queue labels stay compact: numeric months render numerically, while short and long month settings both render as short month names.

## Console commands

Create a manual backup:

```bash title="PHP"
php craft translation-manager/backup/create
```

```bash title="DDEV"
ddev craft translation-manager/backup/create
```

Create one with a custom reason:

```bash title="PHP"
php craft translation-manager/backup/create --reason="Before update"
```

```bash title="DDEV"
ddev craft translation-manager/backup/create --reason="Before update"
```

Run a scheduled backup manually or from cron:

```bash title="PHP"
php craft translation-manager/backup/scheduled
```

```bash title="DDEV"
ddev craft translation-manager/backup/scheduled
```

List all backups:

```bash title="PHP"
php craft translation-manager/backup/list
```

```bash title="DDEV"
ddev craft translation-manager/backup/list
```

## Restoring, downloading, and integrity

Restore replaces all current translations with the backup's version. It requires an intact backup folder with `metadata.json` and a valid SHA-256 checksum — backups with missing metadata, missing checksum data, or modified translation JSON are rejected *before* anything is replaced.

Click the gear icon → **Download** to get a ZIP containing the translation data (JSON), the generated PHP files, and the backup metadata. Downloaded ZIPs are portable: to use one on another install without an upload flow, extract it and place its files into the expected backup folder structure under that install's configured backup storage.

## Storage structure

```text
storage/translation-manager/backups/
├── scheduled/      # Automated backups
├── imports/        # Pre-import backups
├── maintenance/    # Pre-cleanup backups
├── manual/         # User-created backups
└── other/          # Miscellaneous backups
```

## Cloud storage

Store backups in any Craft asset volume — Amazon S3, Servd, Wasabi, or any provider with Craft volume support. Configure it under **Settings → Backup → Backup Storage Volume**.

Local volumes that resolve inside `@webroot` are rejected, because backup JSON files should not be web-accessible. Remote volumes such as S3 are allowed; set the bucket/object access policy in the storage provider so backups stay private.

## Retention policy

- Automatic cleanup is based on the `backupRetentionDays` setting.
- **Manual backups are never automatically deleted.**
- Set retention to `0` to keep all backups forever.
