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

    protected string $type = 'text';

    /** @var array<int, string> */
    protected array $rules = [];

    final protected function __construct(string $name)
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
        $this->removeRule($condition ? 'nullable' : 'required');

        return $this->rule($condition ? 'required' : 'nullable');
    }

    public function unique(?string $table = null, ?string $column = null): static
    {
        $rule = 'unique';

        if ($table !== null) {
            $rule .= ':'.$table;

            if ($column !== null) {
                $rule .= ','.$column;
            }
        }

        return $this->rule($rule);
    }

    public function numeric(): static
    {
        $this->type = 'number';

        return $this->rule('numeric');
    }

    public function integer(): static
    {
        $this->type = 'integer';

        return $this->rule('integer');
    }

    public function text(): static
    {
        $this->type = 'text';

        return $this;
    }

    public function number(): static
    {
        return $this->numeric();
    }

    public function boolean(): static
    {
        $this->type = 'boolean';

        return $this->rule('boolean');
    }

    public function date(): static
    {
        $this->type = 'date';

        return $this->rule('date');
    }

    public function min(int|float $value): static
    {
        return $this->rule('min:'.$value);
    }

    public function max(int|float $value): static
    {
        return $this->rule('max:'.$value);
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

    protected function removeRule(string $rule): void
    {
        $this->rules = array_values(array_filter(
            $this->rules,
            fn (string $existingRule): bool => $existingRule !== $rule,
        ));
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

    public function getType(): string
    {
        return $this->type;
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
            'type' => $this->type,
            'editor' => $this->editable ? $this->editorForType() : false,
            'editable' => $this->editable,
            'searchable' => $this->searchable,
            'sorter' => $this->sortable ? $this->sorterForType() : false,
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
            'type' => $this->type,
            'editable' => $this->editable,
            'searchable' => $this->searchable,
            'sortable' => $this->sortable,
            'rules' => $this->rules,
        ];
    }

    protected function editorForType(): string
    {
        return match ($this->type) {
            'boolean' => 'tickCross',
            'date' => 'date',
            'integer', 'number' => 'number',
            default => 'input',
        };
    }

    protected function sorterForType(): string
    {
        return match ($this->type) {
            'boolean' => 'boolean',
            'date' => 'date',
            'integer', 'number' => 'number',
            default => 'string',
        };
    }
}
