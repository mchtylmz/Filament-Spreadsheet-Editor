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
    ->plugin(SpreadsheetEditorPlugin::make());
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
