# Freeform Integration

Translation Manager provides built-in integration with [Freeform](https://plugins.craftcms.com/freeform) for managing frontend form translations from the Translation Manager Control Panel.

## How It Works

Freeform translations are captured when forms are saved or deleted, and can also be recaptured manually. Translation Manager stores captured strings under the `freeform` provider category and can generate Craft translation files such as `translations/ar/freeform.php`.

Runtime rendering depends on the selected **Runtime Translation Source**:

- `generated-files`: Freeform values come from `translations/{language}/freeform.php`.
- `database`: Freeform values come only from translated Translation Manager rows.
- `database-with-php-fallback`: Translation Manager database rows override
  Freeform/PHP file values, and PHP files fill gaps when no database row exists.

For split-runtime or edge hosting, use `database-with-php-fallback` so translated
rows are read from the database while committed/generated `freeform.php` files
remain available as fallback.

If Freeform has its own native per-site translation for a value, Freeform's
native translation wins. If Freeform does not have a native translation,
Translation Manager's `freeform` category can provide the frontend value through
generated files or the database runtime source.

You do not need to enable Freeform's **Translatable** setting for Translation Manager generated files to work. If you do enable Freeform's native per-site translations, manage those values carefully because they override Translation Manager generated values for matching form content.

## Site-Aware Forms

Freeform forms must be enabled for the site where they are rendered. If a form renders on one site but not another, check the Freeform form's site settings first.

Freeform's site-aware and native **Translatable** settings are separate from
Translation Manager:

- Enable the form for every site where it should render.
- Leave Freeform's native **Translatable** setting disabled when Translation
  Manager should own shared frontend form translations.
- Enable Freeform's native **Translatable** setting only when you intentionally
  want per-site values managed inside Freeform. Those values can override
  Translation Manager values on the frontend.

## Captured Content

Translation Manager captures common Freeform frontend strings, including:

- Form titles and descriptions
- Success, error, and processing messages
- Page labels
- Submit, save, back, and next button labels
- Field labels, instructions, placeholders, and required messages
- HTML/content field text
- Dropdown, radio, checkbox, multi-select, and optgroup labels
- Table labels and option labels
- Add/remove labels for repeatable structures where exposed by Freeform

For manual or custom Freeform templates, keep using Craft translation filters where you render raw values yourself, especially for option labels:

```twig
{{ option.label|t('freeform') }}
```

Freeform's render helpers and Translation Manager's runtime fallback cover the common frontend output paths, but custom templates can bypass those helpers if they print raw values directly.

## Configuration

Enable Freeform integration in **Translation Manager → Settings → Integrations**:

- **Enable Freeform Integration**: Toggle to capture and generate Freeform translations

The Freeform section is only available when the Freeform plugin is installed and enabled.

See [Integrations Overview](overview.md) for the shared provider lifecycle, permissions, generation, maintenance, and import/export behavior.

## Manual Capture

If translations are missing after changing a form, save the form again or manually recapture all Freeform strings:

```bash
php craft translation-manager/translations/capture-provider freeform
```

With DDEV:

```bash
ddev craft translation-manager/translations/capture-provider freeform
```

You can also use **Translation Manager → Maintenance** and run the Freeform scanner.

After capture, confirm the rows exist under category `freeform`, have the target
language, and are marked `translated` before testing the frontend.

## Generate Files

Generate only Freeform translation files:

```bash
php craft translation-manager/translations/generate-provider freeform
```

With DDEV:

```bash
ddev craft translation-manager/translations/generate-provider freeform
```

Generating all files also includes Freeform when the integration is enabled:

```bash
php craft translation-manager/translations/generate-all
```

Generating files is required for `generated-files` runtime mode and remains
useful in `database-with-php-fallback` mode because `freeform.php` supplies
fallback values for missing database rows.

## Testing Checklist

1. Confirm the Freeform form is enabled for the current site.
2. Decide whether Freeform native **Translatable** should be disabled or
   intentionally used for per-site values.
3. Save the Freeform form after adding or changing fields, pages, options, or
   buttons.
4. Confirm Translation Manager captured the expected rows in category
   `freeform`.
5. Translate the rows for the target language and mark them translated.
6. If using `generated-files`, generate Freeform files and confirm
   `translations/{language}/freeform.php` exists.
7. If using `database-with-php-fallback`, confirm database rows override PHP file
   values and PHP file values fill gaps when a DB row is absent.
8. Test fields with options, page labels, and button labels separately because
   Freeform uses mixed rendering paths.

## Troubleshooting

If a Freeform string is captured but does not translate on the frontend:

- Confirm the Freeform form is enabled for the current site.
- Confirm the Freeform integration is enabled in Translation Manager settings.
- Regenerate Freeform files with `translation-manager/translations/generate-provider freeform`.
- Check whether Freeform's native **Translatable** setting has a per-site value that overrides the generated value.
- In custom templates, wrap raw labels/options/messages with `|t('freeform')`.
