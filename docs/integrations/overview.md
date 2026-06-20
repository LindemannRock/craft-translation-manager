# Integrations Overview

Translation Manager includes built-in form integrations for Formie and Freeform. These integrations capture form text into Translation Manager, let editors translate the strings in the Control Panel, and make those strings available to Craft through generated PHP files, database runtime sources, or a hybrid of both.

## Built-In Providers

| Provider | Category | Generated file | Notes |
|----------|----------|----------------|-------|
| Formie | `formie` | `translations/{language}/formie.php` | Captures Formie form, page, field, option, subfield, and button text. |
| Freeform | `freeform` | `translations/{language}/freeform.php` | Captures Freeform form, page, field, option, content, behavior, and button text. |

Provider categories are separate from site translation categories such as `messages` or `site`. Site categories are configured under **Translation Sources**. Provider categories are controlled by their integration toggles under **Settings → Integrations**.

## Provider Lifecycle

1. Enable the provider integration under **Translation Manager → Settings → Integrations**.
2. Save a form in the provider plugin, or run a manual capture command.
3. Translate captured provider rows in **Translation Manager**.
4. Choose the runtime source in **Settings → Generation**:
   `generated-files`, `database`, or `database-with-php-fallback`.
5. Generate provider translation files when using `generated-files`, or when
   using `database-with-php-fallback` and you want PHP files available as
   fallback values.
6. Re-run capture after changing form fields, options, pages, or button labels.

Manual capture:

```bash
php craft translation-manager/translations/capture-provider formie
php craft translation-manager/translations/capture-provider freeform
```

Manual generation:

```bash
php craft translation-manager/translations/generate-provider formie
php craft translation-manager/translations/generate-provider freeform
```

## Control Panel Actions

Provider integrations appear in several Translation Manager areas:

- **Settings → Integrations**: Enable or disable each available provider.
- **Generate**: Generate all files, site files, site category files, or one provider's files.
- **Maintenance**: Recapture provider strings and clean unused provider rows.
- **Danger zone**: Clear provider rows when a provider's translation data must be reset.
- **Import/Export**: Import or export provider rows through CSV/XLSX/PHP workflows.

Provider actions only appear when the provider plugin is installed, enabled in Craft, and enabled in Translation Manager settings.

## Native Plugin Translations

Formie and Freeform also have their own plugin translation categories. Translation Manager preserves those provider-owned static categories so provider Control Panel text can still come from the provider plugin.

Translation Manager generated files and database rows are intended for the frontend form strings it captures. In `database-with-php-fallback` mode, Translation Manager loads provider PHP files first and then overlays translated database rows, so translated database rows win and PHP files fill gaps.

For Freeform, native per-site form translations remain authoritative for values managed directly by Freeform. When Freeform does not provide a native per-site value, Translation Manager's `freeform` category can provide the frontend value through generated files or the database runtime source.

## Permission Handles

Provider permissions use the stable provider handle, even if the provider's display name is customized in its own settings.

| Provider | Generate | Recapture | Clear |
|----------|----------|-----------|-------|
| Formie | `translationManager:generateProvider:formie` | `translationManager:recaptureProvider:formie` | `translationManager:clearProvider:formie` |
| Freeform | `translationManager:generateProvider:freeform` | `translationManager:recaptureProvider:freeform` | `translationManager:clearProvider:freeform` |

For example, if Formie is renamed to "Forms" in Formie settings, Translation Manager may show that label in the interface, but the permission handle remains `formie`.
