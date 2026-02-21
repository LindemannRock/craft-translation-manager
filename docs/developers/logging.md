# Logging

Translation Manager uses the [LindemannRock Logging Library](https://github.com/LindemannRock/craft-logging-library) for centralized logging.

## Log Levels

| Level | Description |
|-------|-------------|
| **Error** | Critical errors only |
| **Warning** | Errors and warnings |
| **Info** | General information |
| **Debug** | Detailed debugging (requires devMode) |

## Configuration

```php
// config/translation-manager.php
return [
    'logLevel' => 'info', // error, warning, info, or debug
];
```

**Note:** Debug level requires Craft's `devMode` to be enabled. If set to debug with devMode disabled, it automatically falls back to info level.

## Log Files

- **Location**: `storage/logs/translation-manager-YYYY-MM-DD.log`
- **Retention**: 30 days (automatic cleanup)
- **Format**: Structured JSON logs with context data

## Web Interface

Access logs through the Control Panel:

1. Navigate to **Translation Manager → Logs**
2. Filter by date, level, or search terms
3. Download log files for external analysis
4. View file sizes and entry counts

## What's Logged

### Error Level

- File generation failures
- Database errors
- Permission denials
- Backup failures

### Warning Level

- Missing translations
- Failed operations
- Slow operations (>1s)

### Info Level

- Translation saves
- Imports/exports
- File generation
- Backup operations
- Cleanup actions

### Debug Level

- Performance timing
- Detailed import/export steps
- Template scanning
- Queue operations

## Logging in Code

```php
use lindemannrock\translationmanager\TranslationManager;

// Log error
TranslationManager::getInstance()->logError('Operation failed', [
    'context' => 'backup',
    'error' => $e->getMessage(),
]);

// Log info
TranslationManager::getInstance()->logInfo('Translations exported', [
    'count' => $count,
    'type' => 'site',
]);

// Log debug (only in devMode)
TranslationManager::getInstance()->logDebug('Processing translation', [
    'key' => $key,
    'site' => $siteId,
]);
```

## Requirements

Requires `lindemannrock/logginglibrary` plugin (installed automatically as dependency).
