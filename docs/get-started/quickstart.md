# Quickstart

Capture your first translations and edit them in the Control Panel — no code beyond the `|t()` calls already in your templates. By the end of this guide you'll have runtime capture enabled, strings translated for a second site, and files ready to generate.

## 1. Install the plugin

See [Installation](installation.md) for the full Composer and DDEV options.

## 2. Enable site translations and auto-capture

Go to **Translation Manager → Settings → Translation Sources** and confirm **Enable Site Translations** is on. This captures the `|t()` calls in your templates as translatable strings.

While you're here, check **Source Language** matches the language your `|t()` keys are written in (English by default). That language is treated as already translated, so you only translate *into* your other sites — set it correctly now, before you capture anything. See [Source Language](configuration.md#source-language).

Then go to **Translation Manager → Settings → Auto-Capture** and enable **Capture Missing Translations**. If **Only in devMode** is enabled, do this on a devMode environment.

## 3. Visit a frontend page

Open any page that uses `|t('messages')` in its templates. With auto-capture enabled, Translation Manager captures those strings as they render.

## 4. Translate a string

1. Go to **Translation Manager** in the Control Panel.
2. You'll see the captured strings with a **Pending** status.
3. Switch to a secondary site using the site selector.
4. Enter translations for the pending strings.
5. Click **Save All Changes** (or press Ctrl/Cmd + S).

## 5. Capture form translations

If you use Formie or Freeform, enable the integration under **Translation Manager → Settings → Integrations**, then save a form — or run a provider capture command:

```bash title="PHP"
php craft translation-manager/translations/capture-provider formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/capture-provider formie
```

Generate files after translating the provider strings:

```bash title="PHP"
php craft translation-manager/translations/generate-provider formie
```

```bash title="DDEV"
ddev craft translation-manager/translations/generate-provider formie
```

## What's next

- [Configuration](configuration.md) — customize translation categories, auto-generation, and locale mapping
- [Feature tour](../feature-tour/overview.md) — explore multi-site support, import/export, and backups
