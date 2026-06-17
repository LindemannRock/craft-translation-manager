# Template Variables

Translation Manager provides Twig variables for use in your templates.

## `craft.translationManager`

### `t(text, context)`

Translate a text string. If the translation exists for the current site's language, returns the translated value. Otherwise returns the original text.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `text` | `string` | required | The source text to translate |
| `context` | `string` | `''` | Translation context (defaults to the plugin's translation category) |

```twig
{{ craft.translationManager.t('Text to translate') }}
{{ craft.translationManager.t('Text to translate', 'my-context') }}
```

**Returns:** `string`

---

### `getStats()`

Get translation statistics

**Returns:** `array`

---

### `getAllowedSites()`

Get allowed sites for current license

**Returns:** `array`

---

### `hasTranslation(text, context)`

Check if a translation exists for the given text and context.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `text` | `string` | required | The source text to check |
| `context` | `string` | `''` | Translation context |

**Returns:** `bool`

---

### `getUnusedTranslationCounts()`

Get unused translation counts by type and category. Returns counts for enabled form providers, each site category, and combined `site` and `total` values.

**Returns:** `array`

---

### `getTranslationCounts()`

Get total translation counts (including used) by type and category. Returns counts for enabled form providers, each site category, and combined `site` and `total` values.

**Returns:** `array`

---

### `getFormProviders()`

Get registered form providers for the current installation.

```twig
{% for provider in craft.translationManager.getFormProviders() %}
    {{ provider.label }}
{% endfor %}
```

**Returns:** `array`

---

### `getEnabledFormProviders()`

Get registered form providers that are enabled in Translation Manager settings and available in Craft.

```twig
{% for provider in craft.translationManager.getEnabledFormProviders() %}
    {{ provider.handle }}: {{ provider.label }}
{% endfor %}
```

**Returns:** `array`

---

### `getFormProviderLabelForContext(context)`

Get the provider label for a translation context such as `formie.contact.label` or `freeform.contact.label`.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `context` | `string` | required | Translation context |

```twig
{{ craft.translationManager.getFormProviderLabelForContext('freeform.contact.label') }}
```

**Returns:** `string|null`

---

### `getFormiePluginName()`

Get the configured Formie plugin name. Prefer the provider helpers above for provider-generic UI.

**Returns:** `string`

---

### `getUnusedTranslationCount()`

Get count of unused translations (forms that no longer exist)

**Returns:** `int`

---

### `getBackup()`

Get the backup service

**Returns:** `\lindemannrock\translationmanager\services\BackupService`

---

### `getSettings()`

Get plugin settings

**Returns:** `\lindemannrock\translationmanager\models\Settings`

---
