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
