<?php

use Illuminate\Support\Facades\Event;
use Mivento\FilamentSpreadsheetEditor\Events\SpreadsheetBatchUpdated;
use Mivento\FilamentSpreadsheetEditor\Events\SpreadsheetCellUpdated;
use Mivento\FilamentSpreadsheetEditor\Events\SpreadsheetCellUpdating;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\Product;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\User;

function registeredSaveSpreadsheetEditor(bool $authorized = true): string
{
    $editor = SpreadsheetEditor::make()
        ->model(Product::class)
        ->columns([
            SpreadsheetColumn::make('sku'),
            SpreadsheetColumn::make('name')->editable()->required(),
            SpreadsheetColumn::make('price')->number()->min(0)->editable(),
            SpreadsheetColumn::make('stock')->integer()->min(0)->editable(),
        ])
        ->authorize(fn (?User $user): bool => $authorized && $user !== null);

    return app(SpreadsheetEditorRegistry::class)->register($editor, $authorized ? 'save-products' : 'save-products-denied');
}

function seedSaveProduct(): Product
{
    return Product::query()->create([
        'sku' => 'SKU-SAVE',
        'name' => 'Save Chair',
        'price' => 10,
        'stock' => 4,
        'category' => 'furniture',
    ]);
}

it('saves edited cells in a transaction and dispatches events', function (): void {
    Event::fake();

    $product = seedSaveProduct();
    $token = registeredSaveSpreadsheetEditor();

    $this
        ->actingAs(new User())
        ->postJson(route('filament-spreadsheet-editor.rows.update', ['token' => $token]), [
            'changes' => [
                ['id' => $product->id, 'field' => 'price', 'old' => '10.00', 'value' => '12.50'],
                ['id' => $product->id, 'field' => 'stock', 'old' => 4, 'value' => 5],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', false)
        ->assertJsonPath('results.0.status', 'success')
        ->assertJsonPath('results.1.status', 'success');

    $product->refresh();

    expect($product->price)->toEqual(12.50)
        ->and($product->stock)->toBe(5);

    Event::assertDispatched(SpreadsheetCellUpdating::class, 2);
    Event::assertDispatched(SpreadsheetCellUpdated::class, 2);
    Event::assertDispatched(SpreadsheetBatchUpdated::class);
});

it('returns validation errors and does not commit valid siblings', function (): void {
    $product = seedSaveProduct();
    $token = registeredSaveSpreadsheetEditor();

    $this
        ->actingAs(new User())
        ->postJson(route('filament-spreadsheet-editor.rows.update', ['token' => $token]), [
            'changes' => [
                ['id' => $product->id, 'field' => 'price', 'old' => '10.00', 'value' => -1],
                ['id' => $product->id, 'field' => 'stock', 'old' => 4, 'value' => 5],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', true)
        ->assertJsonPath('results.0.status', 'validation_error')
        ->assertJsonPath('results.1.status', 'success')
        ->assertJsonPath('results.1.committed', false);

    $product->refresh();

    expect($product->price)->toEqual(10)
        ->and($product->stock)->toBe(4);
});

it('forbids saving non editable configured columns', function (): void {
    $product = seedSaveProduct();
    $token = registeredSaveSpreadsheetEditor();

    $this
        ->actingAs(new User())
        ->postJson(route('filament-spreadsheet-editor.rows.update', ['token' => $token]), [
            'changes' => [
                ['id' => $product->id, 'field' => 'sku', 'old' => 'SKU-SAVE', 'value' => 'SKU-HACK'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', true)
        ->assertJsonPath('results.0.status', 'forbidden');

    expect($product->refresh()->sku)->toBe('SKU-SAVE');
});

it('detects optimistic locking conflicts', function (): void {
    $product = seedSaveProduct();
    $token = registeredSaveSpreadsheetEditor();

    $product->forceFill(['price' => 11])->save();

    $this
        ->actingAs(new User())
        ->postJson(route('filament-spreadsheet-editor.rows.update', ['token' => $token]), [
            'changes' => [
                ['id' => $product->id, 'field' => 'price', 'old' => '10.00', 'value' => '12.50'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', true)
        ->assertJsonPath('results.0.status', 'conflict');

    expect($product->refresh()->price)->toEqual(11);
});

it('rolls back the batch when an update event fails', function (): void {
    $product = seedSaveProduct();
    $token = registeredSaveSpreadsheetEditor();

    Event::listen(SpreadsheetCellUpdating::class, function (SpreadsheetCellUpdating $event): void {
        if ($event->field === 'stock') {
            throw new RuntimeException('Stop stock update.');
        }
    });

    $this
        ->actingAs(new User())
        ->postJson(route('filament-spreadsheet-editor.rows.update', ['token' => $token]), [
            'changes' => [
                ['id' => $product->id, 'field' => 'price', 'old' => '10.00', 'value' => '12.50'],
                ['id' => $product->id, 'field' => 'stock', 'old' => 4, 'value' => 5],
            ],
        ])
        ->assertStatus(500);

    $product->refresh();

    expect($product->price)->toEqual(10)
        ->and($product->stock)->toBe(4);
});
