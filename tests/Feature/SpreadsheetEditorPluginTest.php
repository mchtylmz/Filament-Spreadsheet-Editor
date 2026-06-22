<?php

use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditorPlugin;

it('configures plugin defaults through a fluent api', function (): void {
    $plugin = SpreadsheetEditorPlugin::make()
        ->defaultAdapter('tabulator')
        ->enableAuditLog()
        ->enableCsvImport()
        ->enableCsvExport();

    expect($plugin->getDefaultAdapter())->toBe('tabulator')
        ->and($plugin->hasAuditLogEnabled())->toBeTrue()
        ->and($plugin->hasCsvImportEnabled())->toBeTrue()
        ->and($plugin->hasCsvExportEnabled())->toBeTrue();
});

it('uses published csv configuration when fluent overrides are omitted', function (): void {
    config()->set('filament-spreadsheet-editor.csv_import_enabled', true);
    config()->set('filament-spreadsheet-editor.csv_export_enabled', true);

    $plugin = SpreadsheetEditorPlugin::make();

    expect($plugin->hasCsvImportEnabled())->toBeTrue()
        ->and($plugin->hasCsvExportEnabled())->toBeTrue();
});
