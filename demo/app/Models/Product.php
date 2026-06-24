<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Mivento\FilamentSpreadsheetEditor\Concerns\HasSpreadsheetCellAudits;

class Product extends Model
{
    use HasSpreadsheetCellAudits;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'available_on' => 'date:Y-m-d',
            'price' => 'decimal:2',
            'internal_cost' => 'decimal:2',
        ];
    }
}
