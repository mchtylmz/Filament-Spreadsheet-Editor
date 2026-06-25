# Columns

Spreadsheet columns define what the grid can show, search, validate, and edit.

## Basic Column

```php
SpreadsheetColumn::make('name')
    ->label('Product name')
    ->text()
    ->searchable()
    ->editable();
```

Columns are read-only by default. Call `editable()` only for fields that users may update.

## Types

```php
SpreadsheetColumn::make('sku')->text();
SpreadsheetColumn::make('price')->numeric();
SpreadsheetColumn::make('stock')->integer();
SpreadsheetColumn::make('active')->boolean();
SpreadsheetColumn::make('available_on')->date();
```

Supported types map to grid editors and Laravel validation rules.

## Validation

```php
SpreadsheetColumn::make('price')
    ->numeric()
    ->min(0)
    ->editable();

SpreadsheetColumn::make('sku')
    ->required()
    ->unique();
```

You can add arbitrary Laravel rule strings:

```php
SpreadsheetColumn::make('name')
    ->rules(['required', 'max:120'])
    ->editable();
```

Validation runs on the server during save and CSV import. The frontend also uses serialized rules for quick feedback.

## Search and Sort

```php
SpreadsheetColumn::make('name')->searchable();
SpreadsheetColumn::make('category')->sortable(false);
```

Only configured columns can be searched, filtered, sorted, exported, imported, or saved.

## Read-Only Fields

```php
SpreadsheetColumn::make('internal_cost')
    ->numeric()
    ->readOnly();
```

Read-only columns can be visible without being writable.
