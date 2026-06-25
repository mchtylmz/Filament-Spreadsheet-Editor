<?php

namespace Mivento\FilamentSpreadsheetEditor\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;

class SpreadsheetCellUpdated implements ShouldDispatchAfterCommit
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
