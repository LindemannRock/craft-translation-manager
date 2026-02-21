# Basic Usage

Use Translation Manager to manage translations in your Twig templates.

## Site Translations

Use your configured translation category in templates:

```twig
{{ 'Welcome to our site'|t('lindemannrock') }}
{{ 'Contact Us'|t('lindemannrock') }}
{{ 'All Rights Reserved.'|t('lindemannrock') }}
```

The category (e.g., `lindemannrock`) is configured in **Settings → Translation Manager → Site Translation Category**.

## How It Works

1. **Capture**: When a page is visited, text using your category is captured
2. **Store**: Translations are stored in the database per site
3. **Translate**: Edit translations in the Control Panel
4. **Generate**: PHP translation files are generated for production

## Template Examples

### Simple Text

```twig
<h1>{{ 'Welcome'|t('lindemannrock') }}</h1>
<p>{{ 'Thank you for visiting our website.'|t('lindemannrock') }}</p>
```

### With Variables

```twig
{{ 'Hello, {name}!'|t('lindemannrock', { name: user.name }) }}
{{ '{count} items in cart'|t('lindemannrock', { count: cart.count }) }}
```

### Pluralization

```twig
{{ '{count, plural, =0{No items} =1{One item} other{# items}}'|t('lindemannrock', { count: items|length }) }}
```

## Status Indicators

In the Control Panel, translations show status:

| Status | Color | Meaning |
|--------|-------|---------|
| Pending | Orange | Needs translation |
| Translated | Teal | Has translation |
| Unused | Gray | No longer used in templates |

## Best Practices

1. **Consistent Category**: Always use the same translation category
2. **Meaningful Keys**: Use readable English text as keys
3. **Avoid Twig in Keys**: Text with `{{`, `{%`, `{#` is automatically excluded
4. **Frontend Only**: Translations are only captured from frontend requests
