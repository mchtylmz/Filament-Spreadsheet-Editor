# Changelog

- Add transactional spreadsheet cell audit logging and Filament audit history relation manager.

All notable changes to `mivento/filament-spreadsheet-editor` will be documented in this file.

## 0.1.0 - Unreleased

- Added Laravel 13 support with Testbench 11 and Pest 4 compatibility and PHP 8.3/8.4 CI coverage, retaining Laravel 12 support.
- Removed end-of-life Laravel 11 support, required Laravel 12.69+ / 13.30+, and retained PHP 8.2 coverage with Laravel 12. Added mandatory Composer audits without disabling security blocking or ignoring advisories.
- Fixed invalid Tabulator column definition warnings by keeping spreadsheet metadata out of grid column options (#1).
- Updated installation and persistence examples, demo screenshots, asset refresh instructions, and the Filament page navigation icon type for current Filament 5 compatibility.
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
