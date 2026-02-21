# Multi-Site Translation Support

Translation Manager supports multi-site setups with any language combination. The system automatically creates translations for all configured sites when new text is discovered.

## How Multi-Site Works

- **Translation Key**: The universal identifier (can be any language: English, Arabic, German, etc.)
- **Site-Specific Translations**: Each site has its own translation of the same key
- **Site Switcher**: Native Craft site selector in breadcrumbs
- **Dynamic Interface**: Text direction (RTL/LTR) adapts to site language
- **Per-Site Export**: Generates separate translation files for each site language

## Locale Variant Support

Full support for regional language variants:

- `en-US`, `en-GB`, `en-CA` (English variants)
- `fr-FR`, `fr-CA` (French variants)
- Any other locale combination

## Example Workflow

```twig
{# Template uses same translation key #}
{{ 'Welcome'|t('lindemannrock') }}
```

**Database Storage:**

| Site | Translation Key | Translation |
|------|-----------------|-------------|
| English | Welcome | Welcome |
| Arabic | Welcome | مرحباً |
| French | Welcome | Bienvenue |

**Generated Files:**

```
translations/
├── en-US/
│   ├── lindemannrock.php
│   └── formie.php
├── ar/
│   ├── lindemannrock.php
│   └── formie.php
└── fr/
    ├── lindemannrock.php
    └── formie.php
```

## Site Switcher

The Control Panel includes a native Craft site switcher in the breadcrumbs:

- Switch between sites to manage different language translations
- All filters and search terms are preserved when switching sites
- Dynamic text direction (RTL/LTR) based on site language

## Configuration

Configure the source language in Settings:

```php
// config/translation-manager.php
return [
    'sourceLanguage' => 'en', // Language your template strings are written in
];
```

The source language should match the language of your `|t()` keys, not necessarily your primary site.
