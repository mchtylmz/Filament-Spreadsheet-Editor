<?php

namespace Mivento\FilamentSpreadsheetEditor\Tests\Fixtures;

use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;

class ProductSpreadsheetPage extends Page
{
    protected string $view = 'filament-spreadsheet-editor-demo::pages.product-spreadsheet';

    public function editor(): SpreadsheetEditor
    {
        return SpreadsheetEditor::make()
            ->model(Product::class)
            ->columns([
                SpreadsheetColumn::make('sku')
                    ->label('SKU')
                    ->text()
                    ->required()
                    ->unique()
                    ->searchable(),
                SpreadsheetColumn::make('name')
                    ->label('Product name')
                    ->text()
                    ->required()
                    ->max(120)
                    ->searchable()
                    ->editable(),
                SpreadsheetColumn::make('price')
                    ->label('Price')
                    ->numeric()
                    ->min(0)
                    ->editable(),
                SpreadsheetColumn::make('stock')
                    ->label('Stock')
                    ->integer()
                    ->min(0)
                    ->editable(),
                SpreadsheetColumn::make('active')
                    ->label('Active')
                    ->boolean()
                    ->editable(),
                SpreadsheetColumn::make('available_on')
                    ->label('Available on')
                    ->date()
                    ->editable(),
                SpreadsheetColumn::make('internal_cost')
                    ->label('Internal cost')
                    ->numeric()
                    ->readOnly(),
            ])
            ->importUniqueColumn('sku')
            ->query(fn (Builder $query): Builder => $query->where('active', true))
            ->authorize(fn ($user): bool => $user?->can('manage products') === true);
    }
}
