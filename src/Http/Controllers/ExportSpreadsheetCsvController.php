<?php

namespace Mivento\FilamentSpreadsheetEditor\Http\Controllers;

use Illuminate\Http\Request;
use Mivento\FilamentSpreadsheetEditor\Actions\ExportSpreadsheetCsv;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSpreadsheetCsvController
{
    public function __invoke(
        string $token,
        Request $request,
        SpreadsheetEditorRegistry $registry,
        ExportSpreadsheetCsv $export,
    ): StreamedResponse {
        $editor = $registry->get($token);

        abort_if($editor === null, 404);

        return $export($editor, $request, $request->user());
    }
}
