<?php

use Illuminate\Support\Facades\Route;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\Product;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\User;

function registeredSecurityEditor(): string
{
    return app(SpreadsheetEditorRegistry::class)->define(
        'security-products',
        fn (): SpreadsheetEditor => SpreadsheetEditor::make()
            ->model(Product::class)
            ->columns([
                SpreadsheetColumn::make('sku')->searchable(),
                SpreadsheetColumn::make('name')->searchable()->editable(),
                SpreadsheetColumn::make('price')->number()->editable(),
            ])
            ->tenantQuery(fn ($query, Product $tenant) => $query->where('category', $tenant->category))
            ->authorize(fn (?User $user): bool => $user !== null),
    );
}

it('rejects unregistered editor tokens on every package endpoint', function (): void {
    config()->set('filament-spreadsheet-editor.csv_import_enabled', true);
    config()->set('filament-spreadsheet-editor.csv_export_enabled', true);

    $token = 'not-a-registered-editor';

    $this->actingAs(new User)
        ->getJson(route('filament-spreadsheet-editor.rows.index', ['token' => $token]))
        ->assertNotFound();

    $this->actingAs(new User)
        ->postJson(route('filament-spreadsheet-editor.rows.update', ['token' => $token]), ['changes' => []])
        ->assertNotFound();

    $this->actingAs(new User)
        ->get(route('filament-spreadsheet-editor.csv.export', ['token' => $token]))
        ->assertNotFound();

    $this->actingAs(new User)
        ->post(route('filament-spreadsheet-editor.csv.import.preview', ['token' => $token]))
        ->assertNotFound();

    $this->actingAs(new User)
        ->postJson(route('filament-spreadsheet-editor.csv.import.apply', ['token' => $token]))
        ->assertNotFound();
});

it('keeps package write routes behind web auth middleware for csrf protection', function (): void {
    foreach ([
        'filament-spreadsheet-editor.rows.update',
        'filament-spreadsheet-editor.csv.import.preview',
        'filament-spreadsheet-editor.csv.import.apply',
    ] as $routeName) {
        $middleware = Route::getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];
        $spreadsheetMiddleware = app('router')->getMiddlewareGroups()['filament-spreadsheet-editor'] ?? [];

        expect($middleware)->toContain('filament-spreadsheet-editor')
            ->and($spreadsheetMiddleware)->toContain('web')
            ->and($spreadsheetMiddleware)->toContain('auth');
    }
});

it('applies tenant scoped queries to reads and writes', function (): void {
    $tenantAProduct = Product::query()->create([
        'sku' => 'TENANT-A',
        'name' => 'Tenant A product',
        'price' => 10,
        'stock' => 1,
        'category' => 'tenant-a',
    ]);

    $tenantBProduct = Product::query()->create([
        'sku' => 'TENANT-B',
        'name' => 'Tenant B product',
        'price' => 20,
        'stock' => 1,
        'category' => 'tenant-b',
    ]);

    $token = registeredSecurityEditor();
    app()->instance('filament', new class($tenantAProduct)
    {
        public function __construct(protected Product $tenant)
        {
            //
        }

        public function getTenant(): Product
        {
            return $this->tenant;
        }
    });

    $this
        ->actingAs(new User)
        ->getJson(route('filament-spreadsheet-editor.rows.index', [
            'token' => $token,
        ]))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.sku', 'TENANT-A');

    $this
        ->actingAs(new User)
        ->postJson(route('filament-spreadsheet-editor.rows.update', [
            'token' => $token,
        ]), [
            'changes' => [
                ['id' => $tenantBProduct->id, 'field' => 'name', 'old' => 'Tenant B product', 'value' => 'Cross tenant edit'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', true)
        ->assertJsonPath('results.0.status', 'conflict');

    expect($tenantBProduct->refresh()->name)->toBe('Tenant B product');
});

it('does not accept model class names or unconfigured fields from requests', function (): void {
    $product = Product::query()->create([
        'sku' => 'SEC-001',
        'name' => 'Secure product',
        'price' => 10,
        'stock' => 1,
        'category' => 'tenant-a',
        'internal_cost' => 99,
    ]);

    $this
        ->actingAs(new User)
        ->postJson(route('filament-spreadsheet-editor.rows.update', [
            'token' => registeredSecurityEditor(),
            'tenant' => 'tenant-a',
        ]), [
            'model' => Product::class,
            'changes' => [
                ['id' => $product->id, 'field' => 'internal_cost', 'old' => 99, 'value' => 1],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', true)
        ->assertJsonPath('results.0.status', 'forbidden');

    expect($product->refresh()->internal_cost)->toEqual(99);
});
