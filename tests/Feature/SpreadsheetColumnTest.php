<?php

use Illuminate\Contracts\Validation\ValidationRule;
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
        'type' => 'number',
        'editable' => true,
        'searchable' => true,
        'sortable' => true,
        'rules' => ['numeric', 'min:0'],
    ]);

    expect($column->toGridColumn())->toMatchArray([
        'field' => 'price',
        'title' => 'Unit Price',
        'type' => 'number',
        'editor' => 'number',
        'editable' => true,
        'validationRules' => ['numeric', 'min:0'],
    ]);
});

it('serializes supported grid column types', function (): void {
    expect(SpreadsheetColumn::make('name')->text()->editable()->toGridColumn())
        ->toMatchArray(['type' => 'text', 'editor' => 'input', 'sorter' => 'string']);

    expect(SpreadsheetColumn::make('stock')->integer()->editable()->toGridColumn())
        ->toMatchArray(['type' => 'integer', 'editor' => 'number', 'sorter' => 'number']);

    expect(SpreadsheetColumn::make('active')->boolean()->editable()->toGridColumn())
        ->toMatchArray([
            'type' => 'boolean',
            'editor' => 'tickCross',
            'sorter' => 'boolean',
            'validationRules' => ['boolean'],
        ]);

    expect(SpreadsheetColumn::make('available_on')->date()->editable()->toGridColumn())
        ->toMatchArray([
            'type' => 'date',
            'editor' => 'date',
            'sorter' => 'date',
            'validationRules' => ['date'],
        ]);
});

it('keeps columns read only until editable is enabled', function (): void {
    $column = SpreadsheetColumn::make('sku')->required()->unique();

    expect($column->isEditable())->toBeFalse()
        ->and($column->toGridColumn()['editor'])->toBeFalse()
        ->and($column->getRules())->toBe(['required', 'unique']);
});

it('replaces the opposite presence rule when required is toggled', function (): void {
    $column = SpreadsheetColumn::make('name')
        ->required()
        ->required(false);

    expect($column->getRules())->toBe(['nullable']);

    $column->required();

    expect($column->getRules())->toBe(['required']);
});

it('keeps non string validation rules server side only', function (): void {
    $rule = new class implements ValidationRule
    {
        public function validate(string $attribute, mixed $value, Closure $fail): void
        {
            //
        }
    };
    $column = SpreadsheetColumn::make('status')
        ->rule('required')
        ->rule($rule);

    expect($column->getRules())->toHaveCount(2)
        ->and($column->serializableRules())->toBe(['required'])
        ->and($column->toGridColumn()['validationRules'])->toBe(['required']);
});
