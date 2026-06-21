<?php

namespace Mivento\FilamentSpreadsheetEditor\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;

class LoadSpreadsheetRows
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(SpreadsheetEditor $editor, Request $request, ?Authenticatable $user): array
    {
        abort_unless($editor->isAuthorized($user), 403);

        $model = $editor->getModel();

        abort_if($model === null, 422, 'Spreadsheet editor model is not configured.');

        /** @var Builder $query */
        $query = $model::query();
        $query = $editor->applyQuery($query);
        $query = $editor->applyTenantQuery($query, $this->currentFilamentTenant());

        $columns = collect($editor->getColumns());
        $fields = $columns->map(fn (SpreadsheetColumn $column): string => $column->getName())->values()->all();
        $searchableFields = $columns
            ->filter(fn (SpreadsheetColumn $column): bool => $column->isSearchable())
            ->map(fn (SpreadsheetColumn $column): string => $column->getName())
            ->values()
            ->all();
        $sortableFields = $columns
            ->filter(fn (SpreadsheetColumn $column): bool => $column->isSortable())
            ->map(fn (SpreadsheetColumn $column): string => $column->getName())
            ->values()
            ->all();

        $this->applySearch($query, $request, $searchableFields);
        $this->applyFilters($query, $request, $fields);
        $this->applySorting($query, $request, $sortableFields);

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

    /**
     * @param  array<int, string>  $searchableFields
     */
    protected function applySearch(Builder $query, Request $request, array $searchableFields): void
    {
        $search = trim((string) $request->string('search'));

        if ($search === '' || $searchableFields === []) {
            return;
        }

        $query->where(function (Builder $query) use ($search, $searchableFields): void {
            foreach ($searchableFields as $field) {
                $query->orWhere($field, 'like', '%' . $search . '%');
            }
        });
    }

    /**
     * @param  array<int, string>  $fields
     */
    protected function applyFilters(Builder $query, Request $request, array $fields): void
    {
        $filters = $request->input('filters', []);

        if (! is_array($filters)) {
            return;
        }

        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $field = $value['field'] ?? $field;
                $value = $value['value'] ?? null;
            }

            if (! in_array($field, $fields, true) || $value === null || $value === '') {
                continue;
            }

            $query->where($field, $value);
        }
    }

    /**
     * @param  array<int, string>  $fields
     */
    protected function applySorting(Builder $query, Request $request, array $fields): void
    {
        $sort = $request->input('sort', $request->input('sorters.0', []));

        if (! is_array($sort)) {
            return;
        }

        $field = $sort['field'] ?? null;

        if (! is_string($field) || ! in_array($field, $fields, true)) {
            return;
        }

        $direction = strtolower((string) ($sort['direction'] ?? $sort['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $query->orderBy($field, $direction);
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
