<?php

namespace Mivento\FilamentSpreadsheetEditor\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Events\SpreadsheetBatchUpdated;
use Mivento\FilamentSpreadsheetEditor\Events\SpreadsheetCellUpdated;
use Mivento\FilamentSpreadsheetEditor\Events\SpreadsheetCellUpdating;

class SaveSpreadsheetRows
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(SpreadsheetEditor $editor, Request $request, ?Authenticatable $user): array
    {
        $changes = $request->input('changes', []);

        abort_unless(is_array($changes), 422, 'Spreadsheet changes must be an array.');

        if (! $editor->isAuthorized($user)) {
            return [
                'has_errors' => true,
                'results' => array_map(
                    fn (array $change): array => $this->result($change, 'forbidden'),
                    array_filter($changes, 'is_array'),
                ),
            ];
        }

        $model = $editor->getModel();

        abort_if($model === null, 422, 'Spreadsheet editor model is not configured.');

        $editableColumns = collect($editor->getColumns())
            ->filter(fn (SpreadsheetColumn $column): bool => $column->isEditable())
            ->keyBy(fn (SpreadsheetColumn $column): string => $column->getName());

        $prepared = [];
        $results = [];

        foreach ($changes as $change) {
            if (! is_array($change)) {
                continue;
            }

            $field = $change['field'] ?? null;
            $id = $change['id'] ?? null;

            if (! is_string($field) || ! $editableColumns->has($field)) {
                $results[] = $this->result($change, 'forbidden');

                continue;
            }

            /** @var SpreadsheetColumn $column */
            $column = $editableColumns->get($field);
            $record = $this->findRecord($editor, $model, $id);

            if (! $record instanceof Model) {
                $results[] = $this->result($change, 'conflict', ['message' => 'Record was not found.']);

                continue;
            }

            $currentValue = $record->getAttribute($field);
            $oldValue = $change['old'] ?? null;

            if (! $this->valuesMatch($currentValue, $oldValue)) {
                $results[] = $this->result($change, 'conflict', [
                    'current' => $currentValue,
                ]);

                continue;
            }

            $rules = $this->rulesForColumn($editor, $column, $record);
            $validator = Validator::make([$field => $change['value'] ?? null], [$field => $rules]);

            if ($validator->fails()) {
                $results[] = $this->result($change, 'validation_error', [
                    'errors' => $validator->errors()->get($field),
                ]);

                continue;
            }

            $prepared[] = [
                'change' => $change,
                'record' => $record,
                'field' => $field,
                'old' => $oldValue,
                'value' => $change['value'] ?? null,
            ];
        }

        if ($this->hasErrors($results)) {
            return [
                'has_errors' => true,
                'results' => array_merge($results, array_map(
                    fn (array $item): array => $this->result($item['change'], 'success', ['committed' => false]),
                    $prepared,
                )),
            ];
        }

        DB::transaction(function () use ($editor, $prepared, &$results): void {
            foreach ($prepared as $item) {
                /** @var Model $record */
                $record = $item['record'];

                event(new SpreadsheetCellUpdating(
                    $editor,
                    $record,
                    $item['field'],
                    $item['old'],
                    $item['value'],
                ));

                $record->setAttribute($item['field'], $item['value']);
                $record->save();

                event(new SpreadsheetCellUpdated(
                    $editor,
                    $record,
                    $item['field'],
                    $item['old'],
                    $item['value'],
                ));

                $results[] = $this->result($item['change'], 'success', ['committed' => true]);
            }

            event(new SpreadsheetBatchUpdated($editor, $results));
        });

        return [
            'has_errors' => false,
            'results' => $results,
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected function findRecord(SpreadsheetEditor $editor, string $model, mixed $id): ?Model
    {
        if ($id === null || $id === '') {
            return null;
        }

        /** @var Builder $query */
        $query = $model::query();
        $query = $editor->applyQuery($query);
        $query = $editor->applyTenantQuery($query, $this->currentFilamentTenant());

        return $query->whereKey($id)->first();
    }

    /**
     * @return array<int, string>
     */
    protected function rulesForColumn(SpreadsheetEditor $editor, SpreadsheetColumn $column, Model $record): array
    {
        return array_map(function (string $rule) use ($column, $editor, $record): string {
            if ($rule !== 'unique') {
                return $rule;
            }

            $model = $editor->getModel();

            if ($model === null) {
                return $rule;
            }

            $modelInstance = new $model();

            return 'unique:' . $modelInstance->getTable() . ',' . $column->getName() . ',' . $record->getKey();
        }, $column->getRules());
    }

    protected function valuesMatch(mixed $currentValue, mixed $oldValue): bool
    {
        if (is_numeric($currentValue) && is_numeric($oldValue)) {
            return (float) $currentValue === (float) $oldValue;
        }

        return (string) $currentValue === (string) $oldValue;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    protected function hasErrors(array $results): bool
    {
        return collect($results)->contains(
            fn (array $result): bool => $result['status'] !== 'success',
        );
    }

    /**
     * @param  array<string, mixed>  $change
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function result(array $change, string $status, array $extra = []): array
    {
        return array_merge([
            'id' => $change['id'] ?? null,
            'field' => $change['field'] ?? null,
            'status' => $status,
        ], $extra);
    }

    protected function currentFilamentTenant(): mixed
    {
        if (! class_exists(\Filament\Facades\Filament::class)) {
            return null;
        }

        try {
            return \Filament\Facades\Filament::getTenant();
        } catch (\Throwable) {
            return null;
        }
    }
}
