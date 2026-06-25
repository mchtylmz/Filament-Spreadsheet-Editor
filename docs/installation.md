# Installation

Filament Spreadsheet Editor is distributed as a Laravel package for Filament v5 panels.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Filament v5
- Livewire v4
- Node.js 20+ or 22+ when building package assets

## Install the Package

```bash
composer require mivento/filament-spreadsheet-editor
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=filament-spreadsheet-editor-config
```

## Register the Filament Plugin

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

## Publish Assets

```bash
php artisan vendor:publish --tag=filament-spreadsheet-editor-assets
```

When developing the package itself:

```bash
npm ci
npm run build
```

## Optional Audit Migration

Audit logging requires a package table:

```bash
php artisan vendor:publish --tag=filament-spreadsheet-editor-migrations
php artisan migrate
```

## Verify Installation

Create a Filament page and render:

```blade
<x-filament-spreadsheet-editor::spreadsheet-editor :editor="$this->editor()" />
```

Then define `editor()` on the page class using `SpreadsheetEditor::make()`.
