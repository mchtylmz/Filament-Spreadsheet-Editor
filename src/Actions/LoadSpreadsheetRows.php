<?php

namespace Mivento\FilamentSpreadsheetEditor\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Support\InteractsWithSpreadsheetQuery;

class LoadSpreadsheetRows
{
    use InteractsWithSpreadsheetQuery;

    /**
     * @return array<string, mixed>
     */
    public function __invoke(SpreadsheetEditor $editor, Request $request, ?Authenticatable $user): array
    {
        abort_unless($editor->isAuthorized($user), 403);

        $columns = collect($editor->getColumns());
        $fields = $columns->map(fn (SpreadsheetColumn $column): string => $column->getName())->values()->all();
        $query = $this->spreadsheetQuery($editor, $request);

        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));
        $page = max(1, (int) $request->integer('page', 1));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $records = $paginator->getCollection();

        return [
            'data' => $records
                ->map(fn ($model): array => collect($model->only($fields))->all())
                ->values()
                ->all(),
            'row_ids' => $records
                ->map(fn ($model): mixed => $model->getKey())
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
