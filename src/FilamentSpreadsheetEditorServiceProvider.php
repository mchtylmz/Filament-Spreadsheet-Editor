<?php

namespace Mivento\FilamentSpreadsheetEditor;

use Mivento\FilamentSpreadsheetEditor\Contracts\GridAdapter;
use Mivento\FilamentSpreadsheetEditor\GridAdapters\TabulatorGridAdapter;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentSpreadsheetEditorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-spreadsheet-editor')
            ->hasConfigFile()
            ->hasViews();
    }

    public function packageRegistered(): void
    {
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
        $this->publishes([
            __DIR__ . '/../resources/js' => resource_path('js/vendor/filament-spreadsheet-editor'),
            __DIR__ . '/../resources/css' => resource_path('css/vendor/filament-spreadsheet-editor'),
        ], 'filament-spreadsheet-editor-assets');
    }
}
