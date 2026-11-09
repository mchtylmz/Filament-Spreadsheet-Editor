# Installation

Filament Spreadsheet Editor is distributed as a Laravel package for Filament v5 panels.

## Requirements

- PHP 8.2+ (PHP 8.3+ for Laravel 13)
- Laravel 12.69+ or 13.30+
- Filament v5
- Livewire v4
- Node.js 20.19+ or 22.12+ when building package assets

The current compatibility check uses Laravel 13.40.0, Filament 5.11.2, Livewire 4.4.8, and PHP 8.4. The package's Filament 5 and Livewire 4 constraints already accept these releases.

Laravel 11 is no longer supported. Upgrade existing Laravel 11 applications before installing this revision. Dependency security checks remain enabled, and CI audits the installed dependency set on every supported PHP/Laravel combination.

## Install the Package

```bash
composer require mchtylmz/filament-spreadsheet-editor
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
php artisan filament:assets
```

Run this again after package upgrades. If your application separately serves the published `public/vendor/filament-spreadsheet-editor` copies, refresh those with `php artisan vendor:publish --tag=filament-spreadsheet-editor-assets --force` as well.

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

Register a named editor with `SpreadsheetEditorRegistry::define()` during application boot, then return `app(SpreadsheetEditorRegistry::class)->editor('products')` from the page's `editor()` method. See the complete [Basic Usage example](../README.md#basic-usage). Unregistered `SpreadsheetEditor::make()` instances use local/demo data and do not expose backend load/save endpoints.
