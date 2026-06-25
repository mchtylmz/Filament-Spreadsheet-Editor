<?php

use Illuminate\Database\Eloquent\Builder;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\Product;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

    $serializedFrontendConfig = $editor->toFrontendConfig();

    expect($serializedFrontendConfig['adapter'])->toBe('tabulator')
        ->and($serializedFrontendConfig['features']['selectableRows'])->toBeTrue()
        ->and($serializedFrontendConfig['features']['clipboard'])->toBeTrue()
        ->and($serializedFrontendConfig['features']['dirtyCells'])->toBeTrue()
        ->and($serializedFrontendConfig['features']['mockSave'])->toBeTrue();

    $frontendConfig = $editor->toFrontendConfig();

    expect(array_key_exists('dataUrl', $frontendConfig))->toBeFalse()
        ->and(array_key_exists('saveUrl', $frontendConfig))->toBeFalse();

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

it('can require a tenant context before applying tenant scoped queries', function (): void {
    $editor = SpreadsheetEditor::make()
        ->model(Product::class)
        ->columns([SpreadsheetColumn::make('name')])
        ->requiresTenant()
        ->tenantQuery(fn (Builder $query, Product $tenant): Builder => $query->where('category', $tenant->category));

    expect($editor->requiresTenantContext())->toBeTrue()
        ->and($editor->toArray())->toMatchArray(['requiresTenant' => true]);

    $editor->applyTenantQuery(Product::query(), null);
})->throws(HttpException::class);
