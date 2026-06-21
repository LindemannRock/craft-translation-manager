# Security

Translation Manager is built to keep your translation data and your server safe — translations are user-supplied content, imports touch the filesystem, and exports leave the system, so each of those paths is hardened. This page lists the protections that are on by default and the practices that keep them effective.

## What's protected

- **The strings themselves** — escaped on output and length-limited on input
- **File operations** — imports, exports, and generated files are path-restricted and validated
- **PHP imports** — parsed without ever executing the file's code
- **Access** — every operation is gated by a granular permission and requires authentication

## Built-in protection

### Input validation

- **XSS protection** — all template output is properly escaped
- **CSRF protection** — every form validates CSRF tokens
- **SQL injection protection** — parameterized queries throughout
- **Length limits** — 5000-character maximum per translation
- **Category validation** — letters, numbers, hyphens, and underscores only

### Path security

- **Symlink-attack prevention** — real-path resolution blocks symlink traversal
- **Path restriction** — export paths are limited to secure aliases (`@root`, `@storage`, `@translations`)
- **Backup security** — backup paths are restricted to non-web-accessible locations

### File operations

- **Atomic writes** — temp files with proper locking
- **CSV injection protection** — leading special characters are prefixed in exports
- **File-type validation** — strict MIME-type checking on uploads

### PHP import security

> [!IMPORTANT]
> Only import PHP files from trusted sources.

- **Safe parsing** — PHP translation files are read by tokenization, with no code execution
- **Token validation** — only safe tokens are allowed (return, array syntax, strings, whitespace, comments)
- **No code execution** — malicious code in a PHP file is rejected before anything runs
- **Path restriction** — PHP import is limited to the configured translations directory
- **devMode only** — PHP import is available only when devMode is enabled

### Access control

- **Permission-based access** — granular permissions for every operation ([Permissions](../developers/permissions.md))
- **Anonymous-access prevention** — all actions require authentication
- **Security event logging** — a full audit trail with user tracking

## Best practices

### For administrators

1. **Permission management** — grant only the permissions a group needs, audit them regularly, use separate accounts for different roles, and enable two-factor authentication for admins.
2. **Export security** — point exports at secure directories, limit export permissions to trusted users, clean up old export files, and watch export logs for unusual activity.
3. **System maintenance** — keep Craft and all plugins updated, review security logs, watch for failed permission attempts, and back up translation data regularly.

### For developers

> The rest of this section is for developers integrating with the plugin in code.

1. **Template usage** — always use the proper translation filter syntax, never output translation data without escaping, and avoid inline JavaScript built from translation data.
2. **Custom integrations** — validate all input when calling the plugin's services, use Craft's permission system for access control, and log security-relevant operations.

## Reporting security issues

For security vulnerabilities, contact us directly at **security@lindemannrock.com**.

**Do not** open public GitHub issues for security vulnerabilities.
