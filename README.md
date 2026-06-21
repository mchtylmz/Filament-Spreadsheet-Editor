# Filament Spreadsheet Editor

Premium Filament v5 plugin skeleton for building Excel-like editors for Eloquent models.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Filament v5
- Livewire v4

## Installation

```bash
composer require mivento/filament-spreadsheet-editor
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-spreadsheet-editor-config
```

Publish frontend assets:

```bash
php artisan vendor:publish --tag=filament-spreadsheet-editor-assets
```

Register the plugin on a Filament panel:

```php
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditorPlugin;

$panel
    ->plugin(
        SpreadsheetEditorPlugin::make()
            ->defaultAdapter('tabulator')
            ->enableAuditLog()
            ->enableCsvImport()
            ->enableCsvExport()
    );
```

## Editor API

Define spreadsheet editors with a small builder API. This package does not ship the full UI yet; the builder serializes model, column, validation, grid, query, and authorization metadata for the future Livewire/Filament layer.

```php
use App\Models\Product;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;

SpreadsheetEditor::make()
    ->model(Product::class)
    ->columns([
        SpreadsheetColumn::make('sku')->required()->unique(),
        SpreadsheetColumn::make('name')->searchable()->editable(),
        SpreadsheetColumn::make('price')->numeric()->min(0)->editable(),
        SpreadsheetColumn::make('stock')->integer()->editable(),
    ])
    ->query(fn ($query) => $query->where('active', true))
    ->authorize(fn ($user) => $user->can('manage products'));
```

Columns are read-only by default. Calling `editable()` marks the column as editable in the serialized grid configuration. Validation rules are stored as Laravel-compatible strings. A bare `unique()` rule is resolved from the configured model when the editor serializes validation rules:

```php
SpreadsheetColumn::make('price')
    ->numeric()
    ->min(0)
    ->editable()
    ->toGridColumn();
```

## Grid Adapters

The first supported adapter is Tabulator. The PHP side depends on the `GridAdapter` contract so an AG Grid adapter can be added later without changing the public plugin entry point.

## Development

```bash
composer install
composer test
composer analyse
composer format
```

Frontend entry points live in `resources/js/spreadsheet-editor.js` and `resources/css/spreadsheet-editor.css`. Host applications should include or bundle the published assets with their Vite setup until a dedicated Filament asset pipeline integration is implemented.

## Filament Custom Page Usage

Build an editor definition in your Filament page class:

```php
use App\Models\Product;
use Filament\Pages\Page;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;

class ManageProductsSpreadsheet extends Page
{
    protected string $view = 'filament.pages.manage-products-spreadsheet';

    public function editor(): SpreadsheetEditor
    {
        return SpreadsheetEditor::make()
            ->model(Product::class)
            ->columns([
                SpreadsheetColumn::make('sku')->required()->unique(),
                SpreadsheetColumn::make('name')->text()->searchable()->editable(),
                SpreadsheetColumn::make('price')->number()->min(0)->editable(),
                SpreadsheetColumn::make('stock')->integer()->editable(),
                SpreadsheetColumn::make('active')->boolean()->editable(),
                SpreadsheetColumn::make('available_on')->date()->editable(),
            ])
            ->query(fn ($query) => $query->where('active', true))
            ->authorize(fn ($user) => $user->can('manage products'));
    }
}
```

Render the Blade component from the page view:

```blade
<x-filament-spreadsheet-editor::spreadsheet-editor :editor="$this->editor()" />
```

Include the built assets from your host app while the package asset pipeline is still intentionally lightweight:

```blade
@vite([
    'vendor/mivento/filament-spreadsheet-editor/resources/js/spreadsheet-editor.js',
    'vendor/mivento/filament-spreadsheet-editor/resources/css/spreadsheet-editor.css',
])
```

Or publish the compiled package assets after running the package build:

```bash
npm install
npm run build
php artisan vendor:publish --tag=filament-spreadsheet-editor-assets
```

The first frontend adapter uses Tabulator. It supports editable cells, selectable rows, clipboard copy/paste through Tabulator, text/number/integer/boolean/date column types, dirty-cell highlighting, and batch save events.

When the component receives a raw configuration without a `saveUrl`, saving is mocked in the browser: dirty cells are cleared and `filament-spreadsheet-editor:saving` followed by `filament-spreadsheet-editor:saved` is dispatched. Named editors resolved through `SpreadsheetEditorRegistry` include the backend `saveUrl` and use the persistence endpoint instead.

## Backend Data Loading

The package registers an authenticated JSON endpoint:

```text
GET /filament-spreadsheet-editor/editors/{token}/rows
```

Spreadsheet editors are loaded through a server-side registry token. The request never chooses a model class directly. Define named editors during application boot so their callbacks can be rebuilt safely for every HTTP request:

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
                SpreadsheetColumn::make('sku')->searchable(),
                SpreadsheetColumn::make('name')->searchable()->editable(),
                SpreadsheetColumn::make('price')->number()->editable(),
                SpreadsheetColumn::make('stock')->integer()->editable(),
            ])
            ->query(fn ($query) => $query->where('active', true))
            ->tenantQuery(fn ($query, $tenant) => $query->whereBelongsTo($tenant))
            ->authorize(fn ($user) => $user->can('manage products')));
    }
}
```

Resolve that editor in the Filament page:

```php
public function editor(): SpreadsheetEditor
{
    return app(SpreadsheetEditorRegistry::class)->editor('products');
}
```

The endpoint supports:

- `page` and `per_page`
- `search`
- `sort[field]` and `sort[direction]`
- `filters[column_name]=value`

The Blade component reuses the named editor token and includes `dataUrl` and `saveUrl` in the frontend config automatically. Editors that were not resolved from a named registry definition stay in local/mock mode and do not expose temporary backend URLs:

```blade
<x-filament-spreadsheet-editor::spreadsheet-editor :editor="$this->editor()" />
```

Only configured columns are searchable, sortable, filterable, and returned in row payloads.
Model keys are transported separately in the `row_ids` response field so the frontend can build save payloads without exposing unconfigured model attributes.

## Saving Changes

Edited cells are posted back to the registered editor token:

```text
POST /filament-spreadsheet-editor/editors/{token}/rows
```

Payload:

```json
{
  "changes": [
    {"id": 1, "field": "price", "old": "10.00", "value": "12.50"},
    {"id": 1, "field": "stock", "old": 4, "value": 5}
  ]
}
```

The save action validates that each field is editable, applies Laravel validation rules from `SpreadsheetColumn`, and checks optimistic locking against the `old` value again under a database row lock. Batches are atomic: if one cell fails validation or conflicts, valid sibling cells return `success` with `committed: false` and no writes are persisted.

- `success`
- `validation_error`
- `conflict`
- `forbidden`

The package dispatches `SpreadsheetCellUpdating`, `SpreadsheetCellUpdated`, and `SpreadsheetBatchUpdated` events during committed saves. The frontend preserves the first `old` value across repeated edits and renders validation/conflict details on the affected cell.

## Editing Experience

The Tabulator editor includes:

- undo and redo history
- a pending changes panel with dirty row count
- save all and discard all actions
- per-cell validation and conflict messages
- conflict recovery using the server's current cell value
- unsaved-change protection before leaving the page
- `Ctrl/Cmd+S` to save, `Ctrl/Cmd+Z` to undo, and `Ctrl/Cmd+Shift+Z` to redo

Toolbar, grid, dirty, validation, and conflict states adapt to Filament light and dark modes.
