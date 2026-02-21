# Permissions

Translation Manager registers granular permissions that can be assigned to user groups via **Settings → Users → User Groups → [Group Name] → Translation Manager**.

## Permission Structure

### Translations

| Permission | Description |
|------------|-------------|
| **`translationManager:manageTranslations`** | Access the Translations section (view/manage) |
| └─ `translationManager:editTranslations` | Modify and save translation values |
| └─ `translationManager:deleteTranslations` | Delete unused translations |

### Import / Export

| Permission | Description |
|------------|-------------|
| **`translationManager:manageImportExport`** | Parent — access the Import/Export section |
| └─ `translationManager:importTranslations` | Import translations from CSV files |
| └─ `translationManager:exportTranslations` | Export translations as CSV files |
| └─ `translationManager:viewImportHistory` | View the import history log |
| └─ `translationManager:clearImportHistory` | Clear the import history log |

### Generate Files

| Permission | Description |
|------------|-------------|
| **`translationManager:generateTranslations`** | Parent — access the Generate section |
| └─ `translationManager:generateAllTranslations` | Generate PHP translation files for all types |
| └─ `translationManager:generateFormieTranslations` | Generate PHP translation files for Formie only |
| └─ `translationManager:generateSiteTranslations` | Generate PHP translation files for site only |

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
| └─ `translationManager:cleanUnused` | Clean up unused translations |
| └─ `translationManager:scanTemplates` | Run template scanner to identify unused translations |
| └─ `translationManager:recaptureFormie` | Recapture all Formie form translations |

### Clear Translations

| Permission | Description |
|------------|-------------|
| **`translationManager:clearTranslations`** | Parent — access clear operations |
| └─ `translationManager:clearFormie` | Delete all Formie translations |
| └─ `translationManager:clearSite` | Delete all site translations |
| └─ `translationManager:clearAll` | Delete all translations |

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
- **Write permissions** (e.g., `editTranslations`, `deleteTranslations`) are nested under manage and control specific operations

To give a user read-only access to translations, grant only `manageTranslations` (without any nested write permissions). For full access, also grant the specific write permissions needed.
