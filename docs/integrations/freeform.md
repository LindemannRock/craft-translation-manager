# Freeform integration

Manage your [Freeform](https://plugins.craftcms.com/freeform) form translations from the Translation Manager Control Panel. Frontend strings — titles, messages, page labels, buttons, fields, and options — are captured when forms are saved, translated alongside the rest of your site, and served through your chosen runtime source.

## What you'll use it for

- Localizing Freeform forms for a multi-language site
- Capturing form text automatically when forms are saved (or on demand)
- Letting Translation Manager own shared frontend form translations while Freeform keeps any per-site values it manages natively

## Turn it on

Enable Freeform integration under **Translation Manager → Settings → Integrations** → **Enable Freeform Integration**. The Freeform section appears only when the Freeform plugin is installed and enabled.

See [Integrations overview](overview.md) for the shared provider lifecycle, permissions, generation, maintenance, and import/export behavior.

## How it works

Freeform strings are captured when forms are saved or deleted, and can be recaptured manually. They're stored under the `freeform` category, and Translation Manager can generate files such as `translations/ar/freeform.php`.

How they render depends on the selected **Runtime Translation Source**:

- `php-files` — Freeform values come from `translations/{language}/freeform.php`.
- `database` — Freeform values come only from translated Translation Manager rows.
- `hybrid` — database rows override PHP-file values, and PHP files fill gaps when no database row exists.

For split-runtime or edge hosting, use `hybrid` so translated rows are read from the database while committed or generated `freeform.php` files remain available as fallback.

If Freeform has its own native per-site translation for a value, **Freeform's native translation wins**. When it doesn't, Translation Manager's `freeform` category provides the frontend value through generated files or the database runtime source.

## Freeform Translatable vs Translation Manager

You don't need to enable Freeform's native **Translatable** setting for Translation Manager generated files to work. The two are separate:

- Enable the form for every site where it should render.
- Leave Freeform's native **Translatable** setting **disabled** when Translation Manager should own shared frontend form translations.
- Enable Freeform's native **Translatable** setting **only** when you intentionally want per-site values managed inside Freeform — those values override Translation Manager values on the frontend.

### Site-aware forms

Freeform forms must be enabled for the site where they're rendered. If a form renders on one site but not another, check the Freeform form's site settings first.

## Captured content

Translation Manager captures common Freeform frontend strings, including:

- Form titles and descriptions
- Success, error, and processing messages
- Page labels
- Submit, save, back, and next button labels
- Field labels, instructions, placeholders, and required messages
- HTML/content field text
- Dropdown, radio, checkbox, multi-select, and optgroup labels
- Table labels and option labels
- Add/remove labels for repeatable structures where Freeform exposes them

For manual or custom Freeform templates, keep using Craft translation filters where you render raw values yourself — especially for option labels:

```twig
{{ option.label|t('freeform') }}
```

Freeform's render helpers and Translation Manager's runtime fallback cover the common frontend output paths, but custom templates can bypass those helpers if they print raw values directly.

## Capture and generate

> This section is for re-capturing or generating files from the command line; saving a form already captures its strings.

If translations are missing after changing a form, save it again or recapture all Freeform strings:

```bash title="PHP"
php craft translation-manager/translations/capture-provider freeform
```

```bash title="DDEV"
ddev craft translation-manager/translations/capture-provider freeform
```

You can also run the Freeform scanner under **Translation Manager → Maintenance**. After capture, confirm the rows exist under category `freeform`, have the target language, and are marked `translated` before testing the frontend.

Generate only Freeform translation files:

```bash title="PHP"
php craft translation-manager/translations/generate-provider freeform
```

```bash title="DDEV"
ddev craft translation-manager/translations/generate-provider freeform
```

Generating all files also includes Freeform when the integration is enabled:

```bash title="PHP"
php craft translation-manager/translations/generate-all
```

```bash title="DDEV"
ddev craft translation-manager/translations/generate-all
```

Generating files is required for `php-files` mode. Keep it in place for `hybrid` too, because database rows protect the live frontend runtime while `freeform.php` supplies fallback values for missing database rows.

## Testing checklist

1. Confirm the Freeform form is enabled for the current site.
2. Decide whether Freeform native **Translatable** should be disabled or intentionally used for per-site values.
3. Save the Freeform form after adding or changing fields, pages, options, or buttons.
4. Confirm Translation Manager captured the expected rows in category `freeform`.
5. Translate the rows for the target language and mark them translated.
6. If using `php-files`, generate Freeform files and confirm `translations/{language}/freeform.php` exists in the same runtime that serves frontend requests.
7. If using `hybrid`, keep generation enabled, then confirm database rows override PHP-file values and PHP-file values fill gaps when a database row is absent.
8. Test fields with options, page labels, and button labels separately, because Freeform uses mixed rendering paths.

## Troubleshooting

If a Freeform string is captured but doesn't translate on the frontend:

- Confirm the Freeform form is enabled for the current site.
- Confirm the Freeform integration is enabled in Translation Manager settings.
- Regenerate Freeform files with `translation-manager/translations/generate-provider freeform`; in hybrid mode those files still provide fallback values.
- Check whether Freeform's native **Translatable** setting has a per-site value overriding the generated value.
- In custom templates, wrap raw labels/options/messages with `|t('freeform')`.
