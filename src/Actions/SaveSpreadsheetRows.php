<?php

namespace Mivento\FilamentSpreadsheetEditor\Actions;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Events\SpreadsheetBatchUpdated;
use Mivento\FilamentSpreadsheetEditor\Events\SpreadsheetCellUpdated;
use Mivento\FilamentSpreadsheetEditor\Events\SpreadsheetCellUpdating;
use Mivento\FilamentSpreadsheetEditor\Models\SpreadsheetCellAudit;

class SaveSpreadsheetRows
{
    protected mixed $spreadsheetTenant = null;

    /**
     * @return array<string, mixed>
     */
    public function __invoke(SpreadsheetEditor $editor, Request $request, ?Authenticatable $user): array
    {
        $this->spreadsheetTenant = $request->attributes->get('_spreadsheet_tenant');
        $changes = $request->input('changes', []);

        abort_unless(is_array($changes), 422, 'Spreadsheet changes must be an array.');

        $preAuthorized = $request->attributes->get('_spreadsheet_pre_authorized') === true;

        if (! $preAuthorized && ! $editor->isAuthorized($user)) {
            $results = [];

            foreach ($changes as $index => $change) {
                $results[$index] = is_array($change)
                    ? $this->result($change, 'forbidden')
                    : $this->result([], 'validation_error', [
                        'errors' => ['Each spreadsheet change must be an object.'],
                    ]);
            }

            return $this->response($results, hasErrors: true);
        }

        $model = $editor->getModel();

        abort_if($model === null, 422, 'Spreadsheet editor model is not configured.');

        $editableColumns = collect($editor->getColumns())
            ->filter(fn (SpreadsheetColumn $column): bool => $column->isEditable())
            ->keyBy(fn (SpreadsheetColumn $column): string => $column->getName());

        $prepared = [];
        $results = [];
        $seenCells = [];

        foreach ($changes as $index => $change) {
            if (! is_array($change)) {
                $results[$index] = $this->result([], 'validation_error', [
                    'errors' => ['Each spreadsheet change must be an object.'],
                ]);

                continue;
            }

            $field = $change['field'] ?? null;
            $id = $change['id'] ?? null;

            if (! is_string($field) || ! $editableColumns->has($field)) {
                $results[$index] = $this->result($change, 'forbidden');

                continue;
            }

            $cellKey = (string) $id.':'.$field;

            if (isset($seenCells[$cellKey])) {
                $results[$index] = $this->result($change, 'validation_error', [
                    'errors' => ['A cell may only appear once in a batch.'],
                ]);

                continue;
            }

            $seenCells[$cellKey] = true;

            /** @var SpreadsheetColumn $column */
            $column = $editableColumns->get($field);
            $record = $this->findRecord($editor, $model, $id);

            if (! $record instanceof Model) {
                $results[$index] = $this->result($change, 'conflict', ['message' => 'Record was not found.']);

                continue;
            }

            $currentValue = $record->getAttribute($field);
            $oldValue = $change['old'] ?? null;

            if (! $this->valuesMatch($currentValue, $oldValue)) {
                $results[$index] = $this->result($change, 'conflict', [
                    'current' => $currentValue,
                ]);

                continue;
            }

            $rules = $this->rulesForColumn($editor, $column, $record);
            $validator = Validator::make([$field => $change['value'] ?? null], [$field => $rules]);

            if ($validator->fails()) {
                $results[$index] = $this->result($change, 'validation_error', [
                    'errors' => $validator->errors()->get($field),
                ]);

                continue;
            }

            $prepared[$index] = [
                'change' => $change,
                'id' => $record->getKey(),
                'field' => $field,
                'old' => $oldValue,
                'value' => $change['value'] ?? null,
            ];
        }

        if ($this->hasErrors($results)) {
            foreach ($prepared as $index => $item) {
                $results[$index] = $this->result($item['change'], 'success', ['committed' => false]);
            }

            return $this->response($results, hasErrors: true);
        }

        if ($request->attributes->get('_spreadsheet_validate_only') === true) {
            foreach ($prepared as $index => $item) {
                $results[$index] = $this->result($item['change'], 'success', ['committed' => false]);
            }

            return $this->response($results, hasErrors: false);
        }

        return DB::transaction(function () use ($editor, $model, $prepared, $request, $user): array {
            $results = [];
            $lockedRecords = $this->lockRecords($editor, $model, $prepared);
            $batchUuid = (string) Str::uuid();

            foreach ($prepared as $index => $item) {
                $record = $lockedRecords[(string) $item['id']] ?? null;

                if (! $record instanceof Model) {
                    $results[$index] = $this->result($item['change'], 'conflict', [
                        'message' => 'Record was not found.',
                    ]);

                    continue;
                }

                $currentValue = $record->getAttribute($item['field']);

                if (! $this->valuesMatch($currentValue, $item['old'])) {
                    $results[$index] = $this->result($item['change'], 'conflict', [
                        'current' => $currentValue,
                    ]);
                }
            }

            if ($this->hasErrors($results)) {
                foreach ($prepared as $index => $item) {
                    $results[$index] ??= $this->result(
                        $item['change'],
                        'success',
                        ['committed' => false],
                    );
                }

                return $this->response($results, hasErrors: true);
            }

            foreach ($prepared as $index => $item) {
                /** @var Model $record */
                $record = $lockedRecords[(string) $item['id']];
                $persistedOldValue = $record->getAttribute($item['field']);

                event(new SpreadsheetCellUpdating(
                    $editor,
                    $record,
                    $item['field'],
                    $item['old'],
                    $item['value'],
                ));

                $record->setAttribute($item['field'], $item['value']);
                $record->save();

                $this->writeAudit(
                    $record,
                    $item['field'],
                    $persistedOldValue,
                    $record->getAttribute($item['field']),
                    $batchUuid,
                    $request,
                    $user,
                );

                event(new SpreadsheetCellUpdated(
                    $editor,
                    $record,
                    $item['field'],
                    $item['old'],
                    $item['value'],
                ));

                $results[$index] = $this->result($item['change'], 'success', ['committed' => true]);
            }

            $orderedResults = $this->orderedResults($results);

            event(new SpreadsheetBatchUpdated($editor, $orderedResults));

            return [
                'has_errors' => false,
                'results' => $orderedResults,
            ];
        });
    }

    protected function writeAudit(
        Model $record,
        string $field,
        mixed $oldValue,
        mixed $newValue,
        string $batchUuid,
        Request $request,
        ?Authenticatable $user,
    ): void {
        if (! config('filament-spreadsheet-editor.audit_enabled', false)) {
            return;
        }

        $oldValue = $this->auditValue($field, $oldValue);
        $newValue = $this->auditValue($field, $newValue);

        SpreadsheetCellAudit::query()->create([
            'user_id' => $user?->getAuthIdentifier(),
            'model_type' => $record->getMorphClass(),
            'model_id' => (string) $record->getKey(),
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'batch_uuid' => $batchUuid,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    protected function auditValue(string $field, mixed $value): mixed
    {
        if (! (bool) config('filament-spreadsheet-editor.audit.redact_sensitive_fields', true)) {
            return $value;
        }

        $sensitiveFields = config('filament-spreadsheet-editor.sensitive_fields', []);

        if (! is_array($sensitiveFields) || ! in_array($field, $sensitiveFields, true)) {
            return $value;
        }

        return config('filament-spreadsheet-editor.audit.redacted_value', '[redacted]');
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected function findRecord(
        SpreadsheetEditor $editor,
        string $model,
        mixed $id,
        bool $lockForUpdate = false,
    ): ?Model {
        if ($id === null || $id === '') {
            return null;
        }

        /** @var Builder<Model> $query */
        $query = $model::query();
        $query = $editor->applyQuery($query);
        $query = $editor->applyTenantQuery($query, $this->currentFilamentTenant());

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->whereKey($id)->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $prepared
     * @return array<string, Model>
     */
    protected function lockRecords(SpreadsheetEditor $editor, string $model, array $prepared): array
    {
        $ids = collect($prepared)
            ->pluck('id')
            ->uniqueStrict()
            ->sortBy(fn (mixed $id): string => (string) $id, SORT_NATURAL)
            ->values();
        $records = [];

        foreach ($ids as $id) {
            $record = $this->findRecord($editor, $model, $id, lockForUpdate: true);

            if ($record instanceof Model) {
                $records[(string) $id] = $record;
            }
        }

        return $records;
    }

    /**
     * @return array<int, mixed>
     */
    protected function rulesForColumn(SpreadsheetEditor $editor, SpreadsheetColumn $column, Model $record): array
    {
        return array_map(function (mixed $rule) use ($column, $editor, $record): mixed {
            if (! is_string($rule)) {
                return $rule;
            }

            if ($rule !== 'unique' && ! str_starts_with($rule, 'unique:')) {
                return $rule;
            }

            $model = $editor->getModel();

            if ($model === null) {
                return $rule;
            }

            $parameters = $rule === 'unique'
                ? []
                : str_getcsv(substr($rule, strlen('unique:')));
            $modelInstance = new $model;
            $table = $parameters[0] ?? $modelInstance->getTable();
            $field = $parameters[1] ?? $column->getName();

            return Rule::unique($table, $field)
                ->ignore($record->getKey(), $record->getKeyName());
        }, $column->getRules());
    }

    protected function valuesMatch(mixed $currentValue, mixed $oldValue): bool
    {
        if ($currentValue === null || $oldValue === null) {
            return $currentValue === null && $oldValue === null;
        }

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
     * @param  array<int, array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    protected function response(array $results, bool $hasErrors): array
    {
        return [
            'has_errors' => $hasErrors,
            'results' => $this->orderedResults($results),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    protected function orderedResults(array $results): array
    {
        ksort($results);

        return array_values($results);
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
        if ($this->spreadsheetTenant !== null) {
            return $this->spreadsheetTenant;
        }

        if (! class_exists(Filament::class)) {
            return null;
        }

        try {
            return Filament::getTenant();
        } catch (\Throwable) {
            return null;
        }
    }
}
