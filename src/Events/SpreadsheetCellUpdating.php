<?php

namespace Mivento\FilamentSpreadsheetEditor\Events;

use Illuminate\Database\Eloquent\Model;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;

class SpreadsheetCellUpdating
{
    public function __construct(
        public SpreadsheetEditor $editor,
        public Model $record,
        public string $field,
        public mixed $oldValue,
        public mixed $newValue,
    ) {
        //
    }
}
