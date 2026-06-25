# Senior Maintainer Review Report

Review date: 2026-06-25

Scope: Laravel package structure, Filament v5 compatibility, security posture, public API ergonomics, tests, documentation, frontend build, migration safety, premium distribution readiness, and multi-tenancy risk.

## Critical Issues

1. Queued CSV imports lose user and tenant context.

   `ProcessSpreadsheetCsvImport` only serializes the editor token, import token, mapping, and match mode, then calls `ApplySpreadsheetCsvImport::applyStored()` without a user or source request context (`src/Jobs/ProcessSpreadsheetCsvImport.php:15-39`). This means queued imports do not re-check user authorization at execution time, audit rows will not have the triggering user, and tenant scoping depends on `Filament::getTenant()` being available in a queue worker. That is risky for premium customers with multi-tenant panels or user-specific policies.

   Recommended fix: persist an import job record containing editor key, user id, panel id, tenant morph type/id, request metadata, and an authorization snapshot. Rehydrate the user and tenant in the job before applying rows, and fail safely when the tenant cannot be restored.

2. Package routes are not Filament panel aware by default.

   Routes are registered globally under `filament-spreadsheet-editor` with configurable middleware defaulting to `['web', 'auth']` (`routes/filament-spreadsheet-editor.php:10-27`). This protects basic Laravel auth, but it does not automatically inherit a Filament panel's auth guard, tenant middleware, panel path, or panel-specific middleware stack. For a Filament v5 plugin, this can produce confusing behavior in apps with multiple panels or non-default guards.

   Recommended fix: register endpoints through the active Filament panel where possible, or provide a panel-aware route registration helper that uses the panel auth middleware and panel tenant middleware. At minimum, document exact middleware requirements for each panel and add tests for a non-default guard.

3. CSV import tokens are file-only tokens, not bound to an editor/user.

   The preview endpoint stores an uploaded CSV using a UUID token, and the apply endpoint accepts that token if the file exists (`src/Actions/ApplySpreadsheetCsvImport.php:40-49`). The token has good entropy and path validation, but it is not associated with the editor token, the authenticated user, the tenant, or an expiration policy. A leaked token could be replayed by another authorized user against a compatible editor mapping.

   Recommended fix: store import metadata beside the file or in a database table/cache entry: editor token/key, user id, tenant id, original filename, created timestamp, expiry, and consumed state. Verify all metadata before apply and delete expired previews.

## Recommended Fixes

1. Register and test Filament assets using the Filament asset pipeline.

   The service provider publishes built assets and source assets (`src/FilamentSpreadsheetEditorServiceProvider.php:45-52`), but it does not register JS/CSS with Filament's asset manager. Customers may install the plugin, register it in a PanelProvider, and still miss runtime assets unless they manually include them.

   Fix: add Filament asset registration in `packageBooted()` or plugin boot, then document the publish/build path only as an override for custom builds.

2. Add JavaScript tests to CI.

   The package has JS tests and an `npm test` script, but GitHub Actions only runs `npm ci` and `npm run build` (`.github/workflows/ci.yml:38-48`). Frontend behavior such as undo/redo, save result handling, and shortcut behavior can regress without CI catching it.

   Fix: add `npm test` to CI and optionally to `composer ci` through the existing `build` script chain or a separate `frontend:test` script.

3. Expand CI compatibility matrix.

   CI currently runs only PHP 8.2 (`.github/workflows/ci.yml:7-19`). The package advertises PHP 8.2+, Laravel 11/12, Filament v5, and Livewire v4, but there is no matrix covering PHP 8.3/8.4, Laravel 11 vs 12, or dependency lowest-stable installs.

   Fix: add a matrix for PHP 8.2/8.3/8.4 and Laravel 11/12 constraints, plus a lowest dependency job if package-tooling time permits.

4. Harden audit model mass assignment.

   `SpreadsheetCellAudit` uses `$guarded = []`. The current package writes audit rows internally with explicit fields, so this is not immediately exploitable, but premium packages should avoid an unsafe-looking audit model by default.

   Fix: replace `$guarded = []` with an explicit `$fillable` list for audit columns.

5. Make validation rule support more Laravel-native.

   `SpreadsheetColumn` stores validation rules as strings only. That keeps frontend serialization simple, but Laravel customers will expect support for `Rule` objects, custom rule classes, nullable arrays, and per-row dynamic rules.

   Fix: split server rules from serializable frontend hints. Accept `string|Rule|array` on the PHP API and serialize only safe client-readable metadata.

6. Improve transaction semantics for batch events.

   `SpreadsheetCellUpdated` and `SpreadsheetBatchUpdated` are dispatched inside the database transaction. If external listeners perform side effects and the transaction later rolls back, listeners can observe changes that were not committed.

   Fix: either document that events are in-transaction or dispatch after commit with Laravel's after-commit event behavior.

7. Add an explicit tenant safety mode.

   Tenant scoping is available through `tenantQuery()`, but nothing warns if a registered editor for a tenant-owned model omits it. Documentation notes this, but the runtime cannot distinguish safe global editors from forgotten tenant scope.

   Fix: add optional `requiresTenant()` or `tenantScoped()` API that fails closed when Filament tenancy is active but no tenant scope has been applied.

8. Improve release artifact checks.

   Built files in `dist/` are committed, but CI does not verify that `npm run build` leaves no diff. This can ship stale assets.

   Fix: after `npm run build`, run `git diff --exit-code dist package-lock.json` in CI.

## Nice-To-Have Improvements

1. Add a formal adapter registry.

   `GridAdapter` exists, but adapter resolution is currently a simple config match for Tabulator. A `GridAdapterManager` or container-tagged adapter registry would make AG Grid support cleaner.

2. Add richer column APIs.

   Useful premium APIs would include `money()`, `enum()`, `select()`, `relationshipLabel()`, `hidden()`, `visible()`, `width()`, `frozen()`, `formatStateUsing()`, and `dehydrateStateUsing()`.

3. Add per-operation authorization hooks.

   A single `authorize()` callback is simple, but real admin panels often need `authorizeView`, `authorizeUpdate`, `authorizeImport`, and `authorizeExport`.

4. Add observability hooks for imports.

   CSV import would benefit from import-started/import-finished/import-failed events, stored row error reports, and a Filament notification integration.

5. Add browser-level smoke tests.

   Unit-level JS tests are good, but a small Playwright smoke test would catch missing Alpine registration, missing assets, and Tabulator mount failures.

6. Add a license extension contract.

   Documentation mentions a license validation extension, but there is not yet a concrete contract or service binding for marketplace licensing.

7. Add SECURITY.md links from README and docs.

   `SECURITY.md` exists, but the package docs should route paid customers to the disclosure process more directly.

8. Add migration customization notes.

   Audit migration is straightforward, but enterprise customers may need a custom connection, table name, UUID model ids, or pruning strategy.

## Area Notes

### Filament v5 Compatibility

The package implements `Filament\Contracts\Plugin` and exposes the requested fluent plugin API. The biggest compatibility concern is not the plugin class itself; it is route and asset integration. A premium Filament plugin should feel panel-native after `->plugin(SpreadsheetEditorPlugin::make())`.

### Laravel Package Structure

The package structure is clean: service provider, config, routes, migrations, views, assets, tests, docs, and demo stubs are present. `spatie/laravel-package-tools` usage is appropriate. The package should still add stronger release checks for generated `dist/` files and broader framework matrices.

### Security Risks

The project already avoids arbitrary model class names from requests, gates fields by configured columns, validates writes server-side, escapes CSV formula-like text, and applies audit redaction. The remaining high-risk areas are queued import identity/tenant context, file token ownership, and panel-aware middleware.

### Public API Ergonomics

The builder API is pleasant and close to Filament conventions. It is currently intentionally narrow. For a paid plugin, customers will soon ask for richer column types, dynamic server rules, per-operation authorization, and adapter-independent display options.

### Test Coverage Gaps

There is meaningful Pest coverage for builders, backend loading, saving, CSV import/export, audit logging, and security behavior. Missing coverage is mostly integration-shaped: real Filament panel guards, panel tenancy middleware, queued import rehydration, JS tests in CI, browser smoke tests, and Laravel/Filament/Livewire version matrices.

### Premium Distribution Readiness

The package is credible for an early private beta. Before marketplace release, it needs panel-native asset/route behavior, licensing hooks, import job hardening, CI matrix coverage, and clear support boundaries.

### Documentation Gaps

README and docs are now broad and customer-friendly. Remaining gaps are mainly operational: non-default guards, multi-panel setup, queued import worker requirements, tenant rehydration limits, asset loading behavior, stale `dist/` troubleshooting, audit pruning, and license integration examples.

### Frontend Build Issues

The Vite build is present and assets are committed. JS tests exist but are not enforced in CI. There is also no automated check that committed `dist/` files match the current source.

### Migration Safety

The audit table migration is simple and publishable. For release, consider table-name configuration, connection configuration, index review for large audit tables, pruning guidance, and explicit `$fillable` on the audit model.

### Multi-Tenancy Risks

Read/write actions call `tenantQuery()` with `Filament::getTenant()`, which is a good start. The main tenant risk is queued work, where the Filament tenant is not naturally available. The second risk is developer omission: tenant-owned editors are safe only if the customer remembers to define `tenantQuery()`.

## Release Readiness Score

Score: 74 / 100

Rationale: The package has a strong skeleton, clear public API, real backend behavior, security-minded tests, audit support, CSV import/export, modern docs, and a working frontend build. It is not yet marketplace-ready because panel-native routing/assets, queued import identity and tenancy, CI matrix depth, JS test enforcement, and licensing extension points still need hardening.
