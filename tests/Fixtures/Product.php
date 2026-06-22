<?php

namespace Mivento\FilamentSpreadsheetEditor\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Mivento\FilamentSpreadsheetEditor\Concerns\HasSpreadsheetCellAudits;

class Product extends Model
{
    use HasSpreadsheetCellAudits;

    protected $guarded = [];
}
