<?php

use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\ProductSpreadsheetPage;

it('exposes a complete demo spreadsheet configuration', function (): void {
    $editor = app(ProductSpreadsheetPage::class)->editor();
    $columns = collect($editor->gridColumns())->keyBy('field');

    expect($columns)->toHaveKeys([
        'sku',
        'name',
        'price',
        'stock',
        'active',
        'available_on',
        'internal_cost',
    ])
        ->and($columns['sku']['type'])->toBe('text')
        ->and($columns['price']['type'])->toBe('number')
        ->and($columns['active']['type'])->toBe('boolean')
        ->and($columns['available_on']['type'])->toBe('date')
        ->and($columns['internal_cost']['editable'])->toBeFalse()
        ->and($editor->validationRules())->toMatchArray([
            'sku' => ['required', 'unique:products,sku'],
            'name' => ['required', 'max:120'],
            'price' => ['numeric', 'min:0'],
            'stock' => ['integer', 'min:0'],
            'active' => ['boolean'],
            'available_on' => ['date'],
            'internal_cost' => ['numeric'],
        ])
        ->and($editor->getImportUniqueColumn())->toBe('sku');
});
