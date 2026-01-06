---
title: Logging Guide
category: reference
order: 2
description: Complete logging documentation, log levels, configuration, and monitoring
keywords: logging, logs, debugging, monitoring, errors
relatedPages:
  - slug: config-reference
    title: Configuration Reference
  - slug: troubleshooting-guide
    title: Troubleshooting Guide
---

# Translation Manager Logging

Translation Manager uses the [LindemannRock Logging Library](https://github.com/LindemannRock/craft-logging-library) for centralized, structured logging across all LindemannRock plugins.

## Log Levels

- **Error**: Critical errors only (default)
- **Warning**: Errors and warnings
- **Info**: General information
- **Debug**: Detailed debugging (includes performance metrics, requires devMode)

## Configuration

### Control Panel
1. Navigate to **Settings → Translation Manager → General**
2. Scroll to **Logging Settings**
3. Select desired log level from dropdown
4. Click **Save**

### Config File
```php
// config/translation-manager.php
return [
    'pluginName' => 'Translations',  // Optional: Customize plugin name shown in logs interface
    'logLevel' => 'error',           // error, warning, info, or debug
];
```

**Notes:**
- The `pluginName` setting customizes how the plugin name appears in the log viewer interface (page title, breadcrumbs, etc.). If not set, it defaults to "Translation Manager".
- Debug level requires Craft's `devMode` to be enabled. If set to debug with devMode disabled, it automatically falls back to info level.

## Log Files

- **Location**: `storage/logs/translation-manager-YYYY-MM-DD.log`
- **Retention**: 30 days (automatic cleanup via Logging Library)
- **Format**: Structured JSON logs with context data
- **Web Interface**: View and filter logs in CP at Translation Manager → Logs

## What's Logged

The plugin logs meaningful events using context arrays for structured data. All logs include user context when available.

### Settings Operations (SettingsController)

- **[INFO]** `User requested clear Formie translations` - When user initiates clearing Formie translations
- **[INFO]** `Created backup before clearing Formie translations` - Automatic backup before clear
  - Context: `backupPath`
- **[ERROR]** `Failed to create backup before clearing Formie translations` - Backup failure
  - Context: `error` (exception message)
- **[INFO]** `User requested clear site translations` - When user initiates clearing site translations
- **[INFO]** `Created backup before clearing site translations` - Automatic backup before clear
  - Context: `backupPath`
- **[ERROR]** `Failed to create backup before clearing site translations` - Backup failure
  - Context: `error` (exception message)
- **[INFO]** `User requested clear all translations` - When user initiates clearing all translations
- **[INFO]** `Created backup before clearing all translations` - Automatic backup before clear
  - Context: `backupPath`
- **[ERROR]** `Failed to create backup before clearing all translations` - Backup failure
  - Context: `error` (exception message)

### Settings Model (Settings)

- **[WARNING]** `Log level "debug" from config file changed to "info"` - When debug level used without devMode (config override)
- **[WARNING]** `Log level automatically changed from "debug" to "info"` - When debug level auto-corrected (DB setting)
- **[ERROR]** `Failed to get volume path` - Volume path retrieval errors
  - Context: `error` (exception message)
- **[WARNING]** `Backup path was pointing to root directory` - Security warning with automatic path correction
- **[ERROR]** `Failed to load Translation Manager settings from database` - Database query errors
  - Context: `error` (exception message)
- **[ERROR]** `Failed to save Translation Manager settings` - Database save errors
  - Context: `error` (exception message)

### Translation Operations (TranslationsService)

#### Search & Query
- **[DEBUG]** `Getting translations with filters` - Translation query operations
  - Context: `filters` (filter criteria)
- **[INFO]** `Searching for` - Search operations
  - Context: `searchTerm`
- **[DEBUG]** `Search SQL` - SQL query details
  - Context: `sql` (query string)

#### Template Scanning
- **[INFO]** `Template scanner starting` - When template scan begins
  - Context: `category` (translation category)
- **[INFO]** `Template scanner path` - Template path being scanned
  - Context: `path`
- **[INFO]** `Template scanner results` - Scan completion summary
  - Context: `found`, `new`, `unused` (counts)
- **[INFO]** `Template scanner: Created new translation` - New translation found in templates
  - Context: `key`
- **[INFO]** `Template scanner: Created new multi-site translation` - New multi-site translation created
  - Context: `key`, `sites`
- **[INFO]** `Template scanner: Marked as unused` - Translation not found in templates
  - Context: `key`
- **[DEBUG]** `Template scanner: Similar found keys` - Similar keys detected
  - Context: `similarKeys` (array of similar keys)
- **[WARNING]** `Template scanner: Reactivated` - Previously unused translation found again
  - Context: `key`
- **[ERROR]** `Template scanning failed` - Template scan errors
  - Context: `error` (exception message)

#### Twig Processing
- **[INFO]** `Extracted plain text from Twig code` - When Twig code contains translatable text
  - Context: `original`, `extracted`
- **[INFO]** `Skipping translation with Twig code` - When Twig code has no plain text
  - Context: `key`, `value`
- **[WARNING]** `Template scanner: Unescaped` - Security warning for unescaped translations
  - Context: `key`, `file`
- **[WARNING]** `Template scanner: Found dynamic translation using _globals.primaryTranslationCategory` - Dynamic category usage
  - Context: `file`, `line`

#### Usage Checking
- **[INFO]** `Starting usage check` - Usage check initiation
  - Context: `count` (translations to check)
- **[INFO]** `Found active texts in forms` - Active translations in forms
  - Context: `count`
- **[DEBUG]** `Checking formie translation` - Individual translation check
  - Context: `key`, `value`
- **[DEBUG]** `Form submit button` - Submit button translation
  - Context: `text`, `formHandle`
- **[DEBUG]** `Form submit message` - Submit message translation
  - Context: `text`, `formHandle`
- **[DEBUG]** `Form error message` - Error message translation
  - Context: `text`, `formHandle`
- **[INFO]** `Marking translation as unused` - Setting unused status
  - Context: `id`, `key`
- **[INFO]** `Successfully marked as unused` - Unused status updated
- **[ERROR]** `Failed to update unused status` - Update failure

#### Reactivation
- **[INFO]** `Reactivated translation` - Previously disabled translation reactivated
  - Context: `key`

#### Clear Operations
- **[INFO]** `Cleared Formie translations` - Formie translations deleted
  - Context: `count`
- **[INFO]** `Cleared site translations` - Site translations deleted
  - Context: `count`
- **[INFO]** `Cleared ALL translations` - All translations deleted
  - Context: `count`
- **[INFO]** `Deleted stale Formie file` - Old translation file removed
  - Context: `file`
- **[INFO]** `Deleted site English translation file` - English translation file removed
  - Context: `file`
- **[INFO]** `Deleted site Arabic translation file` - Arabic translation file removed
  - Context: `file`

#### Skip Patterns
- **[INFO]** `Starting applySkipPatternsToExisting` - Skip pattern application
  - Context: `patterns` (array of patterns)
- **[INFO]** `No skip patterns configured` - No patterns to apply
- **[INFO]** `Found site translations` - Translations to check
  - Context: `count`
- **[INFO]** `Checking translation` - Individual translation check
  - Context: `key`, `value`
- **[INFO]** `Checking pattern` - Pattern evaluation
  - Context: `pattern`
- **[INFO]** `Found matching translation` - Translation matches skip pattern
  - Context: `key`, `pattern`
- **[INFO]** `Successfully deleted translation` - Matching translation removed
  - Context: `key`
- **[WARNING]** `Translation record not found for deletion` - Record already deleted
  - Context: `id`
- **[INFO]** `Completed applying skip patterns to existing translations` - Operation complete
  - Context: `deleted`, `checked`, `patterns`

#### Cleanup
- **[INFO]** `Cleaned up unused translations` - Unused translations removed
  - Context: `deleted`
- **[INFO]** `Regenerated Formie translation files after cleanup` - Files regenerated post-cleanup

### Export Operations (ExportService)

- **[INFO]** `Starting exportAll()` - Export all operation initiated
- **[INFO]** `About to export Formie translations` - Formie export starting
- **[INFO]** `Formie export result` - Formie export completed
  - Context: `success` (boolean)
- **[INFO]** `About to export site translations` - Site export starting
- **[INFO]** `Site export result` - Site export completed
  - Context: `success` (boolean)
- **[INFO]** `exportAll() completed` - All exports finished
- **[INFO]** `Starting Formie translation export` - Formie export details
- **[INFO]** `Found Formie translations to export` - Translations ready
  - Context: `count`
- **[INFO]** `No Formie translations to export` - No translations found
- **[INFO]** `Deleted stale Formie file` - Old file cleanup
  - Context: `file`
- **[INFO]** `Exported Formie translations` - Formie export complete
  - Context: `enFile`, `arFile`, `enCount`, `arCount`
- **[INFO]** `Starting site translation export` - Site export details
  - Context: `category`
- **[INFO]** `Found site translations to export` - Translations ready
  - Context: `count`
- **[INFO]** `No site translations to export` - No translations found
- **[INFO]** `Deleted stale file` - Old file cleanup
  - Context: `file`
- **[INFO]** `Exported site translations` - Site export complete
  - Context: `enFile`, `arFile`, `enCount`, `arCount`
- **[INFO]** `Exporting selected translations` - Selected export operation
  - Context: `count`
- **[INFO]** `Writing translation file` - File write operation
  - Context: `path`, `count`
- **[ERROR]** `Failed to write translation file` - Write failure
  - Context: `tempFile`
- **[ERROR]** `Failed to move translation file` - Move failure
  - Context: `from`, `to`
- **[INFO]** `Successfully wrote translation file` - File written
  - Context: `path`

### Backup Operations (BackupService / VolumeBackupService)

#### Configuration
- **[INFO]** `Using volume for backups` - Volume backup enabled
  - Context: `volumeUid`, `volumeName`
- **[INFO]** `Using local storage for backups` - Local backup storage
  - Context: `path`
- **[INFO]** `Created backup directory in volume` - Volume directory created
  - Context: `path`
- **[WARNING]** `Could not create volume directory, will create on demand` - Directory creation deferred
  - Context: `path`, `error`

#### Creation
- **[INFO]** `Creating backup` - Backup operation starting
  - Context: `type`, `auto`, `includeFormie`, `includeSite`
- **[WARNING]** `No translations to backup` - Empty backup skipped
- **[INFO]** `No translations to backup - skipping backup creation` - Empty backup notice
- **[ERROR]** `Failed to create backup` - Backup creation failure
  - Context: `error` (exception message)
- **[INFO]** `Backup created in volume` - Volume backup success
  - Context: `filename`, `size`, `translationCount`, `volumeName`
- **[ERROR]** `Failed to create volume backup` - Volume backup failure
  - Context: `error` (exception message)
- **[INFO]** `Backup created locally` - Local backup success
  - Context: `filename`, `size`, `translationCount`, `path`
- **[ERROR]** `Failed to create local backup` - Local backup failure
  - Context: `error` (exception message)

#### Restoration
- **[ERROR]** `Failed to restore volume backup` - Volume restore failure
  - Context: `error` (exception message)
- **[ERROR]** `Failed to restore local backup` - Local restore failure
  - Context: `error` (exception message)
- **[INFO]** `Translations restored` - Restore success
  - Context: `count`

#### Management
- **[ERROR]** `Failed to list volume backups` - Backup listing failure
  - Context: `error` (exception message)
- **[INFO]** `Volume backup deleted` - Volume backup removed
  - Context: `backup`
- **[ERROR]** `Failed to delete volume backup` - Delete failure
  - Context: `backup`, `error`
- **[INFO]** `Local backup deleted` - Local backup removed
  - Context: `backup`
- **[ERROR]** `Failed to delete local backup` - Delete failure
  - Context: `backup`, `error`

#### Cleanup
- **[INFO]** `Cleaning up old backups` - Cleanup starting
  - Context: `retentionDays`, `cutoffDate`
- **[INFO]** `Found old backups to delete` - Old backups identified
  - Context: `count`, `backups` (array)
- **[INFO]** `Deleted old backup` - Backup removed
  - Context: `filename`, `age`
- **[ERROR]** `Failed to delete old backup` - Cleanup failure
  - Context: `filename`, `error`
- **[INFO]** `Backup cleanup completed` - Cleanup finished
  - Context: `deleted`, `failed`, `kept`

### Formie Integration (FormieService)

- **[INFO]** `Captured translations for form` - Form translations captured
  - Context: `handle` (form handle)
- **[DEBUG]** `Processing field type` - Field type processing
  - Context: `type`, `fieldHandle`
- **[DEBUG]** `Found options for field` - Field options detected
  - Context: `fieldHandle`, `optionCount`
- **[DEBUG]** `Processing option` - Individual option processing
  - Context: `label`, `value`
- **[DEBUG]** `Captured option` - Option translation captured
  - Context: `label`
- **[DEBUG]** `No options property found` - Field has no options
  - Context: `fieldHandle`
- **[DEBUG]** `HTML field found` - HTML field detected
  - Context: `fieldHandle`, `content`
- **[DEBUG]** `HTML field has no content or property missing` - Empty HTML field
  - Context: `fieldHandle`
- **[DEBUG]** `Paragraph field found` - Paragraph field detected
  - Context: `fieldHandle`, `content`
- **[DEBUG]** `Paragraph field has no content or property missing` - Empty paragraph field
  - Context: `fieldHandle`
- **[INFO]** `Running form usage check after form save` - Post-save usage check
- **[INFO]** `Checked form translations for usage` - Usage check complete
  - Context: `count`

### Import Operations (ImportController)

- **[INFO]** `Starting import` - Import operation initiated
  - Context: `filename`, `filesize`
- **[INFO]** `Created pre-import backup` - Automatic backup before import
  - Context: `backupPath`
- **[ERROR]** `Failed to create pre-import backup` - Backup failure
  - Context: `error` (exception message)
- **[INFO]** `Import completed` - Import finished
  - Context: `imported`, `updated`, `skipped`
- **[ERROR]** `Import failed` - Import error
  - Context: `error` (exception message)

### Backup Controller (BackupController)

#### Backup Management UI
- **[ERROR]** `Failed to fetch backups` - Backup listing error
  - Context: `error` (exception message)
- **[INFO]** `User requested manual backup creation` - Manual backup initiated from UI
- **[ERROR]** `Backup creation failed` - Manual backup creation failed
  - Context: `error` (exception message)
- **[INFO]** `User requested backup restore` - Backup restore initiated
  - Context: `backup` (backup filename)
- **[WARNING]** `Invalid backup name format attempted` - Invalid backup filename format
  - Context: `backup` (backup filename)
- **[INFO]** `User requested backup deletion` - Backup deletion initiated
  - Context: `backup` (backup filename)
- **[INFO]** `Backup deletion completed successfully` - Backup deleted successfully
  - Context: `backup` (backup filename)
- **[WARNING]** `Backup deletion failed` - Backup deletion failed
  - Context: `backup` (backup filename)
- **[INFO]** `User requested backup download` - Backup download initiated
  - Context: `backup` (backup filename)

### Export Controller (ExportController)

#### Export UI Operations
- **[ERROR]** `Export failed` - Export operation failed
  - Context: `error` (exception message)
- **[INFO]** `User requested file export` - File export initiated from UI
- **[INFO]** `Export preparation` - Export preparation details
  - Context: `formieCount`, `siteCount`, `totalCount`
- **[INFO]** `Export completed` - Export completed successfully
  - Context: `message` (completion message)
- **[INFO]** `User requested Formie export only` - Formie-only export initiated
- **[INFO]** `Formie export preparation` - Formie export details
  - Context: `formieCount`
- **[INFO]** `Formie export completed` - Formie export completed
  - Context: `message` (completion message)
- **[INFO]** `User requested site/category export only` - Site/category export initiated
- **[INFO]** `Site export preparation` - Site export details
  - Context: `siteCount`
- **[INFO]** `Site export completed` - Site export completed
  - Context: `message` (completion message)

### Maintenance Controller (MaintenanceController)

- **[INFO]** `Created backup before cleaning unused translations` - Backup created before cleanup
  - Context: `backupPath` (backup file path)
- **[ERROR]** `Failed to create backup before cleaning unused translations` - Backup creation failed before cleanup
  - Context: `error` (exception message)
- **[INFO]** `Created backup before cleaning unused translations` - Backup created (alternate location)
  - Context: `backupPath` (backup file path)
- **[ERROR]** `Failed to create backup` - Backup creation failed
  - Context: `error` (exception message)

### Integration Service (IntegrationService)

- **[ERROR]** `Failed to check usage for {integrationName}` - Usage check failed for integration
- **[DEBUG]** `IntegrationService: Already loaded, skipping` - Integrations already loaded
- **[INFO]** `IntegrationService: Loading integrations for the first time` - Initial integration loading
- **[INFO]** `IntegrationService: Integration loading completed` - Integration loading finished
- **[ERROR]** `Failed to register built-in integration {name}` - Built-in integration registration failed
- **[ERROR]** `Failed to initialize integration {name}` - Integration initialization failed

### Base Integration (BaseIntegration)

- **[DEBUG]** `Capturing translation` - Translation capture operation
  - Context: `key`, `value`, `category`, `integration`
- **[INFO]** `Marked translation as unused` - Translation marked as unused
  - Context: `id`, `key`

### Formie Integration (FormieIntegration)

#### Plugin Availability
- **[INFO]** `FormieIntegration: Not available - Formie plugin not found or not enabled` - Formie plugin not available

#### Form Operations
- **[INFO]** `FormieIntegration: Form saved` - Form save event
  - Context: `handle` (form handle)
- **[INFO]** `FormieIntegration: Form deleted` - Form deletion event
  - Context: `handle` (form handle)
- **[INFO]** `Checking Formie translation usage` - Usage check initiated
- **[INFO]** `Checked Formie translations for usage` - Usage check completed
  - Context: `count` (number of translations checked)
- **[INFO]** `Processing form save` - Processing form save event
  - Context: `handle` (form handle)
- **[INFO]** `Captured translations from form` - Translations captured from form
  - Context: `handle`, `count`
- **[INFO]** `Processing form deletion` - Processing form deletion event
  - Context: `handle` (form handle)
- **[INFO]** `Marked translations as unused after form deletion` - Translations marked unused
  - Context: `marked` (number of translations marked)

#### Field Processing
- **[DEBUG]** `Processing Formie field: {fieldClass} ({fieldHandle})` - Field processing debug
- **[INFO]** `Unable to capture default Formie translations` - Default translation capture failed
  - Context: `error` (exception message)

### Create Backup Job (CreateBackupJob)

- **[INFO]** `Scheduled backup created successfully` - Scheduled backup completed
  - Context: `filename` (backup filename)
- **[INFO]** `Cleaned old backups` - Old backups cleaned during scheduled backup
  - Context: `deleted` (number of backups deleted)
- **[INFO]** `Next backup scheduled` - Next scheduled backup queued
  - Context: `delay_seconds` (delay until next backup)

### Main Plugin (TranslationManager)

- **[ERROR]** `Invalid Translation Manager configuration` - Configuration validation failed
  - Context: `errors` (validation errors), `configFile` (path to config file)
- **[INFO]** `Scheduled initial backup job` - Initial backup job scheduled at plugin init
  - Context: `schedule` (backup schedule type)
- **[INFO]** `Backup scheduling disabled` - Backup scheduling turned off
- **[INFO]** `Scheduled backup job already exists, not creating a new one` - Job already in queue
- **[INFO]** `Scheduled backup job queued` - Backup job scheduled
  - Context: `schedule` (backup schedule type)

## Log Management

### Via Control Panel
1. Navigate to **Translation Manager → Logs**
2. Filter by date, level, or search terms
3. Download log files for external analysis
4. View file sizes and entry counts
5. Auto-cleanup after 30 days (configurable via Logging Library)

### Via Command Line

**View today's log**:
```bash
tail -f storage/logs/translation-manager-$(date +%Y-%m-%d).log
```

**View specific date**:
```bash
cat storage/logs/translation-manager-2025-01-15.log
```

**Search across all logs**:
```bash
grep "Export failed" storage/logs/translation-manager-*.log
```

**Filter by log level**:
```bash
grep "\[ERROR\]" storage/logs/translation-manager-*.log
```

## Log Format

Each log entry follows structured JSON format with context data:

```json
{
  "timestamp": "2025-01-15 14:30:45",
  "level": "INFO",
  "message": "Backup created in volume",
  "context": {
    "filename": "backup-2025-01-15-143045.json",
    "size": "245KB",
    "translationCount": 150,
    "volumeName": "Assets",
    "userId": 1
  },
  "category": "lindemannrock\\translationmanager\\services\\BackupService"
}
```

## Using the Logging Trait

All services in Translation Manager use the `LoggingTrait` from the LindemannRock Logging Library:

```php
use lindemannrock\logginglibrary\traits\LoggingTrait;

class MyService extends Component
{
    use LoggingTrait;

    public function myMethod()
    {
        // Info level - general operations
        $this->logInfo('Operation started', ['param' => $value]);

        // Warning level - important but non-critical
        $this->logWarning('Missing data', ['key' => $missingKey]);

        // Error level - failures and exceptions
        $this->logError('Operation failed', ['error' => $e->getMessage()]);

        // Debug level - detailed information
        $this->logDebug('Processing item', ['item' => $itemData]);
    }
}
```

## Best Practices

### 1. DO NOT Log in init() ⚠️

The `init()` method is called on **every request** (every page load, AJAX call, etc.). Logging there will flood your logs with duplicate entries.

```php
// ❌ BAD - Causes log flooding
public function init(): void
{
    parent::init();
    $this->logInfo('Plugin initialized');  // Called on EVERY request!
}

// ✅ GOOD - Log actual operations
public function processTranslations(): void
{
    $this->logInfo('Translation processing started', ['count' => $count]);
    // ... your logic
    $this->logInfo('Translation processing completed', ['count' => $count]);
}
```

### 2. Always Use Context Arrays

Use the second parameter for variable data, not string concatenation:

```php
// ❌ BAD - Concatenating variables into message
$this->logError('Export failed: ' . $e->getMessage());
$this->logInfo('Processing ' . $count . ' items');

// ✅ GOOD - Use context array for variables
$this->logError('Export failed', ['error' => $e->getMessage()]);
$this->logInfo('Processing items', ['count' => $count]);
```

**Why Context Arrays Are Better:**
- Structured data for log analysis tools
- Easier to search and filter in log viewer
- Consistent formatting across all logs
- Automatic JSON encoding with UTF-8 support

### 3. Use Appropriate Log Levels

- **debug**: Internal state, variable dumps (requires devMode)
- **info**: Normal operations, user actions
- **warning**: Unexpected but handled situations
- **error**: Actual errors that prevent operation

### 4. Security

- Never log passwords or sensitive data
- Be careful with user input in log messages
- Never log API keys, tokens, or credentials

## Performance Considerations

- **Error/Warning levels**: Minimal performance impact, suitable for production
  - Logs only failures and important warnings
  - Suitable for production environments
- **Info level**: Moderate logging, useful for tracking operations
  - User-initiated operations (clear, export, import, backup)
  - Translation scanning results
  - Backup/export/import completion
  - Integration events (form saves, deletions)
  - Job completions (scheduled backups, cleanup)
  - Auto-backup creation before destructive operations
- **Debug level**: Extensive logging, use only in development (requires devMode)
  - Includes performance metrics
  - Logs database queries and SQL
  - Tracks method execution
  - Translation filtering details
  - Template scanner similarity detection
  - Form field processing (Formie)
  - Integration loading and registration
  - Settings save operations with database results

## Requirements

Translation Manager logging requires:
- **lindemannrock/logginglibrary** plugin (installed automatically as dependency)
- Write permissions on `storage/logs` directory
- Craft CMS 5.x or later

## Troubleshooting

If logs aren't appearing:

1. **Check permissions**: Verify `storage/logs` directory is writable
2. **Verify library**: Ensure LindemannRock Logging Library is installed and enabled
3. **Check log level**: Confirm log level allows the messages you're looking for
4. **devMode for debug**: Debug level requires `devMode` enabled in `config/general.php`
5. **Check CP interface**: Use Translation Manager → Logs to verify log files exist

## Common Scenarios

### Backup Operations Issues

Monitor backup operations:

```bash
grep -i "backup" storage/logs/translation-manager-*.log
```

Look for:

- `User requested manual backup creation` - Manual backup from UI
- `Scheduled backup created successfully` - Scheduled backup completed
- `Backup creation failed` - Backup creation error
- `Failed to fetch backups` - Backup listing error
- `User requested backup restore` - Restore operation
- `User requested backup deletion` - Deletion operation
- `Backup deletion completed successfully` - Successful deletion
- `Backup deletion failed` - Deletion error
- `Created backup before clearing translations` - Auto-backup before clear operation

If backups fail:

- Check volume configuration (if using volume backups)
- Verify local storage permissions
- Ensure sufficient disk space
- Review backup path configuration
- Check database connectivity for metadata

### Export Operations Issues

Track export operations:

```bash
grep -i "export" storage/logs/translation-manager-*.log
```

Look for:

- `User requested file export` - File export initiated
- `Export preparation` - Export being prepared
- `Export completed` - Successful export
- `Export failed` - Export error
- Formie/site-specific export messages

If exports fail:

- Check translation counts (ensure translations exist)
- Verify export file path permissions
- Review translation data integrity
- Ensure temp directory is writable
- Check for malformed translation data

### Import Operations Issues

Monitor import operations:

```bash
grep -i "import" storage/logs/translation-manager-*.log
```

Look for:

- `Import started` - Import initiated
- `Import completed` - Import finished successfully
- `Import failed` - Import error
- `Created pre-import backup` - Auto-backup created
- `Skipping translation with empty text` - Empty translations skipped
- `Invalid site ID for translation` - Invalid site reference
- `Malicious content detected` - Security warning

If imports fail:

- Check file format and encoding (UTF-8)
- Verify site IDs are valid
- Review backup creation (should succeed first)
- Check for malformed JSON/CSV data
- Ensure database has capacity for new records

### Integration System Issues

Monitor integration operations:

```bash
grep -i "integration" storage/logs/translation-manager-*.log
```

Look for:

- `IntegrationService: Loading integrations for the first time` - Integration load
- `IntegrationService: Integration loading completed` - Load complete
- `Failed to register built-in integration` - Registration error
- `Failed to initialize integration` - Initialization error
- `Failed to check usage for integration` - Usage check error

If integrations fail:

- Verify required plugins installed (e.g., Formie)
- Check integration-specific configuration
- Review error messages for specific integration
- Ensure database tables exist

### Formie Integration Issues

Debug Formie-specific integration:

```bash
grep -i "formie" storage/logs/translation-manager-*.log
```

Look for:

- `FormieIntegration: Not available` - Formie plugin not found
- `FormieIntegration: Form saved` - Form save detected
- `FormieIntegration: Form deleted` - Form deletion detected
- `Processing form save` - Form being processed
- `Captured translations from form` - Translations extracted
- `Processing Formie field` - Field-level processing
- `Unable to capture default Formie translations` - Default capture failed

If Formie integration fails:

- Verify Formie plugin installed and enabled
- Check Formie version compatibility
- Review form structure and fields
- Ensure translation category is configured

### Scheduled Backup Issues

Monitor scheduled backup operations:

```bash
grep -i "scheduled backup\|CreateBackupJob" storage/logs/translation-manager-*.log
```

Look for:

- `Scheduled initial backup job` - Job scheduled at init
- `Scheduled backup job queued` - Job queued
- `Scheduled backup job already exists` - Job already queued
- `Scheduled backup created successfully` - Backup completed
- `Cleaned old backups` - Retention cleanup
- `Next backup scheduled` - Next job scheduled
- `Backup scheduling disabled` - Scheduling turned off

If scheduled backups aren't working:

- Verify backup scheduling is enabled in settings
- Check backup schedule frequency (daily/weekly/monthly)
- Ensure queue runner is active
- Review retention days setting
- Check for queue job failures

### Translation Scanning Issues

Debug template scanning:

```bash
grep -i "template scanner" storage/logs/translation-manager-*.log
```

Look for:

- `Template scanner starting` - Scan initiated
- `Template scanner results` - Scan completed with counts
- `Created new translation` - New translation found
- `Marked as unused` - Translation not found in templates
- `Reactivated` - Previously unused translation found again
- `Template scanning failed` - Scan error
- `Found dynamic translation using _globals` - Dynamic category usage warning
- `Unescaped` - Security warning for unescaped translations

If scanning fails or produces unexpected results:

- Verify template paths are correct
- Check translation category configuration
- Review skip patterns (may be excluding translations)
- Ensure templates are readable
- Check for Twig syntax errors

### Configuration Errors

Monitor configuration issues:

```bash
grep -i "configuration\|config file" storage/logs/translation-manager-*.log
```

Look for:

- `Invalid Translation Manager configuration` - Config validation failed
- `Log level from config file changed` - Debug level adjustment

If configuration errors occur:

- Review `config/translation-manager.php` file
- Check validation error details in context
- Verify all required settings are present
- Ensure data types are correct
- Review config file syntax

## Development Tips

### Enable Debug Logging

For detailed troubleshooting during development:

```php
// config/translation-manager.php
return [
    'dev' => [
        'logLevel' => 'debug',
    ],
];
```

This provides:

- Translation filtering and search SQL queries
- Template scanner similarity detection
- Form field processing details (Formie)
- Integration loading and registration details
- Settings save operation details
- Database operation tracking

### Monitor Specific Operations

Track specific operations using grep:

```bash
# Monitor all backup operations
grep -i "backup" storage/logs/translation-manager-*.log

# Watch logs in real-time
tail -f storage/logs/translation-manager-$(date +%Y-%m-%d).log

# Check all errors
grep "\[ERROR\]" storage/logs/translation-manager-*.log

# Monitor template scanning
grep "Template scanner" storage/logs/translation-manager-*.log

# Track export operations
grep -i "export" storage/logs/translation-manager-*.log

# Watch import operations
grep -i "import" storage/logs/translation-manager-*.log

# Monitor Formie integration
grep -i "formie" storage/logs/translation-manager-*.log

# Track integration system
grep -i "integration" storage/logs/translation-manager-*.log

# Watch scheduled backups
grep -i "scheduled backup" storage/logs/translation-manager-*.log

# Monitor translation operations
grep -i "translation" storage/logs/translation-manager-*.log

# Track skip patterns
grep -i "skip pattern" storage/logs/translation-manager-*.log

# Monitor cleanup operations
grep -i "cleanup\|cleaned" storage/logs/translation-manager-*.log
```

### Debug Translation Scanning

When troubleshooting translation scanning:

```bash
# Find scan results
grep "Template scanner results" storage/logs/translation-manager-*.log

# Track new translations
grep "Created new translation" storage/logs/translation-manager-*.log

# Monitor unused translations
grep "Marked as unused" storage/logs/translation-manager-*.log

# Watch for reactivations
grep "Reactivated" storage/logs/translation-manager-*.log
```

Review the context to see:

- How many translations found vs created vs unused
- Similar keys that might indicate duplicates
- Dynamic translation usage patterns
- Unescaped translation warnings

### Performance Monitoring

Track operation performance:

```bash
# Monitor backup creation frequency
grep "backup created" storage/logs/translation-manager-*.log

# Check export performance
grep "Export completed" storage/logs/translation-manager-*.log

# Track import performance
grep "Import completed" storage/logs/translation-manager-*.log

# Monitor cleanup operations
grep "Cleaned\|cleanup" storage/logs/translation-manager-*.log
```

Enable debug mode to see:

- SQL query details for searches
- Translation processing steps
- Integration loading performance
- Field processing details
