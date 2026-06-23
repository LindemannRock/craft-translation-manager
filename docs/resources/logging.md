# Logging

Translation Manager writes structured, per-day log files through the bundled [Logging Library](https://github.com/LindemannRock/craft-logging-library).

> [!NOTE]
> Logging Library is included as a Composer dependency and downloaded automatically. Activate it in Craft to enable log viewing.

```bash title="PHP"
php craft plugin/install logging-library
```

```bash title="DDEV"
ddev craft plugin/install logging-library
```

Or via the Control Panel: **Settings → Plugins → Logging Library → Install**

Use this page when you need to check what Translation Manager did: imports, exports, generation, backups, cleanup, failed saves, permission denials, and debug-level diagnostics.

## Log levels

Four log levels are available, in order of verbosity:

| Level | What is logged |
|-------|----------------|
| `error` | Critical errors only |
| `warning` | Errors and warnings |
| `info` | General informational messages |
| `debug` | Detailed debugging, including timing and step-by-step diagnostics |

Each level includes all messages from the levels above it. `error` is the least verbose; `debug` is the most.

> [!WARNING]
> Debug level requires Craft's `devMode` to be enabled. If `logLevel` is set to `debug` while `devMode` is disabled, Translation Manager falls back to `info` and records a warning. Use `debug` for local development or short diagnostic sessions, because it can create much more log output.

## Configuration

```php
// config/translation-manager.php
return [
    'logLevel' => 'info', // 'error', 'warning', 'info', or 'debug'
];
```

For environment-specific logging, keep production quieter and enable debug only where Craft's `devMode` is enabled:

```php
// config/translation-manager.php
return [
    '*' => [
        'logLevel' => 'error',
    ],
    'production' => [
        'logLevel' => 'error',
    ],
    'staging' => [
        'logLevel' => 'warning',
    ],
    'dev' => [
        'logLevel' => 'debug',
    ],
];
```

## Log file location

```text
storage/logs/translation-manager-YYYY-MM-DD.log
```

Log files are rotated daily. Retention is managed by Logging Library, with a 30-day default.

Logs are written as structured JSON with context data alongside each message, so they can be searched in the Control Panel or ingested by external logging tools.

## Viewing logs in the CP

The **Translation Manager → Logs** screen reads, filters, and downloads these log files without leaving the Control Panel.

![Translation Manager log viewer in the Control Panel](images/logging-log-viewer.webp)

From there you can:

- Browse log entries for the current and recent days
- Filter by log level
- Search log messages and context
- View file sizes and entry counts
- Download individual log files for external analysis

The `translationManager:viewSystemLogs` permission is required to access the Logs section. The `translationManager:downloadSystemLogs` sub-permission is required to download log files. In the Craft permissions UI, both are nested under the `translationManager:viewLogs` parent group.

## What gets logged

The level of detail depends on your configured `logLevel`.

### Error (`error`)

- File generation failures
- Database errors
- Permission denials
- Backup failures
- Import or export failures

### Warning (`warning`)

- Missing translations
- Failed operations that can continue
- Debug fallback when `logLevel` is set to `debug` without `devMode`
- Slow operations

### Info (`info`)

- Translation saves
- Imports and exports
- File generation
- Backup operations
- Cleanup and maintenance actions

### Debug (`debug`)

- Performance timing
- Detailed import/export steps
- Template capture and scanning details
- Queue operations
- Per-string processing context

## Developer usage

Most sites only need the configuration and CP viewer above. Custom modules or integrations can write to the same Translation Manager log when they need related diagnostics:

```php
use lindemannrock\translationmanager\TranslationManager;

TranslationManager::getInstance()->logError('Operation failed', [
    'context' => 'backup',
    'error' => $e->getMessage(),
]);

TranslationManager::getInstance()->logInfo('Translations exported', [
    'count' => $count,
    'type' => 'site',
]);

TranslationManager::getInstance()->logDebug('Processing translation', [
    'key' => $key,
    'site' => $siteId,
]);
```

## Permissions

| Action | Permission |
|--------|------------|
| Access the Logs section in the CP | `translationManager:viewSystemLogs` |
| Download log files | `translationManager:downloadSystemLogs` |
| Logs group (parent, Craft permissions UI only) | `translationManager:viewLogs` |

See [Permissions](../developers/permissions.md) for the full permission hierarchy.
