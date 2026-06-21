# Multi-site translations

Translate one string once per site, and let Translation Manager keep every language in sync. When new text is discovered, the plugin creates a translation row for each of your configured sites — so the same key (`Welcome`) can be *Welcome* on your English site, *مرحباً* on your Arabic site, and *Bienvenue* on your French site, without touching a file.

## What you'll use it for

- Running one Craft install that serves several languages from separate sites
- Translating the same template into every site language from a single screen
- Supporting regional variants (en-US, en-GB) without maintaining a separate set of files for each
- Letting translators work site-by-site while keys stay shared across all of them

## Translate across sites in the Control Panel

1. Go to **Translation Manager → Translations**.
2. Use the native Craft **site switcher** in the breadcrumb to pick the site (language) you want to work on.
3. Translate the strings for that site, then switch to the next site — your filters and search term carry over, and the editor flips to RTL automatically for right-to-left languages.

![Site switcher in the Translation Manager breadcrumb](images/multi-site-site-switcher.webp)

The site switcher is standard Craft, so it only lists the sites the current user is allowed to edit.

## How it works

- **Translation key** — the universal identifier for a string. It can be in any language (English, Arabic, German…); it's what your `|t()` call passes.
- **Site-specific translations** — each site stores its own translation of that key.
- **Per-site files** — generation writes a separate set of translation files per site language; in hybrid runtime mode those files remain the fallback behind translated database rows.
- **Direction** — the editor's text direction (RTL/LTR) follows the site's language.

### Locale variant support

Regional variants are fully supported as site languages:

- `en-US`, `en-GB`, `en-CA` (English variants)
- `fr-FR`, `fr-CA` (French variants)
- Any other locale combination

To avoid translating the same string separately for `en-US` and `en-GB`, map the variants onto a base locale — see [Locale mapping](../get-started/configuration.md#locale-mapping).

### Example

A template uses one shared key:

```twig
{# Same key on every site #}
{{ 'Welcome'|t('messages') }}
```

Translation Manager stores one translation per site:

| Site | Translation key | Translation |
|------|-----------------|-------------|
| English | Welcome | Welcome |
| Arabic | Welcome | مرحباً |
| French | Welcome | Bienvenue |

And generates a file set per site language:

```text
translations/
├── en-US/
│   ├── messages.php
│   ├── formie.php
│   └── freeform.php
├── ar/
│   ├── messages.php
│   ├── formie.php
│   └── freeform.php
└── fr/
    ├── messages.php
    ├── formie.php
    └── freeform.php
```

## Configuration

Set the source language — the language your `|t()` keys are written in — in your config file:

```php
// config/translation-manager.php
return [
    'sourceLanguage' => 'en', // Language your template strings are written in
];
```

The source language should match the language of your `|t()` keys, which is not necessarily your primary site's language.
