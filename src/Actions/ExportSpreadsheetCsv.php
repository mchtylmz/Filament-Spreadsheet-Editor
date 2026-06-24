<?php

namespace Mivento\FilamentSpreadsheetEditor\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Support\CsvFormulaEscaper;
use Mivento\FilamentSpreadsheetEditor\Support\InteractsWithSpreadsheetQuery;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSpreadsheetCsv
{
    use InteractsWithSpreadsheetQuery;

    public function __invoke(
        SpreadsheetEditor $editor,
        Request $request,
        ?Authenticatable $user,
    ): StreamedResponse {
        abort_unless(config('filament-spreadsheet-editor.csv_export_enabled', false), 404);
        abort_unless($editor->isAuthorized($user), 403);

        $columns = collect($editor->getColumns())
            ->keyBy(fn (SpreadsheetColumn $column): string => $column->getName());
        $requestedFields = $request->input('columns', []);

        if (! is_array($requestedFields)) {
            $requestedFields = [];
        }

        $fields = $request->boolean('all_columns')
            ? $columns->keys()->all()
            : collect($requestedFields)
                ->filter(fn (mixed $field): bool => is_string($field) && $columns->has($field))
                ->values()
                ->all();

        if ($fields === []) {
            $fields = $columns->keys()->all();
        }

        $query = $this->spreadsheetQuery($editor, $request);
        $filename = 'spreadsheet-export-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $fields): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $fields);

            foreach ($query->cursor() as $record) {
                fputcsv($output, array_map(
                    fn (string $field): mixed => CsvFormulaEscaper::escape($record->getAttribute($field)),
                    $fields,
                ));
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
