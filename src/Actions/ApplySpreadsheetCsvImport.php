<?php

namespace Mivento\FilamentSpreadsheetEditor\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Jobs\ProcessSpreadsheetCsvImport;
use Mivento\FilamentSpreadsheetEditor\Support\CsvImportStore;
use Mivento\FilamentSpreadsheetEditor\Support\CsvReader;
use Mivento\FilamentSpreadsheetEditor\Support\InteractsWithSpreadsheetQuery;

class ApplySpreadsheetCsvImport
{
    use InteractsWithSpreadsheetQuery;

    public function __construct(
        protected CsvImportStore $store,
        protected CsvReader $reader,
        protected SaveSpreadsheetRows $saveRows,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(
        SpreadsheetEditor $editor,
        Request $request,
        ?Authenticatable $user,
        string $editorToken,
    ): array {
        abort_unless(config('filament-spreadsheet-editor.csv_import_enabled', false), 404);
        abort_unless($editor->isAuthorized($user), 403);

        $importToken = (string) $request->input('import_token');
        $mapping = $request->input('mapping', []);
        $matchBy = (string) $request->input('match_by', 'primary');

        if (! $this->store->exists($importToken) || ! is_array($mapping)) {
            return $this->globalError('The CSV import token or mapping is invalid.');
        }

        $mappingError = $this->mappingError($editor, $importToken, $mapping, $matchBy);

        if ($mappingError !== null) {
            return $this->globalError($mappingError);
        }

        $mapping = $this->normalizeMapping($mapping);
        $totalRows = $this->reader->countRows($importToken);
        $maxSyncRows = max(1, (int) config('filament-spreadsheet-editor.max_sync_import_rows', 1000));

        if ($totalRows > $maxSyncRows) {
            if (! $request->boolean('queue')) {
                return $this->globalError(
                    "This import has {$totalRows} rows and must be queued because the synchronous limit is {$maxSyncRows}.",
                );
            }

            Bus::dispatch(new ProcessSpreadsheetCsvImport(
                $editorToken,
                $importToken,
                $mapping,
                $matchBy,
            ));

            return [
                'has_errors' => false,
                'queued' => true,
                'total_rows' => $totalRows,
            ];
        }

        return $this->applyStored($editor, $importToken, $mapping, $matchBy);
    }

    /**
     * @param  array<string, string>  $mapping
     * @return array<string, mixed>
     */
    public function applyStored(
        SpreadsheetEditor $editor,
        string $importToken,
        array $mapping,
        string $matchBy,
    ): array {
        $model = $editor->getModel();
        abort_if($model === null, 422, 'Spreadsheet editor model is not configured.');

        $modelInstance = new $model;
        $matchField = $matchBy === 'unique'
            ? $editor->getImportUniqueColumn()
            : $modelInstance->getKeyName();

        if ($matchField === null) {
            return $this->globalError('A unique import column is not configured.');
        }

        $columns = collect($editor->getColumns())
            ->keyBy(fn (SpreadsheetColumn $column): string => $column->getName());
        $editableColumns = $columns->filter(
            fn (SpreadsheetColumn $column): bool => $column->isEditable(),
        );
        $matchHeader = array_search($matchField, $mapping, true);

        if (! is_string($matchHeader)) {
            return $this->globalError("The mapping must include the match column [{$matchField}].");
        }

        $changes = [];
        $changeRows = [];
        $rowErrors = [];

        foreach ($this->reader->rows($importToken) as $row) {
            $matchValue = $row['values'][$matchHeader] ?? null;

            if ($matchValue === null || trim((string) $matchValue) === '') {
                $rowErrors[] = $this->rowError($row['line'], $matchField, 'A match value is required.');

                continue;
            }

            $record = $this->spreadsheetBaseQuery($editor)
                ->where($matchField, $matchValue)
                ->first();

            if ($record === null) {
                $rowErrors[] = $this->rowError($row['line'], $matchField, 'No matching record was found.');

                continue;
            }

            foreach ($mapping as $header => $field) {
                if ($field === $matchField || ! $editableColumns->has($field)) {
                    continue;
                }

                /** @var SpreadsheetColumn $column */
                $column = $editableColumns->get($field);
                $value = $this->normalizeValue($column, $row['values'][$header] ?? null);
                $index = count($changes);
                $changes[] = [
                    'id' => $record->getKey(),
                    'field' => $field,
                    'old' => $record->getAttribute($field),
                    'value' => $value,
                ];
                $changeRows[$index] = $row['line'];
            }
        }

        if ($changes === []) {
            return [
                'has_errors' => $rowErrors !== [],
                'applied' => false,
                'row_errors' => $rowErrors,
                'updated_rows' => 0,
            ];
        }

        $validationRequest = Request::create('/', 'POST', [
            'changes' => $changes,
        ]);
        $validationRequest->attributes->set('_spreadsheet_pre_authorized', true);
        $validationRequest->attributes->set('_spreadsheet_validate_only', true);
        $validation = ($this->saveRows)($editor, $validationRequest, null);
        $rowErrors = [
            ...$rowErrors,
            ...$this->rowErrorsFromResults($validation['results'], $changeRows),
        ];

        if ($rowErrors !== []) {
            return [
                'has_errors' => true,
                'applied' => false,
                'row_errors' => $rowErrors,
                'updated_rows' => 0,
            ];
        }

        $saveRequest = Request::create('/', 'POST', ['changes' => $changes]);
        $saveRequest->attributes->set('_spreadsheet_pre_authorized', true);
        $saved = ($this->saveRows)($editor, $saveRequest, null);

        if ($saved['has_errors']) {
            return [
                'has_errors' => true,
                'applied' => false,
                'row_errors' => $this->rowErrorsFromResults($saved['results'], $changeRows),
                'updated_rows' => 0,
            ];
        }

        $this->store->delete($importToken);

        return [
            'has_errors' => false,
            'applied' => true,
            'row_errors' => [],
            'updated_rows' => count(array_unique($changeRows)),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $mapping
     */
    protected function mappingError(
        SpreadsheetEditor $editor,
        string $importToken,
        array $mapping,
        string $matchBy,
    ): ?string {
        if (! in_array($matchBy, ['primary', 'unique'], true)) {
            return 'The match_by value must be primary or unique.';
        }

        $headers = $this->reader->headers($importToken);
        $model = $editor->getModel();

        if ($model === null) {
            return 'Spreadsheet editor model is not configured.';
        }

        $primaryKey = (new $model)->getKeyName();
        $allowedFields = collect($editor->getColumns())
            ->map(fn (SpreadsheetColumn $column): string => $column->getName())
            ->push($primaryKey)
            ->unique()
            ->all();

        foreach ($mapping as $header => $field) {
            if (! is_string($header) || ! in_array($header, $headers, true)) {
                return "Mapped CSV header [{$header}] does not exist.";
            }

            if (! is_string($field) || ! in_array($field, $allowedFields, true)) {
                return "Mapped spreadsheet column [{$field}] is not configured.";
            }
        }

        if (count(array_unique($mapping)) !== count($mapping)) {
            return 'Each spreadsheet column may only be mapped once.';
        }

        if ($matchBy === 'unique' && $editor->getImportUniqueColumn() === null) {
            return 'A unique import column is not configured.';
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $mapping
     * @return array<string, string>
     */
    protected function normalizeMapping(array $mapping): array
    {
        $normalized = [];

        foreach ($mapping as $header => $field) {
            if (is_string($header) && is_string($field)) {
                $normalized[$header] = $field;
            }
        }

        return $normalized;
    }

    protected function normalizeValue(SpreadsheetColumn $column, mixed $value): mixed
    {
        if ($column->getType() !== 'boolean' || ! is_string($value)) {
            return $value;
        }

        return match (strtolower(trim($value))) {
            'true', 'yes' => true,
            'false', 'no' => false,
            default => $value,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<int, int>  $changeRows
     * @return array<int, array<string, mixed>>
     */
    protected function rowErrorsFromResults(array $results, array $changeRows): array
    {
        $errors = [];

        foreach ($results as $index => $result) {
            if (($result['status'] ?? null) === 'success') {
                continue;
            }

            $errors[] = [
                'line' => $changeRows[$index] ?? null,
                'field' => $result['field'] ?? null,
                'status' => $result['status'] ?? 'validation_error',
                'errors' => $result['errors'] ?? [$result['message'] ?? 'The value is invalid.'],
            ];
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rowError(int $line, string $field, string $message): array
    {
        return [
            'line' => $line,
            'field' => $field,
            'status' => 'validation_error',
            'errors' => [$message],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function globalError(string $message): array
    {
        return [
            'has_errors' => true,
            'applied' => false,
            'errors' => [$message],
            'row_errors' => [],
        ];
    }
}
