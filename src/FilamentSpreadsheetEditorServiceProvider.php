<?php

namespace Mivento\FilamentSpreadsheetEditor;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Routing\Router;
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
            ->hasMigration('create_spreadsheet_cell_audits_table')
            ->hasRoute('filament-spreadsheet-editor')
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->scoped(SpreadsheetEditorRegistry::class);

        $this->app->afterResolving(Router::class, function (Router $router): void {
            $router->middlewareGroup(
                'filament-spreadsheet-editor',
                config('filament-spreadsheet-editor.routes.base_middleware', ['web', 'auth']),
            );
        });

        $this->app->bind(GridAdapter::class, function (): GridAdapter {
            $adapter = config('filament-spreadsheet-editor.grid.adapter', 'tabulator');

            return match ($adapter) {
                'tabulator' => new TabulatorGridAdapter,
                default => throw new \InvalidArgumentException("Unsupported spreadsheet grid adapter [{$adapter}]."),
            };
        });
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Js::make('spreadsheet-editor', __DIR__.'/../dist/spreadsheet-editor.js')->module(),
            Css::make('spreadsheet-editor-style', __DIR__.'/../dist/spreadsheet-editor-style.css'),
        ], 'mivento/filament-spreadsheet-editor');

        Blade::anonymousComponentPath(
            __DIR__.'/../resources/views/components',
            'filament-spreadsheet-editor',
        );

        $this->publishes([
            __DIR__.'/../dist' => public_path('vendor/filament-spreadsheet-editor'),
        ], 'filament-spreadsheet-editor-assets');

        $this->publishes([
            __DIR__.'/../resources/js' => resource_path('js/vendor/filament-spreadsheet-editor'),
            __DIR__.'/../resources/css' => resource_path('css/vendor/filament-spreadsheet-editor'),
        ], 'filament-spreadsheet-editor-source');
    }
}
