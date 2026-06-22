<?php

namespace Mivento\FilamentSpreadsheetEditor\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Mivento\FilamentSpreadsheetEditor\Actions\ApplySpreadsheetCsvImport;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;
use RuntimeException;

class ProcessSpreadsheetCsvImport implements ShouldQueue
{
    /**
     * @param  array<string, string>  $mapping
     */
    public function __construct(
        public string $editorToken,
        public string $importToken,
        public array $mapping,
        public string $matchBy,
    ) {
        //
    }

    public function handle(
        SpreadsheetEditorRegistry $registry,
        ApplySpreadsheetCsvImport $import,
    ): void {
        $editor = $registry->get($this->editorToken);

        if ($editor === null) {
            throw new RuntimeException('The queued spreadsheet editor is not registered.');
        }

        $result = $import->applyStored(
            $editor,
            $this->importToken,
            $this->mapping,
            $this->matchBy,
        );

        if ($result['has_errors']) {
            throw new RuntimeException('The queued CSV import failed validation.');
        }
    }
}
