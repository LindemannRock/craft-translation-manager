---
title: Troubleshooting Guide
category: troubleshooting
order: 1
description: Solutions to common issues, debugging tips, and problem resolution
keywords: troubleshooting, problems, issues, errors, debugging, solutions
relatedPages:
  - slug: logging-guide
    title: Logging Guide
  - slug: config-reference
    title: Configuration Reference
---

# Translation Manager Troubleshooting Guide

## Common Issues

### Search Not Working

**Problem**: Searching for translations returns no results or incorrect results.

**Solutions**:

1. **Check for exact matches**:
   - The search looks for exact text matches including spaces and punctuation
   - Try searching for partial text instead of the full phrase

2. **Use the Debug Search Tool**:
   ```
   /cms/translation-manager/maintenance/debug-search-page
   ```
   This tool shows:
   - Exact match results
   - Partial matches
   - Total translations in database
   - Whether the text exists with different punctuation

3. **Check filters**:
   - Ensure "Type" filter is set to "All Types" (not just Forms or Site)
   - Ensure "Status" filter is set to "All"
   - Clear any search terms and try again

4. **Common issues**:
   - Leading/trailing spaces in the text
   - Text is marked as "unused" (gray status)
   - Text contains Twig syntax (automatically excluded)

### Scheduled Backups Not Running

**Problem**: Daily backups are not being created automatically.

**Solutions**:

1. **Check backup settings**:
   - Go to **Settings → Backup**
   - Ensure "Enable Backups" is ON
   - Ensure "Backup Schedule" is not set to "Manual"

2. **Check queue status**:
   ```bash
   ddev craft queue/info
   ```

3. **Manually trigger queue**:
   ```bash
   ddev craft queue/run
   ```

4. **For production environments**, ensure queue runner is active:
   - Use supervisor, systemd, or cron to run `craft queue/run`
   - Or use Craft's built-in queue runner

### Translations Not Being Captured

**Problem**: New form fields or site translations are not appearing.

**Solutions**:

1. **For Formie fields**:
   - Save the form after adding new fields
   - Check that Formie integration is enabled in settings
   - Run manual capture: `ddev craft translation-manager/translations/capture-formie`

2. **For site translations**:
   - Ensure you're using the correct category: `{{ 'Text'|t('your-category') }}` (e.g., `{{ 'Text'|t('lindemannrock') }}`)
   - Visit the page on the frontend (translations are captured on page load)
   - Check that site translations are enabled in settings

3. **Excluded text**:
   - Text containing Twig syntax ({{, {%, {#) is automatically excluded
   - Check the logs for skipped translations

### Import Fails or Is Blocked

**Problem**: CSV import fails or is blocked by security systems.

**Solutions**:

1. **Check file format**:
   - Use UTF-8 encoding
   - Include headers: English Text, Arabic Text, Status, Context
   - Avoid special characters that might trigger security filters

2. **For Cloudflare blocks**:
   - The plugin uses client-side validation to avoid triggering WAF
   - If still blocked, try smaller batches
   - Contact your hosting provider to whitelist the import endpoint

3. **For large imports**:
   - Files are processed in batches of 50
   - Very large files may timeout - split into smaller files

### Settings Cannot Be Saved

**Problem**: "Changes to project config are not possible in read-only mode" error.

**Solutions**:

1. **This is normal in production** - Translation Manager stores settings in the database
2. If you still see this error:
   - Check that the `translationmanager_settings` table exists
   - Run migrations: `ddev craft migrate/all`
   - Clear caches: `ddev craft clear-caches/all`

### Backup Restoration Fails

**Problem**: Cannot restore from a backup.

**Solutions**:

1. **Check permissions**:
   ```bash
   ls -la storage/translation-manager/backups/
   ```

2. **Check disk space**:
   ```bash
   df -h
   ```

3. **Try manual restoration**:
   - Download the backup
   - Check the JSON files are valid
   - Report any corruption issues

### Export Not Generating Files

**Problem**: Translation files are not being generated.

**Solutions**:

1. **Check auto-export setting**:
   - Go to **Settings → File Generation**
   - If disabled, use manual "Generate Files" button

2. **Check file permissions**:
   ```bash
   ls -la translations/
   ```

3. **Check export path**:
   - Ensure the path exists and is writable
   - Try using an absolute path instead of alias

## Debug Commands

### Check Translation Status
```bash
# Search for specific text
ddev craft translation-manager/debug/search "Your text here"

# List recent translations
ddev craft translation-manager/debug/recent

# Check total translation count
ddev craft translation-manager/translations/stats
```

### Force Capture
```bash
# Recapture all Formie forms
ddev craft translation-manager/translations/capture-formie

# Recapture with verbose output
ddev craft translation-manager/translations/capture-formie --verbose
```

### Backup Operations
```bash
# Create manual backup
ddev craft translation-manager/backup/create --reason="Debug backup"

# List all backups
ddev craft translation-manager/backup/list --detailed

# Check backup integrity
ddev craft translation-manager/backup/verify
```

## Log Files

Translation Manager logs are stored in:
```
storage/logs/translation-manager-YYYY-MM-DD.log
```

To view recent errors:
```bash
tail -f storage/logs/translation-manager-*.log | grep ERROR
```

## Performance Issues

### Slow Translation List Loading

1. **Reduce items per page**:
   - Go to **Settings → General**
   - Set "Items Per Page" to a lower number (e.g., 50)

2. **Disable usage checking**:
   - This feature checks if translations are still used
   - Can be slow with many forms

3. **Clear old translations**:
   - Use **Translation Manager → Maintenance** to clean unused translations

### Timeout During Operations

1. **For imports**: Use smaller CSV files (< 1000 rows)
2. **For exports**: Export in batches using filters
3. **For backups**: Ensure queue is running properly

## Getting Help

1. **Check the logs** first - they often contain detailed error messages
2. **Use debug tools** - especially the debug search page
3. **Enable verbose logging** temporarily:
   ```php
   // In config/translation-manager.php
   'enableDebugLogging' => true,
   ```
4. **Report issues** with:
   - Craft version
   - Plugin version
   - Error messages from logs
   - Steps to reproduce
