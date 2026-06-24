<?php

use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\Product;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\User;

function registeredCsvExportEditor(bool $authorized = true): string
{
    $editor = SpreadsheetEditor::make()
        ->model(Product::class)
        ->columns([
            SpreadsheetColumn::make('sku')->searchable(),
            SpreadsheetColumn::make('name')->searchable(),
            SpreadsheetColumn::make('price')->number(),
            SpreadsheetColumn::make('stock')->integer(),
            SpreadsheetColumn::make('category'),
        ])
        ->authorize(fn (?User $user): bool => $authorized && $user !== null);

    return app(SpreadsheetEditorRegistry::class)
        ->define('csv-export-'.($authorized ? 'allowed' : 'denied'), fn (): SpreadsheetEditor => $editor);
}

beforeEach(function (): void {
    config()->set('filament-spreadsheet-editor.csv_export_enabled', true);

    Product::query()->create([
        'sku' => 'SKU-001',
        'name' => 'Blue Chair',
        'price' => 25,
        'stock' => 4,
        'category' => 'furniture',
        'internal_cost' => 10,
    ]);
    Product::query()->create([
        'sku' => 'SKU-002',
        'name' => 'Green Lamp',
        'price' => 15,
        'stock' => 9,
        'category' => 'lighting',
        'internal_cost' => 6,
    ]);
});

it('streams visible columns while respecting filters and sorting', function (): void {
    $token = registeredCsvExportEditor();

    $response = $this
        ->actingAs(new User)
        ->get(route('filament-spreadsheet-editor.csv.export', [
            'token' => $token,
            'columns' => ['sku', 'name'],
            'filters' => ['category' => 'lighting'],
            'sort' => ['field' => 'name', 'direction' => 'desc'],
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain('sku,name')
        ->toContain('SKU-002,"Green Lamp"')
        ->not->toContain('SKU-001')
        ->not->toContain('internal_cost');
});

it('exports every configured column when requested', function (): void {
    $token = registeredCsvExportEditor();

    $csv = $this
        ->actingAs(new User)
        ->get(route('filament-spreadsheet-editor.csv.export', [
            'token' => $token,
            'columns' => ['sku'],
            'all_columns' => 1,
        ]))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('sku,name,price,stock,category')
        ->not->toContain('internal_cost');
});

it('requires editor authorization before exporting', function (): void {
    $token = registeredCsvExportEditor(authorized: false);

    $this
        ->actingAs(new User)
        ->get(route('filament-spreadsheet-editor.csv.export', ['token' => $token]))
        ->assertForbidden();
});

it('escapes exported values that could be interpreted as spreadsheet formulas', function (): void {
    Product::query()->where('sku', 'SKU-002')->update(['name' => '=cmd|calc']);

    $csv = $this
        ->actingAs(new User)
        ->get(route('filament-spreadsheet-editor.csv.export', [
            'token' => registeredCsvExportEditor(),
            'columns' => ['sku', 'name'],
            'filters' => ['sku' => 'SKU-002'],
        ]))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain("SKU-002,'=cmd|calc");
});
