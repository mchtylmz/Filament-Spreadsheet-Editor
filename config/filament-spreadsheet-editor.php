<?php

use Mivento\FilamentSpreadsheetEditor\Enums\EditMode;

return [
    'grid' => [
        'adapter' => env('FILAMENT_SPREADSHEET_EDITOR_GRID_ADAPTER', 'tabulator'),
    ],

    'editing' => [
        'mode' => EditMode::Inline->value,
        'debounce' => 500,
    ],

    'assets' => [
        'load_tabulator_from_cdn' => false,
    ],

    'routes' => [
        'prefix' => 'filament-spreadsheet-editor',
        'middleware' => ['web', 'auth'],
    ],
];
