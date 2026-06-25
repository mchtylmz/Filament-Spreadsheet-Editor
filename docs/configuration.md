# Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=filament-spreadsheet-editor-config
```

## Grid Adapter

```php
'grid' => [
    'adapter' => env('FILAMENT_SPREADSHEET_EDITOR_GRID_ADAPTER', 'tabulator'),
],
```

Tabulator is the first adapter. Public APIs are kept adapter-neutral so AG Grid can be added later.

## Editing

```php
'editing' => [
    'mode' => 'inline',
    'debounce' => 500,
],
```

The current frontend uses inline cell editing.

## CSV

```php
'csv_import_enabled' => false,
'csv_export_enabled' => false,
'max_sync_import_rows' => 1000,
'import_disk' => 'local',
```

Use plugin methods or config values to enable CSV features. Large imports can be queued by the frontend when the row count exceeds `max_sync_import_rows`.

## Audit

```php
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
```

Audit redaction is enabled by default for fields listed in `sensitive_fields`.

## Routes

```php
'routes' => [
    'prefix' => 'filament-spreadsheet-editor',
    'middleware' => ['web', 'auth'],
],
```

Keep `web` for CSRF protection and `auth` for authenticated Filament users.
