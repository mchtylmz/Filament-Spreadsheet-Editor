<?php

namespace Mivento\FilamentSpreadsheetEditor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mivento\FilamentSpreadsheetEditor\Actions\ApplySpreadsheetCsvImport;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;

class ApplySpreadsheetCsvImportController
{
    public function __invoke(
        string $token,
        Request $request,
        SpreadsheetEditorRegistry $registry,
        ApplySpreadsheetCsvImport $import,
    ): JsonResponse {
        $editor = $registry->get($token);

        abort_if($editor === null, 404);

        return response()->json($import($editor, $request, $request->user(), $token));
    }
}
