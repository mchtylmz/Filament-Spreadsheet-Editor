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

    public function applyQuery(Builder $query): Builder
    {
        if ($this->queryCallback === null) {
            return $query;
        }

        $result = ($this->queryCallback)($query);

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
            'hasQueryCallback' => $this->queryCallback !== null,
            'hasAuthorizationCallback' => $this->authorizationCallback !== null,
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
