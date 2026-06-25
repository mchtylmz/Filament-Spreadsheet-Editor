# Filament Spreadsheet Editor

Premium Excel-like editing for Filament v5 panels, powered by Eloquent, Livewire v4, Alpine.js, Vite, and a grid-adapter architecture that starts with Tabulator and leaves room for AG Grid.

Filament Spreadsheet Editor helps teams edit operational data without leaving their admin panel: products, prices, stock, catalog metadata, tenant-scoped records, approval queues, and any Eloquent-backed dataset that benefits from fast spreadsheet workflows.

## Screenshots

> Replace these placeholders with marketplace-optimized screenshots before publishing.

| Spreadsheet editor | CSV import preview | Audit history |
| --- | --- | --- |
| `demo/screenshots/product-spreadsheet-overview.png` | `demo/screenshots/product-spreadsheet-csv-import.png` | `demo/screenshots/product-spreadsheet-audit-dark.png` |

## Features

- Filament v5 plugin registration with a fluent public API.
- Excel-like Tabulator grid adapter with editable cells, row selection, clipboard copy/paste, dirty-cell styling, undo/redo, save all, discard all, and beforeunload protection.
- Type-aware columns: text, number, integer, boolean, and date.
- Server-side pagination, sorting, searching, and column filters.
- Server-side validation using Laravel-compatible rules defined on `SpreadsheetColumn`.
- Batch updates with optimistic locking, transactions, per-cell results, and conflict recovery.
- CSV export with visible-column export by default and all-configured-column export when requested.
- CSV import with column mapping, first-20-row preview, row-level validation, primary-key or unique-column matching, and optional queue support.
- Audit logging for cell edits with redaction support for sensitive fields.
- Tenant-aware query hook for Filament tenancy.
- Adapter contract for future grid implementations such as AG Grid.
- Pest, PHPStan, Pint, Vite, and GitHub Actions CI included.

## Free vs Pro

| Capability | Free | Pro |
| --- | ---: | ---: |
| Filament plugin registration | Yes | Yes |
| Spreadsheet builder API | Yes | Yes |
| Tabulator frontend adapter | Basic | Advanced |
| Editable Eloquent rows | Limited | Yes |
| Server-side pagination/search/sort/filter | No | Yes |
| Batch save with optimistic locking | No | Yes |
| CSV export | No | Yes |
| CSV import with preview and mapping | No | Yes |
| Audit logging | No | Yes |
| Sensitive audit redaction | No | Yes |
| Tenant-aware query hook | No | Yes |
| Priority support and commercial license | No | Yes |

This repository represents the premium package skeleton and implementation. Adjust the table to match your final marketplace packaging.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Filament v5
- Livewire v4
- Node.js 20+ or 22+ for asset builds

## Installation

```bash
composer require mivento/filament-spreadsheet-editor
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-spreadsheet-editor-config
```

Publish compiled assets:

```bash
php artisan vendor:publish --tag=filament-spreadsheet-editor-assets
```

Publish audit migrations only when audit logging is enabled:

```bash
php artisan vendor:publish --tag=filament-spreadsheet-editor-migrations
php artisan migrate
```

More detail: [docs/installation.md](docs/installation.md)

## Filament v5 PanelProvider Registration

```php
use Filament\Panel;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditorPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            SpreadsheetEditorPlugin::make()
                ->defaultAdapter('tabulator')
                ->enableAuditLog()
                ->enableCsvImport()
                ->enableCsvExport()
        );
}
```

CSV and audit features are disabled by default and can be enabled through the plugin or the published configuration.

## Basic Usage

Create a Filament page:

```bash
php artisan make:filament-page ProductSpreadsheet
```

Render the package component:

```blade
{{-- resources/views/filament/pages/product-spreadsheet.blade.php --}}
<x-filament-panels::page>
    <x-filament-spreadsheet-editor::spreadsheet-editor :editor="$this->editor()" />
</x-filament-panels::page>
```

Define the editor:

```php
namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;

class ProductSpreadsheet extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected string $view = 'filament.pages.product-spreadsheet';

    public function editor(): SpreadsheetEditor
    {
        return SpreadsheetEditor::make()
            ->model(Product::class)
            ->columns([
                SpreadsheetColumn::make('sku')->label('SKU')->text()->required()->unique()->searchable(),
                SpreadsheetColumn::make('name')->text()->required()->max(120)->searchable()->editable(),
                SpreadsheetColumn::make('price')->numeric()->min(0)->editable(),
                SpreadsheetColumn::make('stock')->integer()->min(0)->editable(),
                SpreadsheetColumn::make('active')->boolean()->editable(),
                SpreadsheetColumn::make('available_on')->date()->editable(),
                SpreadsheetColumn::make('internal_cost')->numeric()->readOnly(),
            ])
            ->importUniqueColumn('sku')
            ->query(fn (Builder $query): Builder => $query->where('active', true))
            ->authorize(fn ($user): bool => $user?->can('manage products') === true);
    }
}
```

## Advanced Usage

For backend row loading, saving, import, and export, define named editors during application boot. Named editors are resolved through a server-side registry token so requests never choose arbitrary model classes.

```php
use App\Models\Product;
use Illuminate\Support\ServiceProvider;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;

class AppServiceProvider extends ServiceProvider
{
    public function boot(SpreadsheetEditorRegistry $editors): void
    {
        $editors->define('products', fn () => SpreadsheetEditor::make()
            ->model(Product::class)
            ->columns([
                SpreadsheetColumn::make('sku')->text()->searchable(),
                SpreadsheetColumn::make('name')->text()->searchable()->editable(),
                SpreadsheetColumn::make('price')->numeric()->min(0)->editable(),
                SpreadsheetColumn::make('stock')->integer()->min(0)->editable(),
            ])
            ->importUniqueColumn('sku')
            ->tenantQuery(fn ($query, $tenant) => $query->whereBelongsTo($tenant))
            ->authorize(fn ($user) => $user?->can('manage products') === true));
    }
}
```

Resolve the registered editor in your page:

```php
public function editor(): SpreadsheetEditor
{
    return app(SpreadsheetEditorRegistry::class)->editor('products');
}
```

## Authorization

Every real editor should define an authorization callback:

```php
->authorize(fn ($user): bool => $user?->can('manage products') === true)
```

The callback is checked before row loading, saving, CSV export, and CSV import. Unauthenticated users are blocked by the package middleware group, which is populated from the registered Filament panel by default.

## Validation

Column validation is declared in PHP and enforced server-side during save and CSV import:

```php
SpreadsheetColumn::make('price')
    ->numeric()
    ->min(0)
    ->editable();

SpreadsheetColumn::make('sku')
    ->required()
    ->unique();
```

`unique()` is resolved against the configured Eloquent model table when possible. Server responses include per-cell statuses such as `success`, `validation_error`, `conflict`, and `forbidden`.

More detail: [docs/columns.md](docs/columns.md)

## CSV Import and Export

Enable CSV features:

```php
SpreadsheetEditorPlugin::make()
    ->enableCsvImport()
    ->enableCsvExport();
```

Export respects configured columns, current filters, search, and sort. Visible columns are exported by default; users can request all configured columns. Imports support upload, mapping, preview, validation, and apply-by-primary-key or apply-by-unique-column workflows.

CSV values beginning with `=`, `+`, `-`, or `@` are escaped to reduce spreadsheet formula injection risk.

More detail: [docs/import-export.md](docs/import-export.md)

## Audit Logging

Enable audit logging:

```php
SpreadsheetEditorPlugin::make()->enableAuditLog();
```

Publish and run the audit migration:

```bash
php artisan vendor:publish --tag=filament-spreadsheet-editor-migrations
php artisan migrate
```

Add the relationship trait to audited models:

```php
use Mivento\FilamentSpreadsheetEditor\Concerns\HasSpreadsheetCellAudits;

class Product extends Model
{
    use HasSpreadsheetCellAudits;
}
```

Sensitive fields are redacted by default:

```php
'sensitive_fields' => ['password', 'api_token', 'secret_key'],
'audit' => [
    'redact_sensitive_fields' => true,
    'redacted_value' => '[redacted]',
],
```

More detail: [docs/audit-log.md](docs/audit-log.md)

## License Validation Extension

This package is prepared for premium distribution. Add your marketplace or license-server integration around package boot or panel registration:

```php
SpreadsheetEditorPlugin::make()
    ->defaultAdapter('tabulator');
```

Recommended extension points:

- validate the license in your application service provider before registering premium editors
- cache successful license checks
- fail closed for write/import/export operations when the license is invalid
- keep local development and CI license behavior deterministic

More detail: [docs/license.md](docs/license.md)

## Configuration

Key options in `config/filament-spreadsheet-editor.php`:

```php
'grid' => [
    'adapter' => 'tabulator',
],
'csv_import_enabled' => false,
'csv_export_enabled' => false,
'audit_enabled' => false,
'max_sync_import_rows' => 1000,
'import_disk' => 'local',
'import_ttl_minutes' => 60,
'routes' => [
    'prefix' => 'filament-spreadsheet-editor',
    'middleware' => ['filament-spreadsheet-editor'],
    'use_panel_middleware' => true,
],
```

More detail: [docs/configuration.md](docs/configuration.md)

## Troubleshooting

**The grid renders but does not load backend rows.**
Use a named editor from `SpreadsheetEditorRegistry`. Raw editor instances run in local/mock mode and do not expose backend URLs.

**POST requests fail with 419.**
Keep the default `filament-spreadsheet-editor` middleware group or include `web` in your custom middleware. The bundled JavaScript reads the CSRF token from standard Laravel page metadata.

**CSV import updates no rows.**
Check the mapping, `match_by`, and `->importUniqueColumn('sku')` configuration.

**Audit rows are empty or not created.**
Enable audit logging, publish and run migrations, and ensure the batch actually commits.

**Tenant records leak across panels.**
Add `tenantQuery()` to every editor whose model belongs to a tenant. For fail-closed tenant-owned editors, add `->requiresTenant()` so requests without a Filament tenant context are rejected.

## Upgrade Notes

- Keep PHP, Laravel, Filament, and Livewire versions aligned with the requirements above.
- Re-publish assets after upgrading frontend code.
- Review `config/filament-spreadsheet-editor.php` for new security or feature flags.
- Run `composer ci` locally before upgrading production apps.
- Watch release notes for adapter-level changes when AG Grid support is introduced.

## Roadmap

- AG Grid adapter implementation.
- Column grouping and frozen columns.
- Bulk fill and formula-like helper actions.
- Saved grid views per user.
- Import templates and downloadable CSV examples.
- Optional queued export jobs for very large datasets.
- Marketplace license validation package.

## FAQ

**Does this replace Filament tables?**
No. It complements Filament tables when users need spreadsheet-style inline editing and batch save workflows.

**Can I use it with any Eloquent model?**
Yes, as long as the editor is configured server-side and only safe columns are exposed.

**Are columns editable by default?**
No. Columns are read-only until you call `editable()`.

**Can users write hidden or unconfigured fields?**
No. Save and import endpoints only accept configured editable columns.

**Does it support tenancy?**
Yes. Add `tenantQuery()` so all reads and writes are scoped to the active Filament tenant.

**Is AG Grid supported today?**
Not yet. The adapter contract exists so AG Grid can be added without changing the public editor API.

## Documentation

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Columns](docs/columns.md)
- [Import and Export](docs/import-export.md)
- [Audit Log](docs/audit-log.md)
- [Security](docs/security.md)
- [License](docs/license.md)

## Development

```bash
composer install
npm ci
composer ci
```

Individual commands:

```bash
composer test
composer analyse
composer format
composer build
npm test
```

## License

This package currently uses the MIT license placeholder in this repository. Replace it with your commercial license before marketplace distribution.
