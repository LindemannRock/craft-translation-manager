# Installation & Setup

## Composer

Add the package to your project using Composer and the command line.

1. Open your terminal and go to your Craft project:

```bash
cd /path/to/project
```

2. Then tell Composer to require the plugin, and Craft to install it:

```bash title="Composer"
composer require lindemannrock/craft-translation-manager && php craft plugin/install translation-manager
```

```bash title="DDEV"
ddev composer require lindemannrock/craft-translation-manager && ddev craft plugin/install translation-manager
```

3. **Optional** — Enable [Logging Library](https://github.com/LindemannRock/craft-logging-library) for log viewing:

> [!NOTE]
> Logging Library is required by Composer. Install or activate it in Craft to enable log viewing.

```bash title="PHP"
php craft plugin/install logging-library
```

```bash title="DDEV"
ddev craft plugin/install logging-library
```

Or via the Control Panel: **Settings → Plugins → Logging Library → Install**

## Post-Install Setup

Open **Translation Manager → Settings → Translation Sources** and confirm that **Enable Site Translations**, **Translation Categories**, and **Source Language** match your project. Set the source language before capturing strings; changing it later does not migrate existing rows.

### Review configuration

Most settings can be managed in **Translation Manager → Settings**. See [Configuration](configuration.md) when you need config-file overrides or environment-specific values.

## Quick Start

See [Quickstart](quickstart.md) for the fastest path from installation to your first translated string.
