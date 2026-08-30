# Translation Manager test lifecycle

`composer test` is the package-owned PHPUnit authority. It creates one disposable Craft/MySQL project, installs Logging Library, Formie, Freeform, and Translation Manager, seeds three language sites plus the owned translation baseline required by backup lifecycle checks, runs the integration suite, and then removes the exact project directory, database, and database grant it created.

The runner resolves from its own location and accepts only a package-local Composer vendor tree or the documented shared workspace vendor. PHPUnit refuses direct execution against an owner-managed Craft project. `composer ci:full` runs PHPStan and ECS over `src/` plus `tests/`, followed by this disposable suite; `composer quality-gate` adds platform, security, convention, hook, archive, lifecycle, and orchestration checks.

## Fixture and cleanup ownership

- The project root is `/tmp/translation-manager-fixture-{16 hex characters}` and the MySQL database is `tm_gate_{16 hex characters}`. Cleanup validates both identities before removal.
- The runner owns only its exact project, database, grant, generated files, active child process, and the sites inside that database. Success, operational failure, cleanup-only failure, combined failure, `INT`, `TERM`, `HUP`, and shutdown paths are exercised by the lifecycle probe.
- Integration rows use the `__tm_test_` marker and exact table/setting snapshots. The queue is replaced by a connection-local temporary shadow table before plugin bootstrap. Tests restore app components, settings, filesystem paths, queue state, and marker rows in teardown.
- Tests must never truncate shared tables, flush Redis or Craft caches, reuse owner browser/session state, or remove resources not recorded by the current invocation.

## Accepted suite authority

`tests/accepted-suite.json` freezes the accepted integration-class and test-method inventory, existing convention debt, and the disposable suite execution floor. `composer test-conventions` fails for new work-history identifiers or inventory drift. Complete disposable runs fail if tests/assertions fall below the accepted floor or if error, failure, skip, or incomplete counts change without an explicit reviewed baseline update.

Permanent tests use behavior-oriented names and keep audit, batch, PR, and debt history in `.internal/audit/`. Add new behavior to the cohesive existing class when possible; reusable fixture and lifecycle support belongs under `tests/Fixtures/` or `tests/Support/`.
