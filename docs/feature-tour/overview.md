# Features overview

Translation Manager gives you one place to translate every string your Craft site shows — Formie and Freeform form content, template text, and strings from other plugins — across all of your sites. It can capture template strings as they're used, captures provider strings as forms are saved, lets your team translate everything in the Control Panel, and generates the production PHP files Craft loads at runtime.

> [!NOTE]
> Translation Manager localizes the **strings** your site shows — Formie and Freeform form fields, template text (`|t()`), Control Panel/UI labels, and other plugins' frontend strings. It is **not an entry/content translator**: your editorial content (entries, fields, sections) stays in Craft's native multi-site model, and this plugin handles everything around it.

## What you'll use it for

- Translating site copy and labels for a multi-language site without hand-editing PHP files
- Localizing Formie and Freeform forms — field labels, options, buttons, and messages — automatically
- Letting non-developers manage translations in the Control Panel while developers keep working in templates
- Importing existing translations (CSV or PHP files) when onboarding a project, and exporting them for translators
- Keeping a safety net — backups before every destructive operation — so cleanup and imports are never one-way

![Translation Manager translations list in the Control Panel](images/overview-translations-list.webp)

## What's in the box

### Core

- **Multi-language site support** — manage one key across any site/language combination ([Multi-site](multi-site.md))
- **Unified management** — every translation in one searchable, filterable list
- **Smart deduplication** — each unique string is stored once, no matter how many forms reuse it
- **Built-in form provider support** — automatic capture and generation for Formie and Freeform ([Integrations](../integrations/overview.md))
- **Site translations** — a dedicated category for your own site copy, kept separate from plugin UI strings
- **Advanced filtering** — filter by type (Forms / Site), status (Pending / Draft / Translated / Unused), origin (Manual / Import / System), and free-text search
- **Bulk operations** — save every change at once, or bulk-delete unused translations

### Smart

- **Usage detection** — flags translations as unused when the form or field behind them is deleted
- **Capture missing translations** — when enabled, adds strings at runtime when a `|t()` call hits text that isn't stored yet
- **Approval workflow** — require sign-off so translations publish as Draft until an approver marks them Translated ([Managing translations](managing-translations.md#approval-workflow))
- **Maintenance tools** — capture, cleanup, and bulk deletes, each backed up first ([Maintenance](maintenance.md))
- **Statistics utility** — a Control Panel **Utilities** panel showing coverage %, the pending/unused work queue, and the Forms/Site split ([Managing translations](managing-translations.md#monitor-coverage))
- **Locale mapping** — consolidate regional variants (en-US, en-GB) onto a base locale to cut duplication ([Configuration](../get-started/configuration.md#locale-mapping))
- **Import / export** — CSV export with your current filters, CSV import with preview and malicious-content detection ([Import / export](import-export.md))
- **PHP translation files** — generate and import production-ready PHP files

### Operations

- **Dedicated logging** — all operations written to `storage/logs/translation-manager-YYYY-MM-DD.log` with a Control Panel viewer
- **Security hardened** — XSS escaping, CSRF validation, and symlink-attack prevention ([Security](security.md))
- **Backup system** — manual and scheduled backups with cloud-storage support and one-click restore ([Backups](backups.md))
- **RTL support** — full right-to-left editing for languages such as Arabic
- **Keyboard shortcuts** — Ctrl/Cmd + S to save all changes

## Explore the features

- [Multi-site support](multi-site.md) — manage one key across every language your sites use
- [Integrations](../integrations/overview.md) — the Formie and Freeform provider lifecycle
- [Import / export](import-export.md) — CSV and PHP file operations
- [Maintenance](maintenance.md) — capture, clean up, and delete translations safely
- [Backup system](backups.md) — protect translations before destructive operations
- [Security](security.md) — the built-in protections
