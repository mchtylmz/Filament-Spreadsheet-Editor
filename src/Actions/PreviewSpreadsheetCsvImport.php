<?php

namespace Mivento\FilamentSpreadsheetEditor\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Support\CsvImportStore;
use Mivento\FilamentSpreadsheetEditor\Support\CsvReader;
use Mivento\FilamentSpreadsheetEditor\Support\FilamentTenantContext;
use RuntimeException;

class PreviewSpreadsheetCsvImport
{
    public function __construct(
        protected CsvImportStore $store,
        protected CsvReader $reader,
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

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        if ($validator->fails()) {
            return [
                'has_errors' => true,
                'errors' => $validator->errors()->toArray(),
            ];
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $token = $this->store->store($file, [
            'editor_token' => $editorToken,
            'user_type' => $user instanceof Model ? $user::class : null,
            'user_id' => $user?->getAuthIdentifier() !== null ? (string) $user->getAuthIdentifier() : null,
            'tenant' => FilamentTenantContext::serialize(),
            'original_name' => $file->getClientOriginalName(),
        ]);

        try {
            $headers = $this->reader->headers($token);
            $preview = [];
            $totalRows = 0;

            foreach ($this->reader->rows($token) as $row) {
                $totalRows++;

                if (count($preview) < 20) {
                    $preview[] = [
                        'line' => $row['line'],
                        ...$row['values'],
                    ];
                }
            }
        } catch (RuntimeException $exception) {
            $this->store->delete($token);

            return [
                'has_errors' => true,
                'errors' => ['file' => [$exception->getMessage()]],
            ];
        }

        $model = $editor->getModel();
        abort_if($model === null, 422, 'Spreadsheet editor model is not configured.');
        $primaryKey = (new $model)->getKeyName();
        $columns = collect($editor->getColumns());
        $targets = $columns
            ->map(fn (SpreadsheetColumn $column): array => [
                'field' => $column->getName(),
                'label' => $column->getLabel(),
                'editable' => $column->isEditable(),
            ])
            ->prepend([
                'field' => $primaryKey,
                'label' => $primaryKey,
                'editable' => false,
            ])
            ->unique('field')
            ->values()
            ->all();

        return [
            'has_errors' => false,
            'import_token' => $token,
            'headers' => $headers,
            'preview' => $preview,
            'total_rows' => $totalRows,
            'columns' => $targets,
            'suggested_mapping' => $this->suggestedMapping($headers, $targets),
            'primary_key' => $primaryKey,
            'unique_column' => $editor->getImportUniqueColumn(),
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array{field: string, label: string, editable: bool}>  $targets
     * @return array<string, string>
     */
    protected function suggestedMapping(array $headers, array $targets): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            $normalizedHeader = str($header)->lower()->replace([' ', '-', '_'], '')->toString();

            foreach ($targets as $target) {
                $field = str($target['field'])->lower()->replace([' ', '-', '_'], '')->toString();
                $label = str($target['label'])->lower()->replace([' ', '-', '_'], '')->toString();

                if ($normalizedHeader === $field || $normalizedHeader === $label) {
                    $mapping[$header] = $target['field'];

                    break;
                }
            }
        }

        return $mapping;
    }
}
