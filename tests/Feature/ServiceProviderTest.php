<?php

use Mivento\FilamentSpreadsheetEditor\Contracts\GridAdapter;
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
