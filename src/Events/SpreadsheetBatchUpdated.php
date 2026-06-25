<?php

namespace Mivento\FilamentSpreadsheetEditor\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;

class SpreadsheetBatchUpdated implements ShouldDispatchAfterCommit
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
