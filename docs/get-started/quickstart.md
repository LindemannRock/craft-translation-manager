# Quickstart

Get Translation Manager running in under 5 minutes. By the end of this guide you'll have translations captured and editable from the CP.

## 1. Install the Plugin

See [Installation](installation.md) for full details including DDEV and Composer options.

## 2. Enable Site Translations

Go to **Translation Manager > Settings > Translation Sources** and confirm that **Enable Site Translations** is turned on. This captures any `|t()` calls from your templates as translatable strings.

## 3. Visit a Frontend Page

Open any page on your site that uses `|t('messages')` in its templates. Translation Manager automatically captures these strings as they render.

## 4. Translate a String

1. Go to **Translation Manager** in the CP
2. You should see captured strings with a **Pending** status
3. Switch to a secondary site using the site selector
4. Enter translations for any pending strings
5. Click **Save All** (or press Ctrl/Cmd+S)

## What's Next

- [Configuration](configuration.md) — customize translation categories, auto-export, and locale mapping
- [Feature Tour](../feature-tour/overview.md) — explore multi-site support, import/export, and backups
