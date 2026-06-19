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

Frontend entry points live in `resources/js/index.js` and `resources/css/index.css`. Host applications should include or bundle the published assets with their Vite setup until a dedicated Filament asset pipeline integration is implemented.
