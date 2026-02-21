# Twig Globals

Translation Manager provides the following global variables in your Twig templates.

## `translationHelper`

*Provided by `lindemannrock/base`*

| Property | Description |
|----------|-------------|
| `translationHelper.displayName` | Display name (singular, without "Manager") |
| `translationHelper.pluralDisplayName` | Plural display name (without "Manager") |
| `translationHelper.fullName` | Full plugin name (as configured) |
| `translationHelper.lowerDisplayName` | Lowercase display name (singular) |
| `translationHelper.pluralLowerDisplayName` | Lowercase plural display name |

### Examples

```twig
{{ translationHelper.displayName }}
{{ translationHelper.pluralDisplayName }}
{{ translationHelper.fullName }}
{{ translationHelper.lowerDisplayName }}
{{ translationHelper.pluralLowerDisplayName }}
```

---

