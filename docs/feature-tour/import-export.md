# Import/Export

Translation Manager provides comprehensive import and export functionality for managing translations at scale.

## CSV Export

Export translations with current filters applied.

### Export Contents

- English Text (translation key)
- Translation (site-specific)
- Type (Forms/Site)
- Context (if enabled)
- Status

### Using Export

1. Navigate to **Translation Manager → Import/Export**
2. Apply filters if needed (Type, Status)
3. Click **Export CSV**
4. File downloads with current filter applied

### Security

Exports are protected against CSV injection - special characters are prefixed to prevent formula execution in spreadsheet applications.

## CSV Import

Import translations with preview and validation.

### Import Process

1. Navigate to **Translation Manager → Import/Export**
2. Upload CSV file (max 5MB)
3. Preview changes (new, updated, skipped)
4. Confirm import
5. View results and history

### CSV Format

```csv
English Text,Arabic Translation,Status,Context
"Welcome to our website","مرحباً بكم في موقعنا","translated","site"
"Contact Us","اتصل بنا","translated","site"
"Submit","إرسال","translated","formie.contactForm.button.submit"
"Contact Us","اتصل بنا","translated","freeform.contactForm.title"
```

### Required Columns

| Column | Aliases | Required |
|--------|---------|----------|
| English Text | English, Source, Original | Yes |
| Translation | Arabic, Translated | No |
| Context | Category, Type | No (defaults to 'site') |
| Status | - | No |

### Import Behavior

- Updates existing translations with matching key + context
- Creates new translations for unmatched entries
- Skips empty rows
- Processes in batches (50 per batch) for large files

### Security Features

- File type validation (CSV/TXT only)
- Size limit (5MB)
- MIME type verification
- Malicious content detection (XSS, SQL injection, PHP code)
- Input sanitization
- CSRF protection
- Automatic backup before import

## PHP File Export

Generate production-ready PHP translation files.

Translation Manager writes generated PHP files into Craft's `@translations`
root. The generation path must resolve to that root exactly so Craft can load
the files at runtime; subfolders such as `@root/translations/test` or
`@translations/test` are not valid generation targets.

Generated files are still useful even when the frontend runtime source is set
to `database-with-php-fallback`. In that mode, PHP files provide fallback values
for enabled categories when no translated database row exists, while
Translation Manager database rows override matching keys.

### Generated Structure

```
translations/
├── en-US/
│   ├── lindemannrock.php  (site translations)
│   ├── formie.php         (Formie translations)
│   └── freeform.php       (Freeform translations)
└── ar/
    ├── lindemannrock.php
    ├── formie.php
    └── freeform.php
```

### Auto Generate

Enable auto-generation in settings to automatically update PHP files when translations are saved or imported (CSV, Excel, and PHP file imports all refresh the generated files).

If the configured generation path changes, Translation Manager regenerates the
current files into the new valid `@translations` location after the settings
save succeeds. It does not delete files from the previous physical location.

### Manual Generation

1. Navigate to **Translation Manager → Generate**
2. Select **All**, **Site**, a site category, or a form provider such as Formie or Freeform
3. Click Generate

Provider generation writes the provider's category file only. For example, Formie writes `formie.php` and Freeform writes `freeform.php`.

On split-runtime hosting, a deploy command can generate and verify PHP files
successfully while frontend requests still do not consume those files reliably.
Use `database-with-php-fallback` as the runtime source in that environment, and
keep generation enabled for PHP fallback files, exports, and compatibility with
Craft's standard translation folder.

### Console Commands

```bash
# Generate all translation files
php craft translation-manager/translations/generate-all

# Generate site translation files only
php craft translation-manager/translations/generate-site

# Generate one site category only
php craft translation-manager/translations/generate-category messages

# Generate one form provider's translation files only
php craft translation-manager/translations/generate-provider formie
php craft translation-manager/translations/generate-provider freeform
```

## PHP File Import

Import existing PHP translation files into Translation Manager. This is useful for:

- Importing translations from other Craft plugins
- Migrating from file-based translation management
- Onboarding existing projects with translation files

### Importing Plugin Translations

If you want to manage translations for a third-party plugin that provides frontend content:

1. **Add the category** in Settings → Translation Sources → Translation Categories
   - Add the plugin's handle (e.g., `commerce`, `events`, `pluginX`)

2. **Copy or locate the translation file**
   ```
   translations/
   └── ar/
       └── pluginX.php    ← Plugin translation file
   ```

3. **Import via PHP Import**
   - Navigate to **Translation Manager → Import/Export**
   - Scroll to "Import from PHP Files"
   - Select the file (e.g., `ar/pluginX.php`)
   - Category auto-detects from filename
   - Preview and import

### File Detection

PHP Import automatically scans your configured translations folder and detects:

- **Language** from folder name (e.g., `ar`, `en-US`, `fr`)
- **Category** from filename (e.g., `pluginX.php` → category `pluginX`)

### Import Behavior

- Creates translations for ALL configured site languages
- Source language translations are pre-filled with the key
- Target language translations use the imported values
- Existing translations are updated (not duplicated)
- Automatic backup created before import (if enabled)

Provider files such as `formie.php` and `freeform.php` import into their matching provider categories. Custom site categories import into the configured category with the same filename.

### Example Workflow

Importing a plugin's Arabic translations:

```bash
# 1. Plugin provides translations at:
plugins/my-plugin/src/translations/ar/my-plugin.php

# 2. Copy to site translations folder:
cp plugins/my-plugin/src/translations/ar/my-plugin.php translations/ar/

# 3. Add 'my-plugin' category in Translation Manager settings

# 4. Use PHP Import to import the file

# 5. Manage translations in Translation Manager

# 6. Export updates back to translations/ar/my-plugin.php
```

### Requirements

- PHP Import is only available in **devMode** (for security)
- User must have **Import Translations** permission
- Files must be in the configured generation path (default: `@translations`)

## Import History

Track all imports with:

- Date and time
- User who performed import
- Number of translations (new/updated/skipped)
- Link to pre-import backup
