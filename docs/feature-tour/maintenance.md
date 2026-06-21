# Maintenance

Templates change, forms get deleted, categories get turned off — and the translation list slowly fills with strings nobody uses anymore. **Translation Manager → Maintenance** is where you keep it accurate: scan for strings that have fallen out of use, clean them up in bulk, and (carefully) clear translations when you need a fresh start.

## What you'll use it for

- Marking translations **Unused** after the templates or forms behind them are gone
- Removing unused, orphaned, or ghost-language rows in bulk
- Re-capturing form provider strings after you edit forms outside the plugin
- Clearing a category, a site, a provider, or everything — with a backup taken first

![Maintenance tools in the Translation Manager Control Panel](images/maintenance-tools.webp)

The page is split into three tabs — **Scan**, **Cleanup**, and **Danger Zone** — and each tab only appears if you have the matching permission. A backup is created automatically before any destructive action when [backups](backups.md) are enabled, so most of this is recoverable.

## Scan

The scanners find work for you — they don't delete anything; they flag strings or capture new ones.

### Template scanner

Find translations your templates no longer reference:

1. Open the **Scan** tab.
2. Click **Scan Templates**.
3. Every `.twig` file is scanned for translation usage. Any site translation that no longer appears is marked **Unused** — ready for you to review and remove on the **Cleanup** tab.

### Form provider scanners

If Formie or Freeform integration is enabled, each provider gets its own scanner — for example **Scan Formie Translations**. It re-reads every form and captures any field labels, placeholders, and other strings that aren't stored yet. Run it after you've added or changed forms. See [Integrations](../integrations/overview.md) for the full provider lifecycle.

## Cleanup

The **Cleanup** tab permanently deletes rows. When backups are enabled, the page tells you a backup will be taken first; if backups are off, the warning turns red.

### Clean up unused translations

1. Open the **Cleanup** tab.
2. Under **Type to Clean**, choose what to remove:
   - **All Unused** — every string the scanner flagged
   - **Unused {Provider}** — only a provider's unused strings (e.g. *Unused Formie*)
   - **Unused {Category}** — only one category's unused strings
3. Click **Clean Up Unused**.

### Clean up removed categories

When you disable a translation category, its rows stay in the database. To clear them out, pick the category under **Category to Clean Up** and click **Clean Up Category**. This deletes every row for that category — use it when a category was removed (for example, a plugin you no longer run).

### Clean up languages

Use this to remove leftover language rows — old mapped-source locales (like `en-US` once you've mapped it to `en`) or ghost test languages. Pick a language under **Language to Clean Up**, then choose how to remove it:

- **Migrate & Delete** *(mapped-source languages only)* — copies useful translations into the mapped target **without overwriting** existing translations, then deletes the mapped-source rows. Use this so you don't lose work when consolidating regional variants — see [Locale mapping](../get-started/configuration.md#locale-mapping).
- **Delete Only** — permanently removes the language's rows without copying anything.

### Clean up generated files

Removes a generated PHP file whose language folder or category is no longer a generation target. Pick the file under **Generated File to Clean Up** and click **Delete Generated File**. This only deletes the file on disk — **your database translation rows are not changed**, so you can regenerate at any time.

## Danger Zone

The **Danger Zone** tab clears translations in bulk — it deletes database rows **and** the matching generated PHP files, and it cannot be undone. A backup is taken first when backups are enabled.

1. Open the **Danger Zone** tab.
2. Under **Clear Translations**, choose the scope:
   - **All** — every translation
   - **{Provider} Only** — one form provider (e.g. *Formie Only*)
   - **{Category} Only** — one enabled site category
3. Click **Clear Selected Translations** and confirm.

Per-category clearing only accepts a category that's currently enabled, so you can't accidentally wipe a category you've already removed — clean those up on the **Cleanup** tab instead. Because a backup is created automatically (when enabled), even a full clear is recoverable from [Backups](backups.md).

## From the command line

Maintenance also runs from the console — handy for scheduled cleanups or CI:

```bash title="PHP"
php craft translation-manager/maintenance/scan-templates
php craft translation-manager/maintenance/clean-unused
php craft translation-manager/maintenance/clean-by-type --type=site
```

```bash title="DDEV"
ddev craft translation-manager/maintenance/scan-templates
ddev craft translation-manager/maintenance/clean-unused
ddev craft translation-manager/maintenance/clean-by-type --type=site
```

See [Console commands](../developers/console-commands.md) for the full list, including `preview-scan` (a dry run) and the `--provider` option.

## Permissions

Each tab is gated independently:

- **Scan** — *Scan Templates* (and provider recapture, where available)
- **Cleanup** — *Clean Unused Translations*
- **Danger Zone** — *Clear Translations*, with sub-permissions for clearing all vs. site vs. a provider

See [Permissions](../developers/permissions.md) for the full hierarchy.

## Tips

1. **Scan before you clean** — the scanner is what marks rows **Unused**; cleanup only acts on what's already flagged.
2. **Keep backups on** — every destructive action takes a backup first, turning a one-way delete into something you can roll back.
3. **Migrate, don't delete, when consolidating locales** — *Migrate & Delete* preserves translations; *Delete Only* doesn't.
