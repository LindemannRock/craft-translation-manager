# Translation Manager Plugin for Craft CMS

A comprehensive translation management system for Craft CMS 5 with Formie integration, advanced filtering, and enterprise-grade security.

## Features

- **Multi-Site Translation Support**: 
  - Site-aware translation management for any language combination
  - Site selector in breadcrumbs like native Craft elements
  - Dynamic text direction (RTL/LTR) based on site language
  - Per-site translation files generation
- **Unified Translation Management**: Manage all translations in one place with an intuitive interface
- **Smart Deduplication**: Each unique text is stored only once, regardless of how many forms use it
- **Comprehensive Formie Support**:
  - Automatic capture of ALL field types including options, subfields, and special properties
  - Support for Radio, Dropdown, Checkboxes, Address, Name, Date, Table, Repeater, and more
  - Respects Formie's configured plugin name
- **Site Translations**: Custom translation category for site content with namespace protection
- **Advanced Filtering**: Filter by type (Forms/Site), status (Pending/Translated/Not Used), and search
- **Bulk Operations**: Save all changes at once, bulk delete unused translations
- **Smart Usage Detection**:
  - Automatically identifies unused translations when forms/fields are deleted
  - Reactivates translations when text is reused in new forms
  - Updates context to reflect current usage location
- **Advanced Maintenance Tools**: 
  - Template scanner to identify unused translations automatically
  - Granular cleanup options (All/Site/Forms unused translations)
  - One-click cleanup with backup protection
- **Import/Export Functionality**: 
  - CSV export with current filters and Type column
  - CSV import with preview and malicious content detection
  - PHP translation file generation for production use
  - Protection against CSV injection attacks
  - Import history tracking with backup links
- **Dedicated Logging**: All operations logged to `storage/logs/translation-manager.log`
- **Security Hardened**: XSS protection, CSRF validation, path traversal prevention, and more
- **Backup System**:
  - Manual and automatic backups (daily/weekly/monthly)
  - Auto-backup before dangerous operations
  - Organized backup folders by type (scheduled, imports, maintenance, manual)
  - Configurable retention with manual backup exemption
  - Restore functionality with pre-restore backup
  - Automatic recovery if queue is cleared
- **RTL Support**: Full support for Arabic text editing with proper RTL display
- **Keyboard Shortcuts**: Ctrl/Cmd+S to save all changes
- **Debug Tools**: Built-in search debugger for troubleshooting
- **Twig Code Filtering**: Automatically excludes text containing Twig syntax

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.0.2 or later
- MySQL 8.0.17+ or PostgreSQL 13+
- Formie plugin (optional, for form translations)

## Installation

1. Add the plugin to your project:
   ```bash
   cd /path/to/project
   composer config repositories.translation-manager path plugins/translation-manager
   composer require lindemannrock/translation-manager:@dev
   ```

2. Install the plugin:
   ```bash
   php craft plugin/install translation-manager
   ```

## Multi-Site Translation Support

Translation Manager supports multi-site setups with any language combination. The system automatically creates translations for all configured sites when new text is discovered.

### How Multi-Site Works

- **Translation Key**: The universal identifier (can be any language: English, Arabic, German, etc.)
- **Site-Specific Translations**: Each site has its own translation of the same key
- **Site Switcher**: Native Craft site selector in breadcrumbs
- **Dynamic Interface**: Text direction (RTL/LTR) adapts to site language
- **Per-Site Export**: Generates separate translation files for each site language (e.g., `en-US/alhatab.php`, `ar/formie.php`)

### Example Multi-Site Workflow

```twig
{# Template uses same translation key #}
{{ 'Welcome'|t('alhatab') }}
```

**Database Storage:**
- English Site: `translationKey="Welcome"` → `translation="Welcome"`
- Arabic Site: `translationKey="Welcome"` → `translation="مرحباً"`
- French Site: `translationKey="Welcome"` → `translation="Bienvenue"`

**Generated Files:**
- `translations/en-US/alhatab.php` (English site)
- `translations/ar/alhatab.php` (Arabic site)
- `translations/en-US/formie.php` (English Formie forms)
- `translations/ar/formie.php` (Arabic Formie forms)

### Site Switcher

The Control Panel includes a native Craft site switcher in the breadcrumbs:
- 🌍 **En** ▼ > Translation Manager > Translations
- Switch between sites to manage different language translations
- All filters and search terms are preserved when switching sites

## Configuration

### Config File (Recommended)

Create a `config/translation-manager.php` file to override default settings:

```php
<?php
return [
    'translationCategory' => 'site',
    'autoExport' => true,
    'backupEnabled' => true,
    // Multi-environment support
    'production' => [
        'autoExport' => true,
        'backupSchedule' => 'daily',
    ],
];
```

See [Configuration Documentation](docs/CONFIGURATION.md) for all available options.

### Control Panel Settings

Navigate to **Settings → Translation Manager** in the Control Panel to configure:

### General Settings
- **Translation Category**: The category used for site translations (e.g., `lindemannrock`)
  - Cannot use reserved categories: `site`, `app`, `yii`, `craft`
  - Must start with a letter and contain only letters and numbers
- **Enable Formie Integration**: Capture translations from Formie forms
- **Enable Site Translations**: Capture translations using your configured category
- **Auto Save**: Enable/disable automatic saving with configurable delay (1-10 seconds)
- **Enable Translation Suggestions**: Show translation suggestions based on similar existing translations

### File Generation Settings
- **Auto Generate**: Automatically generate translation files when saved
  - When OFF, manual "Generate Files" button appears
- **Generation Path**: Where PHP translation files are generated
  - Must use safe aliases: `@root`, `@storage`, `@config`, or `@webroot`
  - Protected against directory traversal attacks

### Import/Export Settings
- **CSV Import**: Upload and preview CSV files before importing
  - Client-side malicious content detection for enhanced security
  - Batching support for large imports (50 translations per batch)
  - Cloudflare-compatible to avoid false positive blocks
- **Import History**: Track all imports with user, date, and results
- **Export All**: Download all translations as CSV
- **Export by Type**: Export only Formie or Site translations

### Interface Settings
- **Items Per Page**: Number of translations per page (10-500)
- **Show Context**: Display translation context in interface and exports

### Site Translation Settings

When "Enable Site Translations" is enabled, the following settings become available:

- **Site Translation Category** (Required): The category to use for site translations (e.g., 'lindemannrock' for `|t('lindemannrock')`)
  - Must be used consistently in your templates
  - Avoid using 'site' as it may conflict with Craft's internal translations
  - Only captures translations using this specific category

- **Site Translations Skip Patterns**: Text patterns to skip when capturing site translations
  - One pattern per line
  - Only applies to site translations, not Formie fields
  - Useful for excluding common terms like "ID", "Title", "Status"
  - Translations are only captured from frontend requests, never from the Control Panel
  - Use "Apply Skip Patterns to Existing Translations" button to remove existing translations that match patterns

### Backup Settings
- **Enable Backups**: Turn on backup functionality
- **Backup Before Import**: Automatically backup before CSV imports
- **Backup Schedule**: Manual, Daily, Weekly, or Monthly automatic backups
- **Retention Period**: Days to keep automatic backups (manual backups never auto-delete)
- **Backup Path**: Where backups are stored in organized subfolders

Backups are automatically organized into:
- `/scheduled/` - Daily/weekly/monthly automated backups
- `/imports/` - Backups before CSV imports
- `/maintenance/` - Backups before cleanup/clear operations
- `/manual/` - User-initiated backups (never auto-deleted)

### Maintenance
- **Unused Form Translations**: Clean up translations from deleted forms
- **Danger Zone**: Clear translations by type with confirmation
  - Auto-backup before clearing (if backups enabled)
  - Automatically deletes corresponding translation files
  - Export all translations before clearing
- Apply skip patterns retroactively to existing translations

## Usage

### Site Translations

Use your configured translation category in templates:

```twig
{{ 'Welcome to our site'|t('lindemannrock') }}
{{ 'Contact Us'|t('lindemannrock') }}
{{ 'All Rights Reserved.'|t('lindemannrock') }}
```

### Managing Translations

1. Navigate to **Translation Manager → Translations** in the Control Panel
2. Use the filter dropdown to filter by:
   - **Status**: All, Pending, Translated, Unused
   - **Type**: All Types, Forms, Site (when both integrations enabled)
3. Search for specific translations (searches both English and Arabic)
4. Edit Arabic translations inline with RTL support
5. Save changes:
   - Click "Save All Changes" button
   - Or use Ctrl/Cmd+S keyboard shortcut
   - Auto-save triggers after configured delay (if enabled)
6. Import/Export translations:
   - "Import..." opens CSV import with preview
   - "Export..." exports current filtered view as CSV
   - "Generate Files" button (when Auto Generate is OFF) generates PHP files manually

### Status Indicators

- **Pending** (Orange): Translation needs Arabic text
- **Translated** (Teal): Translation has Arabic text
- **Unused** (Gray): Text no longer exists in any active form

### Maintenance

The plugin includes a maintenance section in settings to manage unused translations:
1. Navigate to **Settings → Translation Manager**
2. Scroll to the **Maintenance** section
3. Click **Clean Up** to remove all unused translations
4. Translation files are automatically regenerated after cleanup

### Formie Integration

Formie translations are captured automatically when:
- Creating or editing forms
- Adding or modifying fields
- Changing field labels, instructions, placeholders, or error messages
- Modifying button text
- Adding/changing dropdown, radio, or checkbox options
- Configuring subfield labels (Address, Name, Date fields)
- Setting up table columns, repeater buttons, or heading text

#### Supported Field Types

**Standard Fields**: SingleLineText, MultiLineText, Email, Number, Phone, Password, etc.
- Label, placeholder, instructions, error message

**Options Fields**: Dropdown, Radio, Checkboxes, Categories, Entries, Products, Tags, Users
- All option labels are captured

**Complex Fields**:
- **Address**: All enabled subfield labels and placeholders (Address 1/2/3, City, State, ZIP, Country)
- **Name**: Prefix, First Name, Middle Name, Last Name labels and placeholders
- **Date**: Day, Month, Year, Hour, Minute, Second, AM/PM labels and placeholders
- **Table**: Column headers and "Add Row" button text
- **Repeater**: Add/Remove button labels
- **Agree**: Description text, checked/unchecked values
- **Recipients**: Recipient option labels
- **Heading**: Heading text content
- **Group**: Recursively captures all nested field translations

#### Smart Deduplication

The system prevents duplicate translations:
- If "First Name" appears in multiple forms/fields, it's stored only once
- When text moves between forms, the context updates automatically
- Translations marked as "not used" are reactivated when the text is used again

Translation contexts follow the pattern:
- Field labels: `formie.{formHandle}.{fieldHandle}.label`
- Field options: `formie.{formHandle}.{fieldHandle}.option.{value}`
- Subfield labels: `formie.{formHandle}.{fieldHandle}.{subfield}.label`
- Button text: `formie.{formHandle}.button.{type}`

**Note**: The plugin automatically detects and uses Formie's configured plugin name (e.g., "Forms" instead of "Formie") throughout the interface. This is configured in Formie's settings or via `config/formie.php`.

### CSV Export

The CSV export includes:
- English Text
- Arabic Translation
- Type (Forms/Site)
- Context (if enabled in settings)
- Status

Exports respect current filters and are protected against CSV injection.

### CSV Import

The plugin provides a secure built-in CSV import feature with preview:

1. **Access Import**: Navigate to Settings → Import/Export or click "Import..." button
2. **Upload CSV**: Select your CSV file (max 5MB)
3. **Preview Changes**: Review what will be imported, updated, or skipped
4. **Confirm Import**: Import with automatic backup (if enabled)
5. **View History**: Check import history with results and backup links

**CSV Format Requirements**:
- UTF-8 encoding for proper Arabic text support
- Headers in first row
- Comma, semicolon, tab, or pipe delimiter (auto-detected)
- Double quotes for text containing delimiters

**Required Columns** (flexible naming):
- **English Text** (or English, Source, Original)
- **Arabic Translation** (or Arabic, Translation, Translated) - optional
- **Context** (or Category, Type) - optional, defaults to 'site'
- **Status** - optional: pending/translated/unused

Example CSV:
```csv
English Text,Arabic Translation,Status,Context
"Welcome to our website","مرحباً بكم في موقعنا","translated","site"
"Contact Us","اتصل بنا","translated","site"
"Submit","إرسال","translated","formie.contactForm"
```

**Security Features**:
- File type validation (CSV/TXT only)
- File size limit (5MB)
- MIME type verification
- Client-side malicious content detection (XSS, SQL injection, PHP code)
  - JavaScript-based scanning to avoid Cloudflare blocks
  - No server-side pattern matching that could trigger WAF rules
- Input sanitization (HTML stripped)
- Length validation (5000 chars max)
- Permission checks
- CSRF protection
- Automatic backup before import (configurable)
- Detailed error reporting (first 10 errors shown)

**Import Behavior**:
- Updates existing translations with matching English text and context
- Creates new translations for unmatched entries
- Skips empty rows
- Preserves 'unused' status unless explicitly changed
- Triggers automatic file export if enabled
- Shows detailed results with counts
- Processes large imports in batches (50 per batch) to prevent timeouts
- Compatible with Servd's temporary filesystem for file uploads

### PHP File Export

Translation files are exported to:
```
translations/
├── en/
│   ├── lindemannrock.php  (your configured category)
│   └── formie.php
└── ar/
    ├── lindemannrock.php
    └── formie.php
```

Files use atomic write operations for safety.

## Permissions

The plugin provides granular permissions:

- **View translations** - Access the translations interface
- **Edit translations** - Modify and save translations
- **Delete translations** - Delete unused translations
- **Export translations** - Export to CSV or PHP files
- **Manage plugin settings** - Access settings and danger zone

## Console Commands

### Translation Commands
```bash
# Capture existing Formie fields
php craft translation-manager/translations/capture-formie

# Export all translations to PHP files
php craft translation-manager/translations/export-all

# Export Formie translations only
php craft translation-manager/translations/export-formie

# Export site translations only
php craft translation-manager/translations/export-site

# Import existing Formie translation files
php craft translation-manager/translations/import-formie
```

### Maintenance Commands

```bash
# Scan templates to identify unused translations
php craft translation-manager/maintenance/scan-templates

# Preview what would be marked unused (no changes)
php craft translation-manager/maintenance/preview-scan

# Clean unused translations by type
php craft translation-manager/maintenance/clean-by-type --type=all     # All unused
php craft translation-manager/maintenance/clean-by-type --type=site    # Site only
php craft translation-manager/maintenance/clean-by-type --type=formie  # Forms only

# Clean all unused translations (legacy command)
php craft translation-manager/maintenance/clean-unused
```

### Backup Commands
```bash
# Create manual backup
php craft translation-manager/backup/create [reason]

# Run scheduled backup (for cron)
php craft translation-manager/backup/scheduled

# List all backups
php craft translation-manager/backup/list

# Clean old backups based on retention
php craft translation-manager/backup/clean
```

## Logging

The plugin logs errors and warnings to date-based log files: `storage/logs/translation-manager-YYYY-MM-DD.log`

Log entries include:
- User ID performing the action
- Critical errors and warnings only
- Failed operations and permission denials
- Export and clear operation errors

The plugin maintains up to 30 days of log files with automatic rotation.

See [docs/LOGGING.md](docs/LOGGING.md) for detailed logging documentation.

## Security

### Built-in Security Features

The Translation Manager plugin includes comprehensive security measures:

1. **XSS Protection**: All template output properly escaped
2. **CSRF Protection**: All forms validate CSRF tokens
3. **Path Traversal Protection**: Export paths restricted to safe directories
4. **CSV Injection Protection**: Special characters prefixed in exports
5. **Input Validation**:
   - Length limits (5000 chars) on translations
   - Numeric ID validation
   - Safe attribute assignment
6. **SQL Injection Protection**: Parameterized queries throughout
7. **Atomic File Operations**: Temp files with proper locking
8. **Permission-based Access Control**: Granular permissions for all operations
9. **Anonymous Access Prevention**: All actions require authentication
10. **Security Event Logging**: Comprehensive audit trail with user tracking

### Security Best Practices

#### For Administrators

1. **Permission Management**
   - Grant only necessary permissions to user groups
   - Regularly audit user permissions
   - Use separate accounts for different roles
   - Enable two-factor authentication for admin accounts

2. **Export Security**
   - Configure export paths to secure directories
   - Limit export permissions to trusted users
   - Regularly clean up old export files
   - Monitor export logs for unusual activity

3. **System Maintenance**
   - Keep Craft CMS and all plugins updated
   - Review security logs regularly
   - Monitor for failed permission attempts
   - Backup translation data regularly

#### For Developers

1. **Template Usage**
   - Always use the proper translation filter syntax
   - Never output translation data without escaping
   - Avoid inline JavaScript with translation data
   - Use data attributes for dynamic content

2. **Custom Integrations**
   - Validate all input when using the plugin's services
   - Use Craft's permission system for access control
   - Log security-relevant operations
   - Follow Craft's security best practices

### Reporting Security Issues

For security vulnerabilities, please see our [Security Policy](SECURITY.md).

**DO NOT** create public GitHub issues for security vulnerabilities.

## Troubleshooting

### Search Not Finding Translations
- Check the **Type** filter is set to "All Types" (not just Forms or Site)
- Check the **Status** filter is set to "All"
- Try searching for partial text instead of the full phrase
- Use the debug search tool: `/cms/translation-manager/maintenance/debug-search-page`
- Common issues: leading/trailing spaces, text marked as "unused", text contains Twig syntax

### Scheduled Backups Not Running
- Ensure backups are enabled and schedule is not "Manual"
- Check queue status: `craft queue/info`
- For production, ensure queue runner is active
- The plugin automatically recovers from queue failures on each page load

### Translations Not Being Captured
- **Formie**: Save the form after adding fields, or run `craft translation-manager/translations/capture-formie`
- **Site**: Use correct category `{{ 'Text'|t('alhatab') }}` and visit the frontend page
- Text with Twig syntax ({{, {%, {#) is automatically excluded

### Import Blocked by Security
- Use UTF-8 encoding with proper headers
- Avoid special characters that trigger WAF
- The plugin uses client-side validation to avoid Cloudflare blocks
- For large files, split into smaller batches

### Settings Cannot Be Saved
- This is normal in production - settings are stored in database, not project config
- If error persists, run migrations: `craft migrate/all`

For detailed troubleshooting, see [Troubleshooting Guide](docs/TROUBLESHOOTING.md)

## Changelog

### 1.3.2 - 2025-01-16
- **Backup System Improvements**:
  - Organized backups into subfolders by type (scheduled, imports, maintenance, manual)
  - Automatic recovery for scheduled backups if queue is cleared
  - Scheduled backups now initialize on every plugin load
- **Search Functionality**:
  - Fixed search form not including search parameter in URL
  - Added debug search tool for troubleshooting
- **Translation Filtering**:
  - Automatically excludes text containing Twig syntax ({{, {%, {#})
- **Bug Fixes**:
  - Fixed cleanup maintenance action not returning JSON for AJAX requests
  - Fixed duplicate backup jobs being created

See [Full Changelog](CHANGELOG.md) for complete version history

## Support

For issues and feature requests, please contact the LindemannRock development team.

## License

Proprietary - LindemannRock for Al Hatab Foods
