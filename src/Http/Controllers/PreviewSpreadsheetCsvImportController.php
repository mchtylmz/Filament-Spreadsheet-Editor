<?php

namespace Mivento\FilamentSpreadsheetEditor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mivento\FilamentSpreadsheetEditor\Actions\PreviewSpreadsheetCsvImport;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;

class PreviewSpreadsheetCsvImportController
{
    public function __invoke(
        string $token,
        Request $request,
        SpreadsheetEditorRegistry $registry,
        PreviewSpreadsheetCsvImport $preview,
    ): JsonResponse {
        $editor = $registry->get($token);

        abort_if($editor === null, 404);

        return response()->json($preview($editor, $request, $request->user(), $token));
    }
}
