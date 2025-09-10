# Security Policy

## Reporting Security Vulnerabilities

We take security seriously at LindemannRock. If you discover a security vulnerability in the Translation Manager plugin, please report it responsibly.

**DO NOT** create a public GitHub issue for security vulnerabilities.

Instead, please email `security@lindemannrock.com` with:

- A description of the vulnerability
- Steps to reproduce the issue
- Potential impact
- Any suggested fixes

We will acknowledge receipt within 48 hours and provide updates as we work on a fix.

## Security Features

The Translation Manager plugin implements comprehensive security measures to protect your translation data and maintain system integrity.

### Authentication & Authorization

#### Permission System

The plugin implements granular permissions that must be explicitly granted to user groups:

- `translationManager:viewTranslations` - View translation interface
- `translationManager:editTranslations` - Edit and save translations
- `translationManager:deleteTranslations` - Delete unused translations
- `translationManager:exportTranslations` - Export to CSV or PHP files
- `translationManager:editSettings` - Manage plugin settings

#### Access Control

- All controllers require authentication (`$allowAnonymous = false`)
- Permission checks in `beforeAction()` throw `ForbiddenHttpException` for unauthorized access
- Admin-only operations protected by Craft's admin requirement

### Input Validation & Sanitization

#### Translation Input

- **Length Limits**: Arabic translations limited to 5000 characters
- **Type Validation**: Strict type checking for IDs (must be numeric)
- **Array Validation**: Ensures array parameters are actually arrays

#### Settings Validation

- **Translation Category**:
  - Must start with a letter
  - Only alphanumeric characters allowed
  - Reserved categories blocked (site, app, yii, craft)
- **Export Path**: Restricted to safe aliases (@root, @storage, @config, @webroot)
- **Numeric Ranges**: Items per page limited to 10-500

### CSRF Protection

All data-modifying actions require CSRF token validation:

- Translation saves
- Bulk operations
- Settings changes
- Export operations

Implementation via `$this->requirePostRequest()` in all POST action methods.

### XSS Prevention

#### Template Escaping

All user-supplied content is escaped in templates:

```twig
{{ translation.englishText|e }}
{{ translation.arabicText|e }}
{{ translation.context|e }}
```

#### No Inline JavaScript

- User data never placed in inline scripts
- All dynamic data passed via data attributes
- Event delegation used for dynamic content

### Path Traversal Protection

Export paths are strictly validated:

1. Paths containing `..` are rejected
2. Must start with approved aliases
3. Double validation in Settings model and ExportService
4. Real path resolution to prevent symlink attacks

### CSV Import Security

The plugin implements multiple layers of security for CSV imports:

#### File Validation
- Only `.csv` and `.txt` extensions allowed
- MIME type verification against whitelist
- 5MB file size limit
- Temporary file cleanup after processing

#### Content Sanitization
All imported content undergoes aggressive sanitization:

1. **HTML Stripping**: Complete removal of all HTML tags
2. **Entity Decoding**: Decode and re-strip to catch encoded attacks
3. **Pattern Removal**: Removes dangerous patterns:
   - `<script>`, `<svg>`, `<iframe>`, `<object>`, `<embed>` tags
   - `javascript:`, `vbscript:`, `data:text/html` protocols
   - Event handlers (onclick, onload, etc.)
   - Any remaining HTML after decoding

#### Example Attack Prevention
```csv
"Test","<svg onload=alert('XSS')>","site" → "Test","","site"
"Hello","&lt;script&gt;alert(1)&lt;/script&gt;","" → "Hello","alert(1)","site"
```

### CSV Export Protection

The `sanitizeForCsv()` method prevents formula injection:

- Dangerous characters (=, +, -, @, |, %) prefixed with single quote
- Proper quote escaping
- UTF-8 BOM for encoding consistency

### SQL Injection Prevention

- All database queries use Craft's Query Builder
- Parameterized queries throughout
- No raw SQL execution
- Proper parameter binding for WHERE conditions

### File Operation Security

#### Atomic Writes

- Temporary files created first
- Atomic rename to final location
- Proper cleanup on failure

#### File Locking

- `LOCK_EX` flag on write operations
- Prevents race conditions
- Ensures data integrity

### Logging & Auditing

#### Security Event Logging

All security-relevant operations are logged with:

- User ID performing action
- Operation type and parameters
- Timestamp
- IP address (when available)

#### Log Security

- Date-based log files (translation-manager-YYYY-MM-DD.log)
- Only errors and warnings logged (reduced verbosity)
- 10MB size limit per file
- Maximum 30 days of logs retained
- Logs stored outside web root

### Request Security

#### Type Enforcement

- POST required for data modifications
- JSON response requirement for AJAX
- Accept header validation

#### Headers

- Proper Content-Type for downloads
- Cache-Control to prevent sensitive data caching
- Content-Disposition for safe downloads

## Best Practices for Users

### Permission Configuration

1. Grant minimum necessary permissions
2. Regularly audit user permissions
3. Use separate accounts for different roles
4. Enable two-factor authentication for admin accounts

### Export Security

1. Regularly review export paths
2. Limit export permissions to trusted users
3. Secure exported files appropriately
4. Delete old exports when no longer needed

### Translation Management

1. Review translations before saving
2. Be cautious with user-submitted translations
3. Validate special characters in translations
4. Regular backups of translation data

### System Security

1. Keep Craft CMS updated
2. Update the plugin regularly
3. Monitor security logs
4. Use HTTPS for all connections

## Security Checklist

### Installation

- [ ] Configure appropriate user permissions
- [ ] Set secure export paths
- [ ] Enable logging
- [ ] Review skip patterns

### Regular Maintenance

- [ ] Check for plugin updates
- [ ] Review security logs
- [ ] Audit user permissions
- [ ] Validate export configurations

### Before Production

- [ ] Test all permissions
- [ ] Verify CSRF protection
- [ ] Check file permissions
- [ ] Enable HTTPS

## Future Enhancements

We are continuously working to improve security:

### Planned Features

- Rate limiting for operations
- Enhanced audit logging
- Two-factor authentication integration
- IP allowlisting
- Content Security Policy headers

### Under Consideration

- PGP encryption for exports
- API authentication tokens
- Webhook security
- Advanced threat detection

## Compliance

The Translation Manager plugin is designed to help meet common compliance requirements:

- **Data Protection**: Secure storage and transmission
- **Access Control**: Granular permissions
- **Audit Trail**: Comprehensive logging
- **Data Integrity**: Validation and sanitization

## Version History

### 1.0.0

- Initial release with comprehensive security features
- CSRF protection
- XSS prevention
- Path traversal protection
- CSV injection protection
- Permission system
- Security logging
