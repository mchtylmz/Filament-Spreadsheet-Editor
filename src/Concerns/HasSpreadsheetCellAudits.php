<?php

namespace Mivento\FilamentSpreadsheetEditor\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Mivento\FilamentSpreadsheetEditor\Models\SpreadsheetCellAudit;

trait HasSpreadsheetCellAudits
{
    public function spreadsheetCellAudits(): MorphMany
    {
        return $this->morphMany(SpreadsheetCellAudit::class, 'model');
    }
}
