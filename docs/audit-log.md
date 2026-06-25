# Audit Log

Audit logging records committed cell edits.

## Enable Audit Logging

```php
SpreadsheetEditorPlugin::make()->enableAuditLog();
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag=filament-spreadsheet-editor-migrations
php artisan migrate
```

## Audit Table

The package stores:

- user ID
- model type and model ID
- field
- old and new values
- batch UUID
- IP address
- user agent
- created timestamp

Failed or rolled-back batches do not create audit rows.

## Model Trait

```php
use Mivento\FilamentSpreadsheetEditor\Concerns\HasSpreadsheetCellAudits;

class Product extends Model
{
    use HasSpreadsheetCellAudits;
}
```

## Filament Relation Manager

```php
use Mivento\FilamentSpreadsheetEditor\Filament\RelationManagers\SpreadsheetCellAuditsRelationManager;

public static function getRelations(): array
{
    return [
        SpreadsheetCellAuditsRelationManager::class,
    ];
}
```

## Sensitive Fields

```php
'sensitive_fields' => ['password', 'api_token', 'secret_key'],
'audit' => [
    'redact_sensitive_fields' => true,
    'redacted_value' => '[redacted]',
],
```

Disable redaction only when your compliance process explicitly allows raw sensitive values in audit storage.
