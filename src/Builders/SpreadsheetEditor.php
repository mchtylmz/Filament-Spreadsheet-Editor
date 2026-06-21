<?php

namespace Mivento\FilamentSpreadsheetEditor\Builders;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @implements Arrayable<string, mixed>
 */
class SpreadsheetEditor implements Arrayable
{
    /** @var class-string<Model>|null */
    protected ?string $model = null;

    /** @var array<int, SpreadsheetColumn> */
    protected array $columns = [];

    protected ?Closure $queryCallback = null;

    protected ?Closure $authorizationCallback = null;

    protected ?Closure $tenantQueryCallback = null;

    protected bool $selectableRows = true;

    protected bool $clipboard = true;

    /** @var array<int, array<string, mixed>> */
    protected array $rows = [];

    protected function __construct()
    {
        //
    }

    public static function make(): static
    {
        return new static();
    }

    /**
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        if (! is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException("Spreadsheet editor model [{$model}] must extend " . Model::class . '.');
        }

        $this->model = $model;

        return $this;
    }

    /**
     * @param  array<int, SpreadsheetColumn>  $columns
     */
    public function columns(array $columns): static
    {
        foreach ($columns as $column) {
            if (! $column instanceof SpreadsheetColumn) {
                throw new InvalidArgumentException('Spreadsheet editor columns must be instances of ' . SpreadsheetColumn::class . '.');
            }
        }

        $this->columns = array_values($columns);

        return $this;
    }

    public function query(Closure $callback): static
    {
        $this->queryCallback = $callback;

        return $this;
    }

    public function authorize(Closure $callback): static
    {
        $this->authorizationCallback = $callback;

        return $this;
    }

    public function tenantQuery(Closure $callback): static
    {
        $this->tenantQueryCallback = $callback;

        return $this;
    }

    public function selectableRows(bool $condition = true): static
    {
        $this->selectableRows = $condition;

        return $this;
    }

    public function clipboard(bool $condition = true): static
    {
        $this->clipboard = $condition;

        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function rows(array $rows): static
    {
        $this->rows = array_values($rows);

        return $this;
    }

    /**
     * @return class-string<Model>|null
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * @return array<int, SpreadsheetColumn>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getQueryCallback(): ?Closure
    {
        return $this->queryCallback;
    }

    public function getAuthorizationCallback(): ?Closure
    {
        return $this->authorizationCallback;
    }

    public function getTenantQueryCallback(): ?Closure
    {
        return $this->tenantQueryCallback;
    }

    public function hasSelectableRows(): bool
    {
        return $this->selectableRows;
    }

    public function hasClipboard(): bool
    {
        return $this->clipboard;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function applyQuery(Builder $query): Builder
    {
        if ($this->queryCallback === null) {
            return $query;
        }

        $result = ($this->queryCallback)($query);

        return $result instanceof Builder ? $result : $query;
    }

    public function applyTenantQuery(Builder $query, mixed $tenant): Builder
    {
        if ($this->tenantQueryCallback === null || $tenant === null) {
            return $query;
        }

        $result = ($this->tenantQueryCallback)($query, $tenant);

        return $result instanceof Builder ? $result : $query;
    }

    public function isAuthorized(mixed $user): bool
    {
        if ($this->authorizationCallback === null) {
            return true;
        }

        return (bool) ($this->authorizationCallback)($user);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function validationRules(): array
    {
        return collect($this->columns)
            ->mapWithKeys(fn (SpreadsheetColumn $column): array => [
                $column->getName() => $this->resolveRulesForColumn($column),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function serializedValidationRules(): array
    {
        return array_map(
            fn (SpreadsheetColumn $column): array => [
                'attribute' => $column->getName(),
                'rules' => $this->resolveRulesForColumn($column),
            ],
            $this->columns,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function gridColumns(): array
    {
        return array_map(
            fn (SpreadsheetColumn $column): array => $column->toGridColumn(),
            $this->columns,
        );
    }

    /**
     * @return Collection<int, SpreadsheetColumn>
     */
    public function editableColumns(): Collection
    {
        return collect($this->columns)->filter(
            fn (SpreadsheetColumn $column): bool => $column->isEditable(),
        )->values();
    }

    /**
     * @return Collection<int, SpreadsheetColumn>
     */
    public function readOnlyColumns(): Collection
    {
        return collect($this->columns)->reject(
            fn (SpreadsheetColumn $column): bool => $column->isEditable(),
        )->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'columns' => array_map(
                fn (SpreadsheetColumn $column): array => $column->toArray(),
                $this->columns,
            ),
            'gridColumns' => $this->gridColumns(),
            'validationRules' => $this->serializedValidationRules(),
            'rows' => $this->rows,
            'selectableRows' => $this->selectableRows,
            'clipboard' => $this->clipboard,
            'hasQueryCallback' => $this->queryCallback !== null,
            'hasAuthorizationCallback' => $this->authorizationCallback !== null,
            'hasTenantQueryCallback' => $this->tenantQueryCallback !== null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $rows
     * @return array<string, mixed>
     */
    public function toFrontendConfig(?array $rows = null): array
    {
        $registry = app(\Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry::class);
        $token = $registry->tokenForEditor($this);

        $config = [
            'adapter' => 'tabulator',
            'columns' => $this->gridColumns(),
            'rows' => $rows ?? $this->rows,
            'validationRules' => $this->serializedValidationRules(),
            'features' => [
                'selectableRows' => $this->selectableRows,
                'clipboard' => $this->clipboard,
                'dirtyCells' => true,
                'mockSave' => $token === null,
            ],
        ];

        if ($token === null) {
            return $config;
        }

        return [
            ...$config,
            'token' => $token,
            'dataUrl' => route('filament-spreadsheet-editor.rows.index', ['token' => $token]),
            'saveUrl' => route('filament-spreadsheet-editor.rows.update', ['token' => $token]),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function resolveRulesForColumn(SpreadsheetColumn $column): array
    {
        return array_map(function (string $rule) use ($column): string {
            if ($rule !== 'unique' || $this->model === null) {
                return $rule;
            }

            $model = new $this->model();

            return 'unique:' . $model->getTable() . ',' . $column->getName();
        }, $column->getRules());
    }
}
