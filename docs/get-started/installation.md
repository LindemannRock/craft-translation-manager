# Installation & Setup

> [!NOTE]
> Translation Manager is in active development and not yet available on the Craft Plugin Store. Install via Composer for now.

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

3. **Optional** — Install [Logging Library](https://github.com/LindemannRock/craft-logging-library) for log viewing:

```bash title="Composer"
php craft plugin/install logging-library
```

```bash title="DDEV"
ddev craft plugin/install logging-library
```

Or via the Control Panel: **Settings → Plugins → Logging Library → Install**
