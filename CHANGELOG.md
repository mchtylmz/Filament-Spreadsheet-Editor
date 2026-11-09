# Changelog

All notable changes to `mchtylmz/filament-spreadsheet-editor` will be documented in this file.

## 0.2.0 - 2026-11-09

- Added Laravel 13 support with Testbench 11 and Pest 4 compatibility and PHP 8.3/8.4 CI coverage, retaining Laravel 12 support.
- Removed end-of-life Laravel 11 support, required Laravel 12.69+ / 13.30+, and retained PHP 8.2 coverage with Laravel 12. Added mandatory Composer audits without disabling security blocking or ignoring advisories.
- Fixed invalid Tabulator column definition warnings by keeping spreadsheet metadata out of grid column options (#1).
- Updated installation and persistence examples, demo screenshots, asset refresh instructions, and the Filament page navigation icon type for current Filament 5 compatibility.

### Upgrading

- Laravel 11 applications must upgrade to Laravel 12.69+ or 13.30+ before installing this release. Laravel 13 requires PHP 8.3+; PHP 8.2 remains supported with Laravel 12.
- Run `php artisan filament:assets` after updating, then refresh the browser cache to load the fixed JavaScript.
- No new database migrations are required for this release.

### Validation

- All five GitHub Actions combinations pass: Laravel 12 on PHP 8.2/8.3/8.4 and Laravel 13 on PHP 8.3/8.4.
- PHP and JavaScript tests, Composer audits, Pint, PHPStan, asset builds, and compiled asset consistency checks pass.

## 0.1 - 2026-06-25

- Added transactional spreadsheet cell audit logging and the Filament audit history relation manager.
- Initial package skeleton.
- Added Filament plugin registration class.
- Added service provider with config, views, assets, and grid adapter binding.
- Added Tabulator as the first grid adapter.
- Added public spreadsheet editor and column builder APIs.
- Added plugin feature toggles for audit log, CSV import, and CSV export.
- Added the first Tabulator frontend adapter with Vite assets and a Blade component.
- Added authenticated backend row loading with registry tokens, pagination, search, sorting, filters, and tenant query hooks.
- Added batch cell saving with validation, optimistic locking, transactions, events, and frontend per-cell error rendering.
- Restored mock frontend save events for editor configurations without a persistence endpoint.
- Added request-safe named editor resolvers and request-scoped registry binding for backend row loading.
- Hardened batch saving with locked optimistic checks, stable row identifiers, ordered results, and persistent frontend error states.
- Added undo/redo, pending changes, save/discard actions, conflict recovery, keyboard shortcuts, and light/dark editing states.
- Added streamed CSV export plus mapped, previewed, validated, synchronous or queued CSV imports.
