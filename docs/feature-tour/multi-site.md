# Multi-site translations

Run one Craft install across several sites and languages, and translate each string once per **language** — not once per site. Translation Manager keys every translation by language, so sites that share a language share the same translation: the key `Welcome` is *Welcome* in English, *مرحباً* in Arabic, and *Bienvenue* in French, no matter how many sites use each language, and without touching a file.

## What you'll use it for

- Running one Craft install that serves several languages from separate sites
- Translating the same template into every language from a single screen
- Supporting regional variants (en-US, en-GB) without maintaining a separate set of files for each
- Letting translators work language-by-language while keys stay shared across every site

## Translate across languages in the Control Panel

1. Go to **Translation Manager → Translations**.
2. Use the **language switcher** in the breadcrumb (**Select language**) to pick the language you want to work on.
3. Translate the strings for that language, then switch to the next — your filters and search term carry over, and the editor flips to RTL automatically for right-to-left languages.

![Language switcher in the Translation Manager breadcrumb](images/multi-site-language-switcher.webp)

The switcher lists the managed languages in use across your sites, with regional variants consolidated to their base language (so a mapped `en-US` shows under `en`). It is a language selector, not a Craft site-permission selector; Translation Manager permissions decide which sources a user can edit or approve inside that language.

## How it works

- **Translation key** — the universal identifier for a string. It can be in any language (English, Arabic, German…); it's what your `|t()` call passes.
- **Per-language translations** — each language stores its own translation of the key (the database is unique per key + language + category). Sites that share a language share the same translation row.
- **Per-language files** — generation writes a set of translation files per language; in hybrid runtime mode those files remain the fallback behind translated database rows.
- **Direction** — the editor's text direction (RTL/LTR) follows the language.

### Locale variant support

Regional variants are fully supported as languages:

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

Translation Manager stores one translation per language:

| Language | Translation key | Translation |
|----------|-----------------|-------------|
| English | Welcome | Welcome |
| Arabic | Welcome | مرحباً |
| French | Welcome | Bienvenue |

And generates a file set per language:

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

The source language should match the language of your `|t()` keys, which is not necessarily your primary site's language. See [Source Language](../get-started/configuration.md#source-language).
