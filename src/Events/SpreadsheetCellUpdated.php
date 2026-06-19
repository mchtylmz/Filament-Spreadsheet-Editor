<?php

namespace Mivento\FilamentSpreadsheetEditor\Events;

use Illuminate\Database\Eloquent\Model;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;

class SpreadsheetCellUpdated
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
