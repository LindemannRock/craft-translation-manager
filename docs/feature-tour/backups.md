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

![Backups list in the Translation Manager Control Panel](../images/backups-list.webp)

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

Scheduled backups normally run through Craft's queue. Translation Manager keeps one delayed scheduled-backup chain for the next run and creates its successor only after a successful backup. On queue transports with a bounded delay, the plugin relays the wait through intermediate queue handoffs; those handoffs do not create backups. Local and other non-SQS queue transports retain the complete native delay. Run a queue worker with `queue/listen` or a cron-driven `queue/run` so scheduled backups fire on time.

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

Check and run a due scheduled backup directly. This command remains available for manual checks and direct-cron setups; normal automatic scheduling uses Craft's queue:

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

Craft Cloud's application filesystem is ephemeral. A custom/local path and a Craft volume backed by a local filesystem are therefore unsafe for persistent backups on Craft Cloud, even when the local path is outside `@webroot`. Select a volume that uses Craft Cloud's **Cloud** filesystem type and review Craft's [local filesystem migration guidance](https://craftcms.com/docs/cloud/assets.html#local).

On an ephemeral host, Backup settings evaluates the effective values after `config/translation-manager.php` overrides and shows a colored warning for effective local storage. A valid resolved non-local filesystem suppresses only this local-storage warning; it is not certification that a third-party filesystem is fully compatible with Craft Cloud. The warning does not change settings or backup, restore, ZIP, retention, or queue behavior. Missing or validation-invalid volumes retain the existing local fallback and warn, while an unavailable filesystem remains a separate failure condition.

## Retention policy

- Automatic cleanup is based on the `backupRetentionDays` setting.
- **Manual backups are never automatically deleted.**
- Set retention to `0` to keep all backups forever.
