<?php

use Illuminate\Support\ServiceProvider;
use Mivento\FilamentSpreadsheetEditor\Contracts\GridAdapter;
use Mivento\FilamentSpreadsheetEditor\FilamentSpreadsheetEditorServiceProvider;
use Mivento\FilamentSpreadsheetEditor\GridAdapters\TabulatorGridAdapter;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;

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
