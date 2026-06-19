<?php

namespace Mivento\FilamentSpreadsheetEditor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mivento\FilamentSpreadsheetEditor\Actions\LoadSpreadsheetRows;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;

class LoadSpreadsheetRowsController
{
    public function __invoke(
        string $token,
        Request $request,
        SpreadsheetEditorRegistry $registry,
        LoadSpreadsheetRows $loadRows,
    ): JsonResponse {
        $editor = $registry->get($token);

        abort_if($editor === null, 404);

        return response()->json($loadRows($editor, $request, $request->user()));
    }
}
