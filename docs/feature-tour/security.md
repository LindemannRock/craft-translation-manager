# Security

Translation Manager includes comprehensive security measures to protect your data and system.

## Built-in Protection

### Input Validation

- **XSS Protection**: All template output properly escaped
- **CSRF Protection**: All forms validate CSRF tokens
- **SQL Injection Protection**: Parameterized queries throughout
- **Length Limits**: 5000 character max on translations
- **Category Validation**: Letters, numbers, hyphens, underscores only

### Path Security

- **Symlink Attack Prevention**: Real path resolution prevents symlink traversal
- **Path Restriction**: Export paths limited to secure aliases (@root, @storage, @translations)
- **Backup Security**: Backup paths restricted to non-web-accessible locations

### File Operations

- **Atomic Writes**: Temp files with proper locking
- **CSV Injection Protection**: Special characters prefixed in exports
- **File Type Validation**: Strict MIME type checking on uploads

### PHP Import Security

> [!IMPORTANT]
> Only import PHP files from trusted sources.

- **Safe Parsing**: PHP translation files are parsed using tokenization without code execution
- **Token Validation**: Only safe tokens allowed (return, array syntax, strings, whitespace, comments)
- **No Code Execution**: Malicious code in PHP files is rejected before any execution
- **Path Restriction**: PHP import limited to configured translations directory
- **DevMode Only**: PHP import feature only available when devMode is enabled

### Access Control

- **Permission-based Access**: Granular permissions for all operations
- **Anonymous Access Prevention**: All actions require authentication
- **Security Event Logging**: Comprehensive audit trail with user tracking

## Security Best Practices

### For Administrators

1. **Permission Management**
   - Grant only necessary permissions to user groups
   - Regularly audit user permissions
   - Use separate accounts for different roles
   - Enable two-factor authentication for admin accounts

2. **Export Security**
   - Configure export paths to secure directories
   - Limit export permissions to trusted users
   - Regularly clean up old export files
   - Monitor export logs for unusual activity

3. **System Maintenance**
   - Keep Craft CMS and all plugins updated
   - Review security logs regularly
   - Monitor for failed permission attempts
   - Backup translation data regularly

### For Developers

1. **Template Usage**
   - Always use the proper translation filter syntax
   - Never output translation data without escaping
   - Avoid inline JavaScript with translation data

2. **Custom Integrations**
   - Validate all input when using the plugin's services
   - Use Craft's permission system for access control
   - Log security-relevant operations

## Reporting Security Issues

For security vulnerabilities, contact us directly:

**Email**: security@lindemannrock.com

**DO NOT** create public GitHub issues for security vulnerabilities.
