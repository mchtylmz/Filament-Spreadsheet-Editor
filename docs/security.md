# Security

Filament Spreadsheet Editor is designed around server-side editor definitions.

## Registered Editors

Backend endpoints resolve editors through `SpreadsheetEditorRegistry`. Requests receive a token for a configured editor; they never provide arbitrary model class names.

## Authorization

Always define an authorization callback:

```php
->authorize(fn ($user): bool => $user?->can('manage products') === true)
```

Authorization is checked before:

- row loading
- saving
- CSV export
- CSV import preview
- CSV import apply

## Configured Columns Only

Only configured columns can be returned or changed. Writes also require the column to be marked `editable()`.

## CSRF and Middleware

Default route middleware:

```php
'middleware' => ['web', 'auth'],
```

Keep `web` enabled for CSRF protection. Keep `auth` enabled for authenticated Filament panel users.

## Tenant Scoping

Use `tenantQuery()` for tenant-owned models:

```php
->tenantQuery(fn ($query, $tenant) => $query->whereBelongsTo($tenant))
```

The callback applies to row loading, saving, CSV export, CSV import lookup, and optimistic-lock checks.

## CSV Formula Injection

CSV import and export escape values starting with:

- `=`
- `+`
- `-`
- `@`

This reduces the risk that spreadsheet applications evaluate stored user content as formulas.

## Audit Redaction

Use `sensitive_fields` to prevent raw sensitive values from being stored in audit rows.

## Responsible Disclosure

See [../SECURITY.md](../SECURITY.md).
