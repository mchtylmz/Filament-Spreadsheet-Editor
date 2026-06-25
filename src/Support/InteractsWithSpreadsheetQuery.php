<?php

namespace Mivento\FilamentSpreadsheetEditor\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;

trait InteractsWithSpreadsheetQuery
{
    protected mixed $spreadsheetTenant = null;

    /**
     * @return Builder<Model>
     */
    protected function spreadsheetQuery(SpreadsheetEditor $editor, Request $request): Builder
    {
        $query = $this->spreadsheetBaseQuery($editor);
        $columns = collect($editor->getColumns());
        $fields = $columns
            ->map(fn (SpreadsheetColumn $column): string => $column->getName())
            ->values()
            ->all();
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

        $this->applySpreadsheetSearch($query, $request, $searchableFields);
        $this->applySpreadsheetFilters($query, $request, $fields);
        $this->applySpreadsheetSorting($query, $request, $sortableFields);

        return $query;
    }

    /**
     * @return Builder<Model>
     */
    protected function spreadsheetBaseQuery(SpreadsheetEditor $editor): Builder
    {
        $model = $editor->getModel();

        abort_if($model === null, 422, 'Spreadsheet editor model is not configured.');

        /** @var Builder<Model> $query */
        $query = $model::query();
        $query = $editor->applyQuery($query);
        $query = $editor->applyTenantQuery($query, $this->currentFilamentTenant());

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $searchableFields
     */
    protected function applySpreadsheetSearch(Builder $query, Request $request, array $searchableFields): void
    {
        $search = trim((string) $request->string('search'));

        if ($search === '' || $searchableFields === []) {
            return;
        }

        $query->where(function (Builder $query) use ($search, $searchableFields): void {
            foreach ($searchableFields as $field) {
                $query->orWhere($field, 'like', '%'.$search.'%');
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $fields
     */
    protected function applySpreadsheetFilters(Builder $query, Request $request, array $fields): void
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
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $fields
     */
    protected function applySpreadsheetSorting(Builder $query, Request $request, array $fields): void
    {
        $sort = $request->input('sort', $request->input('sorters.0', []));

        if (! is_array($sort)) {
            return;
        }

        $field = $sort['field'] ?? null;

        if (! is_string($field) || ! in_array($field, $fields, true)) {
            return;
        }

        $direction = strtolower((string) ($sort['direction'] ?? $sort['dir'] ?? 'asc')) === 'desc'
            ? 'desc'
            : 'asc';

        $query->orderBy($field, $direction);
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
