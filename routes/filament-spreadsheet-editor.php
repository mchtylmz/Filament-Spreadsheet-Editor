<?php

use Illuminate\Support\Facades\Route;
use Mivento\FilamentSpreadsheetEditor\Http\Controllers\ApplySpreadsheetCsvImportController;
use Mivento\FilamentSpreadsheetEditor\Http\Controllers\ExportSpreadsheetCsvController;
use Mivento\FilamentSpreadsheetEditor\Http\Controllers\LoadSpreadsheetRowsController;
use Mivento\FilamentSpreadsheetEditor\Http\Controllers\PreviewSpreadsheetCsvImportController;
use Mivento\FilamentSpreadsheetEditor\Http\Controllers\SaveSpreadsheetRowsController;

Route::middleware(config('filament-spreadsheet-editor.routes.middleware', ['filament-spreadsheet-editor']))
    ->prefix(config('filament-spreadsheet-editor.routes.prefix', 'filament-spreadsheet-editor'))
    ->name('filament-spreadsheet-editor.')
    ->group(function (): void {
        Route::get('editors/{token}/rows', LoadSpreadsheetRowsController::class)
            ->name('rows.index');

        Route::post('editors/{token}/rows', SaveSpreadsheetRowsController::class)
            ->name('rows.update');

        Route::get('editors/{token}/csv/export', ExportSpreadsheetCsvController::class)
            ->name('csv.export');

        Route::post('editors/{token}/csv/import/preview', PreviewSpreadsheetCsvImportController::class)
            ->name('csv.import.preview');

        Route::post('editors/{token}/csv/import/apply', ApplySpreadsheetCsvImportController::class)
            ->name('csv.import.apply');
    });
