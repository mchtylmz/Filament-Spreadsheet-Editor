<?php

use Filament\Panel;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Mivento\FilamentSpreadsheetEditor\Contracts\GridAdapter;
use Mivento\FilamentSpreadsheetEditor\FilamentSpreadsheetEditorServiceProvider;
use Mivento\FilamentSpreadsheetEditor\GridAdapters\TabulatorGridAdapter;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditorPlugin;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\Product;

it('resolves the configured grid adapter', function (): void {
    config()->set('filament-spreadsheet-editor.grid.adapter', 'tabulator');

    expect(app(GridAdapter::class))->toBeInstanceOf(TabulatorGridAdapter::class);
});

it('shares one spreadsheet editor registry within the request', function (): void {
    expect(app(SpreadsheetEditorRegistry::class))
        ->toBe(app(SpreadsheetEditorRegistry::class));
});

it('registers the audit migration for publishing', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        FilamentSpreadsheetEditorServiceProvider::class,
        'filament-spreadsheet-editor-migrations',
    );

    expect(collect(array_keys($paths))->contains(
        fn (string $path): bool => str_ends_with(
            $path,
            '/database/migrations/create_spreadsheet_cell_audits_table.php.stub',
        ),
    ))->toBeTrue();
});

it('registers built assets through the filament asset pipeline', function (): void {
    $scripts = FilamentAsset::getScripts(['mivento/filament-spreadsheet-editor']);
    $styles = FilamentAsset::getStyles(['mivento/filament-spreadsheet-editor']);

    expect(collect($scripts)->map->getId())->toContain('spreadsheet-editor')
        ->and(collect($styles)->map->getId())->toContain('spreadsheet-editor-style');
});

it('uses panel middleware for package routes when the plugin is registered', function (): void {
    $panel = Panel::make()
        ->id('admin')
        ->middleware(['panel-base'])
        ->authMiddleware(['panel-auth'])
        ->tenant(Product::class)
        ->tenantMiddleware(['panel-tenant']);

    SpreadsheetEditorPlugin::make()->register($panel);

    $middleware = app('router')->getMiddlewareGroups()['filament-spreadsheet-editor'] ?? [];

    expect($middleware)->toContain('panel:admin')
        ->and($middleware)->toContain('panel-base')
        ->and($middleware)->toContain('panel-auth')
        ->and($middleware)->toContain('panel-tenant');
});
