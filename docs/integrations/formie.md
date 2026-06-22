# Formie integration

Translate your [Formie](https://verbb.io/craft-plugins/formie/features) forms without leaving Translation Manager. Field labels, options, buttons, subfields, and messages are captured automatically as you build and edit forms, then translated in the Control Panel and served to the frontend through your chosen runtime source.

## What you'll use it for

- Localizing Formie forms for a multi-language site
- Capturing form text automatically as editors change fields, options, and buttons
- Keeping form translations in the same list as the rest of your site copy

## Turn it on

Enable Formie integration under **Translation Manager → Settings → Integrations** → **Enable Formie Integration**. Once it's on, saving a form captures its translatable strings into the `formie` category.

See [Integrations overview](overview.md) for the shared provider lifecycle, permissions, generation, maintenance, and import/export behavior.

## How it works

Formie strings are captured automatically when you:

- Create or edit forms
- Add or modify fields
- Change field labels, instructions, placeholders, or error messages
- Modify button text
- Add or change dropdown, radio, or checkbox options
- Configure subfield labels (Address, Name, Date fields)
- Set up table columns, repeater buttons, or heading text

Captured strings are stored under the `formie` category. How they render on the frontend depends on the selected **Runtime Translation Source**:

- `php-files` — Formie values come from `translations/{language}/formie.php`.
- `database` — Formie values come only from translated Translation Manager rows.
- `hybrid` — database rows override PHP-file values, and PHP files fill gaps when no database row exists.

For split-runtime or edge hosting, use `hybrid` so translated rows are read from the database while committed or generated `formie.php` files remain available as fallback.

## Supported field types

### Standard fields

SingleLineText, MultiLineText, Email, Number, Phone, Password, etc. — captures label, placeholder, instructions, and error message.

### Options fields

Dropdown, Radio, Checkboxes, Categories, Entries, Products, Tags, Users — captures all option labels.

### Complex fields

- **Address** — all enabled subfield labels and placeholders (Address Line 1/2/3, City, State, ZIP, Country)
- **Name** — Prefix, First, Middle, Last
- **Date** — Day, Month, Year, Hour, Minute, Second, AM/PM labels
- **Table** — column headers, "Add Row" button text
- **Repeater** — Add/Remove button labels
- **Agree** — description text, checked/unchecked values
- **Recipients** — recipient option labels
- **Heading** — heading text content
- **Group** — all nested field translations (recursive)

## Smart deduplication

- If "First Name" appears in multiple forms or fields, it's stored only once.
- When text moves between forms, the context updates automatically.
- Translations marked "unused" are reactivated when the text is used again.

## Context format

Translation contexts follow these patterns:

| Type | Format |
|------|--------|
| Field labels | `formie.{formHandle}.{fieldHandle}.label` |
| Field options | `formie.{formHandle}.{fieldHandle}.option.{value}` |
| Subfield labels | `formie.{formHandle}.{fieldHandle}.{subfield}.label` |
| Button text | `formie.{formHandle}.button.{type}` |

## Capture and generate

> This section is for re-capturing or generating files from the command line; the day-to-day flow above needs no commands.

If translations are missing, manually capture all Formie fields:

```bash title="PHP"
php craft translation-manager/translations/capture-provider formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/capture-provider formie
```

You can also capture Formie strings from the Control Panel: open **Translation Manager → Maintenance → Capture**, pick Formie under **Provider to Capture**, and click **Capture Form Translations**. After capture, confirm the rows exist under category `formie`, have the target language, and are marked `translated` before testing the frontend.

Generate only Formie translation files:

```bash title="PHP"
php craft translation-manager/translations/generate-provider formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/generate-provider formie
```

Generating files is required for `php-files` mode. Keep it in place for `hybrid` too, because database rows protect the live frontend runtime while `formie.php` supplies fallback values for missing database rows.

## Testing checklist

1. Save the Formie form after adding or changing fields.
2. Confirm Translation Manager captured the expected rows in category `formie`.
3. Translate the rows for the target language and mark them translated.
4. If using `php-files`, generate Formie files and confirm `translations/{language}/formie.php` exists in the same runtime that serves frontend requests.
5. If using `hybrid`, keep generation enabled, then confirm database rows override PHP-file values and PHP-file values fill gaps when a database row is absent.
6. Load the frontend form in the target site language.

## Plugin name detection

Translation Manager detects Formie's configured plugin name (e.g. "Forms" instead of "Formie") and uses it throughout the interface. Configure it in `config/formie.php`:

```php
return [
    'pluginName' => 'Forms',
];
```
