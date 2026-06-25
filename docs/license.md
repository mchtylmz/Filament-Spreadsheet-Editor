# License Validation

This repository includes a premium-package structure, but the actual marketplace license validation layer is intentionally left as an extension point.

## Recommended Flow

1. Validate the license during application boot or panel registration.
2. Cache successful checks for a short period.
3. Fail closed for premium write workflows when the license is invalid.
4. Keep CI and local development deterministic.
5. Avoid sending sensitive customer data to the license server.

## Example Integration Point

```php
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditorPlugin;

$panel->plugin(
    SpreadsheetEditorPlugin::make()
        ->defaultAdapter('tabulator')
        ->enableCsvImport()
        ->enableCsvExport()
        ->enableAuditLog()
);
```

A commercial wrapper may validate before enabling CSV, audit, or write endpoints.

## Suggested License States

- `active`
- `expired`
- `invalid`
- `trial`
- `development`

## Marketplace Notes

Replace the placeholder license file and `SECURITY.md` contact before publishing. Document your refund, renewal, support, and domain activation rules clearly.
