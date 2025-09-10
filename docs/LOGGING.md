# Translation Manager Logging

The Translation Manager plugin uses Craft's logging system with a dedicated log file to track plugin-related activities, making it easier to debug issues and monitor operations.

## Log File Location

All Translation Manager logs are written to daily-rotated files:
```
storage/logs/translation-manager-{date}.log
```

Example:
```
storage/logs/translation-manager-2025-07-10.log
```

## Log Configuration

The logging is configured automatically when the plugin initializes with the following settings:

- **Max File Size**: 10MB per file
- **Max Log Files**: 30 (30 days retention)
- **Log Levels**: Error, Warning (Info level is excluded for performance)
- **User Tracking**: Each log entry includes the user ID who performed the action
- **Daily Rotation**: New log file created each day with date in filename

## What Gets Logged

### Errors (Always Logged)
- Backup creation failures
- Export/Import failures
- File write errors
- API communication errors
- Database operation failures

### Warnings (Always Logged)
- Missing translations
- Empty backup attempts
- Failed pre-restore backups
- Configuration issues

### Info Level (Not Logged by Default)
- Translation creation/updates
- Routine operations
- Successful completions
- Debug information

## Using the Logging Trait

All services in the Translation Manager plugin use the `LoggingTrait` which provides consistent logging methods:

```php
// Info level - general operations (not logged in production)
$this->logInfo('Export completed', ['count' => 150, 'type' => 'forms']);

// Warning level - important but non-critical
$this->logWarning('No translations to backup');

// Error level - failures and exceptions
$this->logError('Export failed', ['error' => $e->getMessage()]);
```

## Viewing Logs

You can view the logs in several ways:

1. **View today's log**: 
   ```bash
   tail -f storage/logs/translation-manager-$(date +%Y-%m-%d).log
   ```

2. **View specific date**:
   ```bash
   cat storage/logs/translation-manager-2025-07-10.log
   ```

3. **Search across all logs**:
   ```bash
   grep "Export failed" storage/logs/translation-manager-*.log
   ```

4. **Filter by user**:
   ```bash
   grep "\[user:1\]" storage/logs/translation-manager-*.log
   ```

## Log Format

Each log entry follows this format:
```
[timestamp] [ip_address] [user:id] message | parameters [category]
```

Example:
```
2025-07-10 14:30:45 [192.168.1.1] [user:1] Failed to create backup | {"error":"Permission denied"} [lindemannrock\translationmanager\services\BackupService::logError]
```

## Enabling Info Level Logging

If you need more detailed logging for debugging, you can enable info level logs by modifying the plugin code:

In `TranslationManager.php`, find the `_configureLogging()` method and change:
```php
'levels' => ['error', 'warning'], // Only log errors and warnings
```

To:
```php
'levels' => ['error', 'warning', 'info'], // Include info messages
```

**Note**: This is not recommended for production as it can create large log files.

## Common Log Messages

### Errors
- `Failed to create backup` - Backup operation failed
- `Failed to export translations` - Export operation failed
- `Failed to write file` - File system error
- `Database operation failed` - Query error

### Warnings  
- `No translations to backup` - Backup attempted with empty data
- `Translation not found` - Requested translation doesn't exist
- `Failed to create pre-restore backup` - Non-critical backup failure

## Troubleshooting

If logs aren't appearing:

1. Check file permissions on the `storage/logs` directory
2. Verify the plugin is installed and enabled
3. Ensure Craft's logging is working (check `web-{date}.log`)
4. Verify the log file path is writable

## Integration with Craft Logs

Translation Manager logs are separate from but complementary to Craft's default logs:
- `web-{date}.log` - General web requests
- `queue-{date}.log` - Background jobs
- `console-{date}.log` - CLI commands
- `translation-manager-{date}.log` - Translation Manager specific

All follow the same daily rotation pattern for consistency.