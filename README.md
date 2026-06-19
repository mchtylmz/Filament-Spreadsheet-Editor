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

The first frontend adapter uses Tabulator. It supports editable cells, selectable rows, clipboard copy/paste through Tabulator, text/number/integer/boolean/date column types, dirty-cell highlighting, and a mock save event. Server persistence is not implemented yet.
