# Managing Translations

Learn how to manage translations in the Control Panel.

## Translations Interface

Navigate to **Translation Manager → Translations** to manage all translations.

### Filtering

Use the filter dropdown to filter by:

- **Status**: All, Pending, Translated, Unused
- **Type**: All Types, Forms, Site

### Searching

Search for translations by:

- English text (translation key)
- Translated text
- Context

### Editing

1. Find the translation to edit
2. Enter the translated text in the input field
3. RTL languages display with proper text direction
4. Save changes

### Saving

Multiple ways to save:

- Click **Save All Changes** button
- Use **Ctrl/Cmd+S** keyboard shortcut
- Enable **Auto Save** in settings (configurable delay)

## Bulk Operations

### Delete Unused

Remove translations no longer used in templates:

1. Filter by **Status → Unused**
2. Select translations to delete
3. Click **Delete Selected**

### Clean All Unused

Navigate to **Translation Manager → Maintenance** for bulk cleanup options.

## Maintenance

### Template Scanner

Identify unused translations:

1. Go to **Translation Manager → Maintenance**
2. Click **Rescan Templates**
3. Templates are scanned for translation usage
4. Unused translations are marked

### Cleanup Options

| Option | Description |
|--------|-------------|
| Clean All Unused | Remove all unused translations |
| Clean Site Unused | Remove unused site translations |
| Clean Forms Unused | Remove unused form translations |

### Danger Zone

Clear all translations (with backup):

1. Go to **Translation Manager → Maintenance**
2. Scroll to Danger Zone
3. Select type to clear
4. Confirm action

A backup is automatically created before clearing (if backups enabled).

## Tips

1. **Regular Cleanup**: Periodically clean unused translations
2. **Use Filters**: Narrow down translations with filters
3. **Check Status**: Review pending translations regularly
4. **Backup First**: Always backup before major changes
