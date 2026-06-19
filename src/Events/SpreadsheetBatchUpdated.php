<?php

namespace Mivento\FilamentSpreadsheetEditor\Events;

use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;

class SpreadsheetBatchUpdated
{
    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    public function __construct(
        public SpreadsheetEditor $editor,
        public array $results,
    ) {
        //
    }
}
