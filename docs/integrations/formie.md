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

Enable Formie integration in **Settings → Translation Manager**:

- **Enable Formie Integration**: Toggle to capture form translations

## Manual Capture

If translations are missing, manually capture all Formie fields:

```bash
php craft translation-manager/translations/capture-formie
```

Or use **Translation Manager → Maintenance → Recapture Formie Translations**.

## Plugin Name Detection

The plugin automatically detects Formie's configured plugin name (e.g., "Forms" instead of "Formie") and uses it throughout the interface.

Configure in `config/formie.php`:

```php
return [
    'pluginName' => 'Forms',
];
```
