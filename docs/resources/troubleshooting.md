# Troubleshooting

Solutions to common issues and debugging tips.

## Search Not Finding Translations

- Check the **Type** filter is set to "All Types"
- Check the **Status** filter is set to "All"
- Try searching for partial text instead of the full phrase
- Common issues: leading/trailing spaces, text marked as "unused", text contains Twig syntax

## Scheduled Backups Not Running

- Ensure backups are enabled and schedule is not "Manual"
- Check queue status: `php craft queue/info`
- For production, ensure queue runner is active
- The plugin automatically recovers from queue failures on each page load
- If a deployment or multiple web processes create duplicate pending backup rows, Translation Manager collapses the duplicate pending rows during bootstrap and keeps one row for the next scheduled run

## Translations Not Being Captured

**Form providers**: Save the form after adding fields, or run:
```bash
php craft translation-manager/translations/capture-provider formie
php craft translation-manager/translations/capture-provider freeform
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

Numeric settings such as backup retention and items per page must be whole numbers within the allowed range. If a value is invalid, Translation Manager keeps you on the same settings page and shows the field error inline.

When a setting is overridden in `config/translation-manager.php`, the Control Panel field is skipped during save. Change the config file value instead.

If error persists:
```bash
php craft migrate/all
php craft clear-caches/all
```

## Useful Commands

```bash
# Recapture form provider translations
php craft translation-manager/translations/capture-provider formie
php craft translation-manager/translations/capture-provider freeform
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
