<?php

namespace Mivento\FilamentSpreadsheetEditor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mivento\FilamentSpreadsheetEditor\Actions\SaveSpreadsheetRows;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;

class SaveSpreadsheetRowsController
{
    public function __invoke(
        string $token,
        Request $request,
        SpreadsheetEditorRegistry $registry,
        SaveSpreadsheetRows $saveRows,
    ): JsonResponse {
        $editor = $registry->get($token);

        abort_if($editor === null, 404);

        return response()->json($saveRows($editor, $request, $request->user()));
    }
}
