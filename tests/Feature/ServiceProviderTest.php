<?php

use Mivento\FilamentSpreadsheetEditor\Contracts\GridAdapter;
use Mivento\FilamentSpreadsheetEditor\GridAdapters\TabulatorGridAdapter;

it('resolves the configured grid adapter', function (): void {
    config()->set('filament-spreadsheet-editor.grid.adapter', 'tabulator');

    expect(app(GridAdapter::class))->toBeInstanceOf(TabulatorGridAdapter::class);
});
