<?php

namespace Mivento\FilamentSpreadsheetEditor\Builders;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class SpreadsheetColumn implements Arrayable
{
    protected string $name;

    protected ?string $label = null;

    protected bool $editable = false;

    protected bool $searchable = false;

    protected bool $sortable = true;

    /** @var array<int, string> */
    protected array $rules = [];

    protected function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function editable(bool $condition = true): static
    {
        $this->editable = $condition;

        return $this;
    }

    public function readOnly(bool $condition = true): static
    {
        $this->editable = ! $condition;

        return $this;
    }

    public function searchable(bool $condition = true): static
    {
        $this->searchable = $condition;

        return $this;
    }

    public function sortable(bool $condition = true): static
    {
        $this->sortable = $condition;

        return $this;
    }

    public function required(bool $condition = true): static
    {
        return $this->rule($condition ? 'required' : 'nullable');
    }

    public function unique(?string $table = null, ?string $column = null): static
    {
        $rule = 'unique';

        if ($table !== null) {
            $rule .= ':' . $table;

            if ($column !== null) {
                $rule .= ',' . $column;
            }
        }

        return $this->rule($rule);
    }

    public function numeric(): static
    {
        return $this->rule('numeric');
    }

    public function integer(): static
    {
        return $this->rule('integer');
    }

    public function min(int|float $value): static
    {
        return $this->rule('min:' . $value);
    }

    public function max(int|float $value): static
    {
        return $this->rule('max:' . $value);
    }

    public function rule(string $rule): static
    {
        if (! in_array($rule, $this->rules, true)) {
            $this->rules[] = $rule;
        }

        return $this;
    }

    /**
     * @param  array<int, string>  $rules
     */
    public function rules(array $rules): static
    {
        foreach ($rules as $rule) {
            $this->rule($rule);
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? str($this->name)->headline()->toString();
    }

    public function isEditable(): bool
    {
        return $this->editable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    /**
     * @return array<int, string>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function toValidationRulePayload(): array
    {
        return [
            'attribute' => $this->name,
            'rules' => $this->rules,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toGridColumn(): array
    {
        return [
            'field' => $this->name,
            'title' => $this->getLabel(),
            'editor' => $this->editable ? 'input' : false,
            'editable' => $this->editable,
            'searchable' => $this->searchable,
            'sorter' => $this->sortable ? 'string' : false,
            'validationRules' => $this->rules,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->getLabel(),
            'editable' => $this->editable,
            'searchable' => $this->searchable,
            'sortable' => $this->sortable,
            'rules' => $this->rules,
        ];
    }
}
