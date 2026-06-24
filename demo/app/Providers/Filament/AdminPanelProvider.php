<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditorPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(
                SpreadsheetEditorPlugin::make()
                    ->defaultAdapter('tabulator')
                    ->enableAuditLog()
                    ->enableCsvImport()
                    ->enableCsvExport()
            );
    }
}
