<?php

namespace Mivento\FilamentSpreadsheetEditor\GridAdapters;

use Mivento\FilamentSpreadsheetEditor\Contracts\GridAdapter;

class TabulatorGridAdapter implements GridAdapter
{
    public function name(): string
    {
        return 'tabulator';
    }

    public function options(array $columns, array $rows): array
    {
        return [
            'layout' => 'fitColumns',
            'reactiveData' => true,
            'columns' => $columns,
            'data' => $rows,
        ];
    }
}
