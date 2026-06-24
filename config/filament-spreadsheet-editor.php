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

    'csv_import_enabled' => false,
    'csv_export_enabled' => false,
    'audit_enabled' => false,
    'sensitive_fields' => [
        'password',
        'password_confirmation',
        'token',
        'api_token',
        'secret',
        'secret_key',
    ],
    'audit' => [
        'redact_sensitive_fields' => true,
        'redacted_value' => '[redacted]',
    ],
    'max_sync_import_rows' => 1000,
    'import_disk' => env('FILAMENT_SPREADSHEET_EDITOR_IMPORT_DISK', 'local'),

    'routes' => [
        'prefix' => 'filament-spreadsheet-editor',
        'middleware' => ['web', 'auth'],
    ],
];
