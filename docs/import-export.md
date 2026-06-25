# CSV Import and Export

CSV features are premium workflows intended for operational data management.

## Enable Features

```php
SpreadsheetEditorPlugin::make()
    ->enableCsvImport()
    ->enableCsvExport();
```

Or configure:

```php
'csv_import_enabled' => true,
'csv_export_enabled' => true,
```

## Export

Export endpoint:

```text
GET /filament-spreadsheet-editor/editors/{token}/csv/export
```

Exports are authorized, tenant-aware, and based on the registered editor. The request cannot select arbitrary model classes.

By default, the frontend sends visible columns. Passing `all_columns=1` exports every configured column. Search, filters, and sort are respected.

Values beginning with `=`, `+`, `-`, or `@` are escaped before being written to CSV.

## Import

Import uses a two-step flow:

```text
POST /filament-spreadsheet-editor/editors/{token}/csv/import/preview
POST /filament-spreadsheet-editor/editors/{token}/csv/import/apply
```

Preview returns:

- headers
- suggested mapping
- first 20 rows
- total row count
- import token
- available configured columns

Apply payload:

```json
{
  "import_token": "generated-token",
  "mapping": {
    "sku": "sku",
    "Product Name": "name"
  },
  "match_by": "unique",
  "queue": false
}
```

`match_by` may be `primary` or `unique`. Unique matching requires:

```php
->importUniqueColumn('sku')
```

All rows are validated before updates are applied. Row-level errors include the CSV line number and field name.

## Queued Imports

When row count exceeds `max_sync_import_rows`, the frontend can request queued processing by sending `"queue": true`.
