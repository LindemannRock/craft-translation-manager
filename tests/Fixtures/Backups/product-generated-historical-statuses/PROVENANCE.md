# Historical backup fixture provenance

- Producer commit: `1cf468858d68862925115febf531fdcb7fa07844`
- Producer commit date: 2026-02-26 22:52:11 +0400
- Package-reported version: `5.21.3`
- Generation method: `BackupService::_createLocalBackup()` was loaded directly from a detached worktree at the producer commit and invoked by `scripts/generate-historical-backup-fixture.php` with synthetic rows only.
- Regeneration command: `./scripts/regenerate-historical-backup-fixture --write`
- Byte-verification command: `./scripts/regenerate-historical-backup-fixture --verify`
- Backup checksum: `929df936364e3ec4e4ac47a96ae8300a6bb0ee5444607957a7ef347dddc2d7cd`
- Checksum rule: SHA-256 of the exact `formie-translations.json` bytes followed by the exact `site-translations.json` bytes.
- File SHA-256 values:
  - `formie-translations.json`: `ffaa31c2fde5379350cb74ae56ea2fbda13c06dbff2f8879430bc14b5d467dd4`
  - `site-translations.json`: `0436624366900fa17b63ba8fd8cdd788d24a62cd693e3c0ada2fb7dade171710`
  - `metadata.json`: `6e2cfae5e7ec8d33115284c87e5d9a113b39c1d859ed55a8294953ad2fdd928c`

The fixture is not copied from an owner or client backup. Commit `1cf4688` accepted both `approved` and `ai_draft`; its translation schema predates `translationOrigin`, creator/reviewer attribution, and `reviewedAt`. Restore compatibility therefore supplies the documented `system`/`null` defaults for this fixture. Current-format product backup tests separately prove preservation of those later semantic fields.
