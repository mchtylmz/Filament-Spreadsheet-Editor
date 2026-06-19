<?php

use Illuminate\Support\Facades\Route;
use Mivento\FilamentSpreadsheetEditor\Http\Controllers\LoadSpreadsheetRowsController;

Route::middleware(config('filament-spreadsheet-editor.routes.middleware', ['web', 'auth']))
    ->prefix(config('filament-spreadsheet-editor.routes.prefix', 'filament-spreadsheet-editor'))
    ->name('filament-spreadsheet-editor.')
    ->group(function (): void {
        Route::get('editors/{token}/rows', LoadSpreadsheetRowsController::class)
            ->name('rows.index');
    });
