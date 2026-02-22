# Features Overview

Translation Manager provides a comprehensive translation management system for Craft CMS 5 with Formie integration, advanced filtering, and enterprise-grade security.

## Core Features

- **Multi-Site Translation Support**: Site-aware translation management for any language combination
- **Unified Translation Management**: Manage all translations in one place with an intuitive interface
- **Smart Deduplication**: Each unique text is stored only once, regardless of how many forms use it
- **Comprehensive Formie Support**: Automatic capture of ALL field types including options, subfields, and special properties
- **Site Translations**: Custom translation category for site content with namespace protection
- **Advanced Filtering**: Filter by type (Forms/Site), status (Pending/Translated/Not Used), and search
- **Bulk Operations**: Save all changes at once, bulk delete unused translations

## Smart Features

- **Smart Usage Detection**: Automatically identifies unused translations when forms/fields are deleted
- **Capture Missing Translations**: Automatically add translations at runtime when `|t()` calls encounter unknown strings
- **Advanced Maintenance Tools**: Template scanner to identify unused translations automatically
- **Locale Mapping**: Consolidate regional variants (en-US, en-GB) to base locales to reduce duplication
- **Import/Export Functionality**: CSV export with current filters, CSV import with preview and malicious content detection
- **PHP Translation Files**: Generate and import production-ready PHP translation files

## Enterprise Features

- **Dedicated Logging**: All operations logged to `storage/logs/translation-manager.log`
- **Security Hardened**: XSS protection, CSRF validation, symlink attack prevention
- **Advanced Backup System**: Manual and automatic backups with cloud storage support
- **RTL Support**: Full support for Arabic text editing with proper RTL display
- **Keyboard Shortcuts**: Ctrl/Cmd+S to save all changes

## Feature Details

- [Multi-Site Support](multi-site.md) - Site-aware translation management
- [Backup System](backups.md) - Comprehensive backup and restore
- [Import/Export](import-export.md) - CSV and PHP file operations
- [Security](security.md) - Built-in security measures
