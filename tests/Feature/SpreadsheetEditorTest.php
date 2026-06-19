<?php

use Illuminate\Database\Eloquent\Builder;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\Product;

it('builds a spreadsheet editor definition for an eloquent model', function (): void {
    $editor = SpreadsheetEditor::make()
        ->model(Product::class)
        ->columns([
            SpreadsheetColumn::make('sku')->required()->unique(),
            SpreadsheetColumn::make('name')->searchable()->editable(),
            SpreadsheetColumn::make('price')->numeric()->min(0)->editable(),
            SpreadsheetColumn::make('stock')->integer()->editable(),
        ])
        ->query(fn (Builder $query): Builder => $query->where('active', true))
        ->authorize(fn (object $user): bool => $user->can('manage products'));

    expect($editor->getModel())->toBe(Product::class)
        ->and($editor->editableColumns())->toHaveCount(3)
        ->and($editor->readOnlyColumns())->toHaveCount(1)
        ->and($editor->validationRules())->toBe([
            'sku' => ['required', 'unique:products,sku'],
            'name' => [],
            'price' => ['numeric', 'min:0'],
            'stock' => ['integer'],
        ])
        ->and($editor->toArray())->toMatchArray([
            'model' => Product::class,
            'selectableRows' => true,
            'clipboard' => true,
            'hasQueryCallback' => true,
            'hasAuthorizationCallback' => true,
        ]);

    expect($editor->toFrontendConfig())->toMatchArray([
        'adapter' => 'tabulator',
        'features' => [
            'selectableRows' => true,
            'clipboard' => true,
            'dirtyCells' => true,
            'mockSave' => false,
        ],
    ]);

    expect($editor->toFrontendConfig())
        ->toHaveKey('dataUrl')
        ->toHaveKey('saveUrl');

    $user = new class
    {
        public function can(string $ability): bool
        {
            return $ability === 'manage products';
        }
    };

    expect($editor->isAuthorized($user))->toBeTrue();

    $query = $editor->applyQuery(Product::query());

    expect($query->getQuery()->wheres[0])->toMatchArray([
        'type' => 'Basic',
        'column' => 'active',
        'operator' => '=',
        'value' => true,
    ]);
});

it('rejects non eloquent model classes', function (): void {
    SpreadsheetEditor::make()->model(stdClass::class);
})->throws(InvalidArgumentException::class);

it('rejects invalid column definitions', function (): void {
    SpreadsheetEditor::make()->columns(['sku']);
})->throws(InvalidArgumentException::class);
