# Troubleshooting

Solutions to common issues and debugging tips.

## Search Not Finding Translations

- Check the **Type** filter is set to "All Types"
- Check the **Status** filter is set to "All"
- Try searching for partial text instead of the full phrase
- Use the debug search tool: `/cms/translation-manager/maintenance/debug-search-page`
- Common issues: leading/trailing spaces, text marked as "unused", text contains Twig syntax

## Scheduled Backups Not Running

- Ensure backups are enabled and schedule is not "Manual"
- Check queue status: `php craft queue/info`
- For production, ensure queue runner is active
- The plugin automatically recovers from queue failures on each page load

## Translations Not Being Captured

**Formie**: Save the form after adding fields, or run:
```bash
php craft translation-manager/translations/capture-formie
```

**Site**: Use correct category and visit the frontend page:
```twig
{{ 'Text'|t('lindemannrock') }}
```

**Locale Variants**: If using locale-specific languages (en-US, en-GB, fr-CA, etc.):
- Translation files export to the correct locale folder (e.g., `/translations/en-US/lindemannrock.php`)
- Configure **Source Language** in Settings to match your template string language
- Clear Craft's cache after changing Source Language

**Excluded**: Text with Twig syntax (`{{`, `{%`, `{#`) is automatically excluded.

## Import Blocked by Security

- Use UTF-8 encoding with proper headers
- Avoid special characters that trigger WAF
- The plugin uses client-side validation to avoid Cloudflare blocks
- For large files, split into smaller batches

## Settings Cannot Be Saved

This is normal in production - settings are stored in database, not project config.

If error persists:
```bash
php craft migrate/all
php craft clear-caches/all
```

## Debug Commands

```bash
# Search for specific text
php craft translation-manager/debug/search "Your text"

# List recent translations
php craft translation-manager/debug/recent

# Recapture Formie forms
php craft translation-manager/translations/capture-formie
```

## Log Files

Logs are stored in `storage/logs/translation-manager-YYYY-MM-DD.log`

View recent errors:
```bash
tail -f storage/logs/translation-manager-*.log | grep ERROR
```

## Getting Help

1. Check logs first - they contain detailed error messages
2. Use debug tools, especially the debug search page
3. Enable debug logging in `config/translation-manager.php`:
   ```php
   'logLevel' => 'debug',
   ```
4. Report issues with: Craft version, plugin version, error messages, steps to reproduce
