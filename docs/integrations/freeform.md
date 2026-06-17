# Freeform Integration

Translation Manager provides built-in integration with [Freeform](https://plugins.craftcms.com/freeform) for managing frontend form translations from the Translation Manager Control Panel.

## How It Works

Freeform translations are captured when forms are saved or deleted, and can also be recaptured manually. Translation Manager stores captured strings under the `freeform` provider category and can generate Craft translation files such as `translations/ar/freeform.php`.

Generated Freeform files are used as a fallback for frontend rendering. If Freeform has its own native per-site translation for a value, Freeform's native translation wins. If Freeform does not have a native translation, Translation Manager's generated `freeform.php` value can be used.

You do not need to enable Freeform's **Translatable** setting for Translation Manager generated files to work. If you do enable Freeform's native per-site translations, manage those values carefully because they override Translation Manager generated values for matching form content.

## Site-Aware Forms

Freeform forms must be enabled for the site where they are rendered. If a form renders on one site but not another, check the Freeform form's site settings first.

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

## Troubleshooting

If a Freeform string is captured but does not translate on the frontend:

- Confirm the Freeform form is enabled for the current site.
- Confirm the Freeform integration is enabled in Translation Manager settings.
- Regenerate Freeform files with `translation-manager/translations/generate-provider freeform`.
- Check whether Freeform's native **Translatable** setting has a per-site value that overrides the generated value.
- In custom templates, wrap raw labels/options/messages with `|t('freeform')`.
