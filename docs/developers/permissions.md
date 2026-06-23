# Permissions

Translation Manager registers granular permissions that can be assigned to user groups via **Settings → Users → User Groups → [Group Name] → Translation Manager**.

Maintenance, provider, and translation-table actions are gated by **source-based permissions**: each translation source (a configured template category and every enabled form provider) gets its own capture / generate / delete / edit / approve handle, plus an "all sources" handle for each action.

Translation Manager permissions are independent of Craft site permissions. Craft site access does not restrict rows in the translations table; use Translation Manager's source-specific permissions to decide who can edit, approve, generate, capture, or delete **Site** category strings and **Forms** provider strings. The language selector controls which language rows a user is working on, not which Craft sites they can edit.

## Permission Structure

### Translations

| Permission | Description |
|------------|-------------|
| **`translationManager:manageTranslations`** | Access the Translations section (view/manage) |
| └─ `translationManager:editTranslations` | Parent — edit translation values |
|     └─ `translationManager:editAllTranslations` | Edit translations for all sources |
|     └─ `translationManager:editSource:{source}` | Edit one source's translations |
| └─ `translationManager:approveTranslations` | Parent — approve translated values |
|     └─ `translationManager:approveAllTranslations` | Approve translations for all sources |
|     └─ `translationManager:approveSource:{source}` | Approve one source's translations |

### Import / Export

| Permission | Description |
|------------|-------------|
| **`translationManager:manageImportExport`** | Parent — access the Import/Export section |
| └─ `translationManager:importTranslations` | Import translations from CSV files |
| └─ `translationManager:exportTranslations` | Export translations as CSV files |
| └─ `translationManager:clearImportHistory` | Clear the import history log |

### Generate Files

| Permission | Description |
|------------|-------------|
| **`translationManager:generateTranslations`** | Parent — access the Generate section |
| └─ `translationManager:generateAllSources` | Generate PHP translation files for all sources |
| └─ `translationManager:generateSource:{source}` | Generate PHP translation files for one source |

### Backups

| Permission | Description |
|------------|-------------|
| **`translationManager:manageBackups`** | Parent — access the Backups section |
| └─ `translationManager:createBackups` | Create new backups manually |
| └─ `translationManager:downloadBackups` | Download backup files as ZIP |
| └─ `translationManager:restoreBackups` | Restore translations from a backup |
| └─ `translationManager:deleteBackups` | Delete backup files |

### Maintenance

| Permission | Description |
|------------|-------------|
| **`translationManager:maintenance`** | Parent — access the Maintenance section |
| └─ `translationManager:captureTranslations` | Parent — capture missing strings (the **Capture** tab) |
|     └─ `translationManager:captureAllTranslations` | Capture translations for all sources |
|     └─ `translationManager:captureTranslations:{source}` | Capture one source's translations |
| └─ `translationManager:cleanMaintenanceArtifacts` | Clean artifacts — removed categories, ghost languages, and orphaned generated files (the **Cleanup** tab) |
| └─ `translationManager:deleteUnusedTranslations` | Parent — delete unused translations (the **Cleanup** tab) |
|     └─ `translationManager:deleteUnusedAllTranslations` | Delete unused translations for all sources |
|     └─ `translationManager:deleteUnusedSource:{source}` | Delete one source's unused translations |
| └─ `translationManager:deleteSourceTranslations` | Parent — delete translations in bulk (the **Danger** tab, destructive) |
|     └─ `translationManager:deleteAllSourceTranslations` | Delete all translations (every source) |
|     └─ `translationManager:deleteSourceTranslations:{source}` | Delete one source's translations |

### Logs

| Permission | Description |
|------------|-------------|
| **`translationManager:viewLogs`** | Parent — view plugin logs |
| └─ `translationManager:viewSystemLogs` | View system-level log entries |
|     └─ `translationManager:downloadSystemLogs` | Download system log files |

### Settings

| Permission | Description |
|------------|-------------|
| `translationManager:editSettings` | Access and modify plugin settings |

## Checking Permissions

In Twig:

```twig
{% if currentUser.can('translationManager:manageTranslations') %}
    {# User can access translations section #}
{% endif %}

{% if currentUser.can('translationManager:editTranslations') %}
    {# User can edit translations #}
{% endif %}
```

In PHP:

```php
if (Craft::$app->getUser()->checkPermission('translationManager:manageTranslations')) {
    // User has access to translations section
}

// In a controller
$this->requirePermission('translationManager:editTranslations');
```

## Nested Permission Pattern

Craft's nested permissions are a UI convenience — the parent permission does not automatically grant child permissions at runtime.

- **"Manage" permissions** (e.g., `manageTranslations`) are the access/view permission — they grant visibility of the section in the CP subnav
- **Write permissions** (e.g., `editTranslations`, `captureTranslations`, `deleteSourceTranslations`) are nested under manage and control specific operations

To give a user read-only access to translations, grant only `manageTranslations` (without any nested write permissions). For full access, also grant the specific write permissions needed.

For each source action there are two granularities: the **all-sources** handle (e.g. `captureAllTranslations`, `approveAllTranslations`, `deleteAllSourceTranslations`) and the **per-source** handle (e.g. `captureTranslations:provider:freeform`, `approveSource:provider:freeform`). Granting the all-sources handle covers every source; granting only a per-source handle limits the user to that one source.

## Source Permission Handles

Per-source permissions include the **source id** in the permission name. Source ids are namespaced so a configured category cannot collide with a provider:

- Configured categories use `category:{category}`, for example `category:messages`.
- Form providers use `provider:{provider}`, for example `provider:formie`.

| Source | Edit | Approve | Capture | Generate | Delete |
|--------|------|---------|---------|----------|--------|
| Messages category | `translationManager:editSource:category:messages` | `translationManager:approveSource:category:messages` | `translationManager:captureTranslations:category:messages` | `translationManager:generateSource:category:messages` | `translationManager:deleteSourceTranslations:category:messages` |
| Formie | `translationManager:editSource:provider:formie` | `translationManager:approveSource:provider:formie` | `translationManager:captureTranslations:provider:formie` | `translationManager:generateSource:provider:formie` | `translationManager:deleteSourceTranslations:provider:formie` |
| Freeform | `translationManager:editSource:provider:freeform` | `translationManager:approveSource:provider:freeform` | `translationManager:captureTranslations:provider:freeform` | `translationManager:generateSource:provider:freeform` | `translationManager:deleteSourceTranslations:provider:freeform` |

The source id is stable and does not change when the provider's display name changes. For example, a Formie install renamed to "Forms" still uses `provider:formie` in permission handles.
