<?php

use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\Product;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\User;

function registeredSpreadsheetEditor(bool $authorized = true): string
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

    return app(SpreadsheetEditorRegistry::class)->register($editor, $authorized ? 'products' : 'products-denied');
}

function seedSpreadsheetProducts(): void
{
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
        'name' => 'Amber Desk',
        'price' => 90,
        'stock' => 2,
        'category' => 'furniture',
        'internal_cost' => 40,
    ]);

    Product::query()->create([
        'sku' => 'SKU-003',
        'name' => 'Green Lamp',
        'price' => 15,
        'stock' => 9,
        'category' => 'lighting',
        'internal_cost' => 6,
    ]);
}

it('prevents unauthorized users from loading rows', function (): void {
    seedSpreadsheetProducts();

    $token = registeredSpreadsheetEditor(authorized: false);

    $this
        ->actingAs(new User())
        ->getJson(route('filament-spreadsheet-editor.rows.index', ['token' => $token]))
        ->assertForbidden();
});

it('allows authorized users to load paginated rows', function (): void {
    seedSpreadsheetProducts();

    $token = registeredSpreadsheetEditor();

    $this
        ->actingAs(new User())
        ->getJson(route('filament-spreadsheet-editor.rows.index', [
            'token' => $token,
            'per_page' => 2,
        ]))
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonCount(2, 'data');
});

it('searches across configured searchable columns', function (): void {
    seedSpreadsheetProducts();

    $token = registeredSpreadsheetEditor();

    $this
        ->actingAs(new User())
        ->getJson(route('filament-spreadsheet-editor.rows.index', [
            'token' => $token,
            'search' => 'lamp',
        ]))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Green Lamp');
});

it('sorts by configured columns', function (): void {
    seedSpreadsheetProducts();

    $token = registeredSpreadsheetEditor();

    $this
        ->actingAs(new User())
        ->getJson(route('filament-spreadsheet-editor.rows.index', [
            'token' => $token,
            'sort' => [
                'field' => 'price',
                'direction' => 'desc',
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Amber Desk')
        ->assertJsonPath('data.1.name', 'Blue Chair')
        ->assertJsonPath('data.2.name', 'Green Lamp');
});

it('filters by configured columns', function (): void {
    seedSpreadsheetProducts();

    $token = registeredSpreadsheetEditor();

    $this
        ->actingAs(new User())
        ->getJson(route('filament-spreadsheet-editor.rows.index', [
            'token' => $token,
            'filters' => [
                'category' => 'lighting',
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Green Lamp');
});

it('returns only configured columns', function (): void {
    seedSpreadsheetProducts();

    $token = registeredSpreadsheetEditor();

    $response = $this
        ->actingAs(new User())
        ->getJson(route('filament-spreadsheet-editor.rows.index', ['token' => $token]))
        ->assertOk();

    expect(array_keys($response->json('data.0')))->toBe([
        'sku',
        'name',
        'price',
        'stock',
        'category',
    ]);
});
