# Integrations overview

Translate your Formie and Freeform forms from the same Control Panel you use for the rest of your site. Translation Manager captures form text into its own list, lets editors translate it, and feeds the results back to Craft through generated PHP files, a database runtime source, or a hybrid of both — no separate per-form translation workflow.

## What you'll use it for

- Localizing form fields, options, buttons, and messages for a multi-language site
- Keeping form translations next to your site translations instead of in a separate tool
- Re-capturing automatically as editors change forms, with manual capture when you need it

## Built-in providers

| Provider | Category | Generated file | Captures |
|----------|----------|----------------|----------|
| Formie | `formie` | `translations/{language}/formie.php` | Form, page, field, option, subfield, and button text ([details](formie.md)) |
| Freeform | `freeform` | `translations/{language}/freeform.php` | Form, page, field, option, content, behavior, and button text ([details](freeform.md)) |

Provider categories are separate from site translation categories such as `messages` or `site`. Site categories are configured under **Translation Sources**; provider categories are controlled by their integration toggles under **Settings → Integrations**.

![Integration toggles under Translation Manager settings](images/integrations-settings.webp)

## Provider lifecycle

1. Enable the provider integration under **Translation Manager → Settings → Integrations**.
2. Save a form in the provider plugin, or run a manual capture command.
3. Translate the captured provider rows in **Translation Manager**.
4. Choose the runtime source in **Settings → Generation**: use `php-files` for standard hosting, `hybrid` for edge/split-runtime hosting, or `database` for DB-only diagnostics (see [Runtime translation source](../get-started/configuration.md#runtime-translation-source)).
5. Keep provider generation in your workflow for `php-files` and `hybrid`. In hybrid mode, database rows protect the live frontend runtime and PHP files remain the fallback.
6. Re-run capture after changing form fields, options, pages, or button labels.

Manually capture a provider's strings:

```bash title="PHP"
php craft translation-manager/translations/capture-provider formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/capture-provider formie
```

Manually generate a provider's files:

```bash title="PHP"
php craft translation-manager/translations/generate-provider formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/generate-provider formie
```

## Where providers appear in the Control Panel

- **Settings → Integrations** — enable or disable each available provider.
- **Generate** — generate all files, site files, a site category, or one provider's files.
- **Maintenance** — capture provider strings and clean unused provider rows.
- **Danger** — delete a provider's rows when its translation data must be reset.
- **Import/Export** — import or export provider rows through the CSV/XLSX/PHP workflows.

Provider actions appear only when the provider plugin is installed, enabled in Craft, and enabled in Translation Manager settings.

## Native plugin translations

Formie and Freeform also ship their own plugin translation categories. Translation Manager preserves those provider-owned static categories, so provider Control Panel text can still come from the provider plugin.

Translation Manager's generated files and database rows are for the frontend form strings it captures. In `php-files` mode, frontend output comes from the provider PHP files. In `hybrid` mode, Translation Manager loads provider PHP files first and then overlays translated database rows — so translated database rows win and PHP files fill gaps.

For Freeform, native per-site form translations remain authoritative for values Freeform manages directly. When Freeform has no native per-site value, Translation Manager's `freeform` category can supply the frontend value through generated files or the database runtime source.

## Permission handles

Provider actions are gated by source-based permissions keyed on the provider's category (its source id). The source id is stable even when the provider's display name is customized in its own settings.

| Provider | Capture | Generate | Delete |
|----------|---------|----------|--------|
| Formie | `translationManager:captureTranslations:formie` | `translationManager:generateSource:formie` | `translationManager:deleteSourceTranslations:formie` |
| Freeform | `translationManager:captureTranslations:freeform` | `translationManager:generateSource:freeform` | `translationManager:deleteSourceTranslations:freeform` |

For example, if Formie is renamed to "Forms" in Formie's settings, Translation Manager may show that label in the interface, but the permission handle stays `formie`. See [Permissions](../developers/permissions.md) for the full source-permission tree.
