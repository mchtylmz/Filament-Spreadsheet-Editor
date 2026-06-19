<?php

use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;

it('serializes editable column configuration and validation rules', function (): void {
    $column = SpreadsheetColumn::make('price')
        ->label('Unit Price')
        ->numeric()
        ->min(0)
        ->editable()
        ->searchable();

    expect($column->toArray())->toMatchArray([
        'name' => 'price',
        'label' => 'Unit Price',
        'editable' => true,
        'searchable' => true,
        'sortable' => true,
        'rules' => ['numeric', 'min:0'],
    ]);

    expect($column->toGridColumn())->toMatchArray([
        'field' => 'price',
        'title' => 'Unit Price',
        'editor' => 'input',
        'editable' => true,
        'validationRules' => ['numeric', 'min:0'],
    ]);
});

it('keeps columns read only until editable is enabled', function (): void {
    $column = SpreadsheetColumn::make('sku')->required()->unique();

    expect($column->isEditable())->toBeFalse()
        ->and($column->toGridColumn()['editor'])->toBeFalse()
        ->and($column->getRules())->toBe(['required', 'unique']);
});
