# Translation Manager Test Files

This directory contains test CSV files for various scenarios including multi-site support and security testing.

## Test Files

### Valid Multi-Site Tests

**`multi-site-valid.csv`**
- Tests normal multi-site import/export functionality
- Contains translations for both English (site ID 1) and Arabic (site ID 2) 
- Tests both Site and Formie translation types
- Tests pending and translated statuses
- Includes mixed case and Unicode content

### Edge Case Tests

**`multi-site-edge-cases.csv`**
- Tests boundary conditions and unusual but valid inputs
- Invalid site IDs (999, non-existent)
- Missing site information (fallback to primary site)
- Wrong language formats
- Very long content
- Unicode characters and mixed scripts
- Special characters and symbols
- Line breaks, tabs, and HTML entities
- Status variations (different cases)

### Security Tests

**`multi-site-malicious.csv`**
- Tests malicious input attempts
- XSS attacks in all columns
- SQL injection attempts
- Command injection
- Path traversal attacks
- CSV formula injection (=, +, -, @, |)
- File protocol attacks
- LDAP, XML, and XXE injection
- Header injection and CRLF attacks
- PHP code execution attempts

**`malicious-test.csv`** (Legacy)
- Original malicious content testing
- XSS, script injection, event handlers
- Formula injection for CSV attacks
- Mixed encoding attacks

## Legacy Test Files

**`malicious-test.csv`**
- Original malicious content testing (pre-multi-site)
- Still valuable for testing basic security protections

## Usage

### Testing Valid Multi-Site Import
1. Export current translations to get baseline
2. Import `multi-site-valid.csv`
3. Verify translations appear in correct sites
4. Check that English translations go to site ID 1
5. Check that Arabic translations go to site ID 2

### Testing Edge Cases
1. Import `multi-site-edge-cases.csv`
2. Verify system handles invalid site IDs gracefully
3. Check fallback to primary site works
4. Verify long content is handled properly
5. Check Unicode and special characters work

### Testing Security
1. Import `multi-site-malicious.csv`
2. Verify no malicious code execution
3. Check all HTML is stripped
4. Verify no SQL injection occurs
5. Check CSV formula injection is blocked
6. Verify no file system access
7. Check headers aren't injected

### Expected Security Behavior
- All HTML tags should be stripped
- JavaScript should be removed
- Formula characters (=+@|-) should be prefixed or escaped
- File paths should be sanitized
- SQL injection should be parameterized
- Long content should be truncated
- Invalid site IDs should fallback to primary site

## Test Results

After running tests, verify:
1. **No malicious content** appears in database
2. **No code execution** occurs
3. **Site-specific translations** are properly assigned
4. **Fallback mechanisms** work for invalid data
5. **Error handling** is graceful
6. **Performance** remains acceptable with large imports