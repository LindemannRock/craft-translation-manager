# Requirements

## System Requirements

| Requirement | Version |
|-------------|---------|
| [Craft CMS](https://craftcms.com/) | 5.10+ |
| [PHP](https://php.net/) | 8.2+ |

## Dependencies

Composer pulls these packages automatically. Craft plugin dependencies also need to be installed in the Control Panel.

| Package | Version | Purpose |
|---------|---------|---------|
| [lindemannrock/craft-plugin-base](https://github.com/LindemannRock/craft-plugin-base) | 5.28.1+ | Shared base plugin utilities (helpers, traits, layouts) |
| [lindemannrock/craft-logging-library](https://github.com/LindemannRock/craft-logging-library) | 5.12.0+ | Optional — install in CP for log viewing |

## Optional Form Integrations

Translation Manager can capture provider strings when these form plugins are installed and enabled.

| Provider | Verified Craft 5 Version | Notes |
|----------|--------------------------|-------|
| [Formie](https://verbb.io/craft-plugins/formie/features) | 3.1.14+ | Captures Formie form, page, field, option, and button text. |
| [Freeform](https://plugins.craftcms.com/freeform) | 5.15.6.1+ | Works with any Freeform edition supported by that version. |
