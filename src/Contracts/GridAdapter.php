<?php

namespace Mivento\FilamentSpreadsheetEditor\Contracts;

interface GridAdapter
{
    public function name(): string;

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function options(array $columns, array $rows): array;
}
