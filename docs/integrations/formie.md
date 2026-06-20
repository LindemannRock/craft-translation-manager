# Formie Integration

Translation Manager provides comprehensive integration with [Formie](https://verbb.io/craft-plugins/formie/features) for form translations.

## How It Works

Formie translations are captured automatically when:

- Creating or editing forms
- Adding or modifying fields
- Changing field labels, instructions, placeholders, or error messages
- Modifying button text
- Adding/changing dropdown, radio, or checkbox options
- Configuring subfield labels (Address, Name, Date fields)
- Setting up table columns, repeater buttons, or heading text

Captured Formie strings are stored in Translation Manager under the `formie`
category. Runtime rendering depends on the selected **Runtime Translation
Source**:

- `generated-files`: Formie values come from `translations/{language}/formie.php`.
- `database`: Formie values come only from translated Translation Manager rows.
- `database-with-php-fallback`: Translation Manager database rows override
  Formie/PHP file values, and PHP files fill gaps when no database row exists.

For split-runtime or edge hosting, use `database-with-php-fallback` so translated
rows are read from the database while committed/generated `formie.php` files
remain available as fallback.

## Supported Field Types

### Standard Fields

SingleLineText, MultiLineText, Email, Number, Phone, Password, etc.

Captured properties:
- Label
- Placeholder
- Instructions
- Error message

### Options Fields

Dropdown, Radio, Checkboxes, Categories, Entries, Products, Tags, Users

Captured properties:
- All option labels

### Complex Fields

#### Address

All enabled subfield labels and placeholders:
- Address Line 1/2/3
- City, State, ZIP
- Country

#### Name

- Prefix
- First Name
- Middle Name
- Last Name

#### Date

- Day, Month, Year
- Hour, Minute, Second
- AM/PM labels

#### Table

- Column headers
- "Add Row" button text

#### Repeater

- Add/Remove button labels

#### Other

- **Agree**: Description text, checked/unchecked values
- **Recipients**: Recipient option labels
- **Heading**: Heading text content
- **Group**: All nested field translations (recursive)

## Smart Deduplication

The system prevents duplicate translations:

- If "First Name" appears in multiple forms/fields, it's stored only once
- When text moves between forms, the context updates automatically
- Translations marked as "unused" are reactivated when text is used again

## Context Format

Translation contexts follow the pattern:

| Type | Format |
|------|--------|
| Field labels | `formie.{formHandle}.{fieldHandle}.label` |
| Field options | `formie.{formHandle}.{fieldHandle}.option.{value}` |
| Subfield labels | `formie.{formHandle}.{fieldHandle}.{subfield}.label` |
| Button text | `formie.{formHandle}.button.{type}` |

## Configuration

Enable Formie integration in **Translation Manager → Settings → Integrations**:

- **Enable Formie Integration**: Toggle to capture form translations

See [Integrations Overview](overview.md) for the shared provider lifecycle, permissions, generation, maintenance, and import/export behavior.

## Manual Capture

If translations are missing, manually capture all Formie fields:

```bash
php craft translation-manager/translations/capture-provider formie
```

Or use **Translation Manager → Maintenance** and run the Formie scanner.

After capture, confirm the rows exist under category `formie`, have the target
language, and are marked `translated` before testing the frontend.

## Generate Files

Generate only Formie translation files:

```bash
php craft translation-manager/translations/generate-provider formie
```

With DDEV:

```bash
ddev craft translation-manager/translations/generate-provider formie
```

Generating files is required for `generated-files` runtime mode and remains
useful in `database-with-php-fallback` mode because `formie.php` supplies
fallback values for missing database rows.

## Testing Checklist

1. Save the Formie form after adding or changing fields.
2. Confirm Translation Manager captured the expected rows in category `formie`.
3. Translate the rows for the target language and mark them translated.
4. If using `generated-files`, generate Formie files and confirm
   `translations/{language}/formie.php` exists.
5. If using `database-with-php-fallback`, confirm database rows override PHP file
   values and PHP file values fill gaps when a DB row is absent.
6. Load the frontend form in the target site language.

## Plugin Name Detection

The plugin automatically detects Formie's configured plugin name (e.g., "Forms" instead of "Formie") and uses it throughout the interface.

Configure in `config/formie.php`:

```php
return [
    'pluginName' => 'Forms',
];
```
