<?php

use Filament\Pages\Page;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\ProductSpreadsheetPage;

it('loads the documented demo page with the current filament page contract', function (): void {
    require_once __DIR__.'/../../demo/app/Filament/Pages/ProductSpreadsheetPage.php';

    $page = App\Filament\Pages\ProductSpreadsheetPage::class;

    expect(is_subclass_of($page, Page::class))->toBeTrue()
        ->and($page::getNavigationIcon())->toBe('heroicon-o-table-cells');
});

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
