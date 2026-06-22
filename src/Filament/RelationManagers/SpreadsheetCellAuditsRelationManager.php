<?php

namespace Mivento\FilamentSpreadsheetEditor\Filament\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SpreadsheetCellAuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'spreadsheetCellAudits';

    protected static ?string $title = 'Spreadsheet audit history';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('field')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('old_value')
                    ->label('Old value')
                    ->formatStateUsing(fn (mixed $state): string => $this->formatValue($state))
                    ->wrap(),
                TextColumn::make('new_value')
                    ->label('New value')
                    ->formatStateUsing(fn (mixed $state): string => $this->formatValue($state))
                    ->wrap(),
                TextColumn::make('user_id')
                    ->label('User')
                    ->placeholder('System')
                    ->sortable(),
                TextColumn::make('batch_uuid')
                    ->label('Batch')
                    ->copyable()
                    ->limit(12),
                TextColumn::make('ip_address')
                    ->label('IP address')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Changed at')
                    ->dateTime()
                    ->sortable(),
            ]);
    }

    protected function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
