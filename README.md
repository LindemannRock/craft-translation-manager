# Translation Manager Plugin for Craft CMS

[![Latest Version](https://img.shields.io/packagist/v/lindemannrock/craft-translation-manager.svg)](https://packagist.org/packages/lindemannrock/craft-translation-manager)
[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.0+-orange.svg)](https://craftcms.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/lindemannrock/craft-translation-manager.svg)](LICENSE)

Comprehensive translation management for Craft CMS 5 with Formie integration, multi-site support, and enterprise-grade security.

## License

Commercial plugin licensed under the [Craft License](https://craftcms.github.io/license/). See [LICENSE.md](LICENSE.md) for details.

## Features

- **Multi-Site Translations** — site-aware management for any language combination with locale variant support
- **Multi-Category Support** — multiple translation categories (site, emails, errors) with separate file generation
- **Formie Integration** — automatic capture of all field types including options, subfields, and complex fields
- **Smart Deduplication** — each unique text stored once, context updated automatically
- **Capture Missing Translations** — auto-add translations at runtime when `|t()` encounters unknown strings
- **Locale Mapping** — consolidate regional variants (en-US, en-GB) to base locales
- **Import/Export** — CSV import with preview and validation, PHP file import/export
- **Backup System** — scheduled backups with cloud storage (S3, Servd, Wasabi) and one-click restore
- **Maintenance Tools** — template scanner, usage detection, granular cleanup
- **Security** — XSS protection, CSRF validation, path traversal prevention, CSV injection guards
- **RTL Support** — full Arabic/Hebrew text editing with proper display
- **Logging** — dedicated log files with CP viewer

## Requirements

| Requirement | Version |
|-------------|---------|
| Craft CMS   | 5.0+    |
| PHP         | 8.2+    |

## Installation

### Via Composer

```bash
cd /path/to/project
```

```bash
composer require lindemannrock/craft-translation-manager
```

```bash
./craft plugin/install translation-manager
```

### Using DDEV

```bash
cd /path/to/project
```

```bash
ddev composer require lindemannrock/craft-translation-manager
```

```bash
ddev craft plugin/install translation-manager
```

## Documentation

Full documentation available at **Settings > Translation Manager > Docs** or in the [`docs/`](docs/) folder.

## Support

- Email: support@lindemannrock.com
- Issues: [GitHub Issues](https://github.com/LindemannRock/craft-translation-manager/issues)

---

<p align="center">Made by <a href="https://lindemannrock.com">LindemannRock</a></p>
