<?php

namespace Mivento\FilamentSpreadsheetEditor;

use Illuminate\Support\Facades\Blade;
use Mivento\FilamentSpreadsheetEditor\Contracts\GridAdapter;
use Mivento\FilamentSpreadsheetEditor\GridAdapters\TabulatorGridAdapter;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentSpreadsheetEditorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-spreadsheet-editor')
            ->hasConfigFile()
            ->hasRoute('filament-spreadsheet-editor')
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->scoped(SpreadsheetEditorRegistry::class);

        $this->app->bind(GridAdapter::class, function (): GridAdapter {
            $adapter = config('filament-spreadsheet-editor.grid.adapter', 'tabulator');

            return match ($adapter) {
                'tabulator' => new TabulatorGridAdapter(),
                default => throw new \InvalidArgumentException("Unsupported spreadsheet grid adapter [{$adapter}]."),
            };
        });
    }

    public function packageBooted(): void
    {
        Blade::anonymousComponentPath(
            __DIR__ . '/../resources/views/components',
            'filament-spreadsheet-editor',
        );

        $this->publishes([
            __DIR__ . '/../dist' => public_path('vendor/filament-spreadsheet-editor'),
        ], 'filament-spreadsheet-editor-assets');

        $this->publishes([
            __DIR__ . '/../resources/js' => resource_path('js/vendor/filament-spreadsheet-editor'),
            __DIR__ . '/../resources/css' => resource_path('css/vendor/filament-spreadsheet-editor'),
        ], 'filament-spreadsheet-editor-source');
    }
}
