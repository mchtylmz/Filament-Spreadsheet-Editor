<?php

namespace Mivento\FilamentSpreadsheetEditor;

use Filament\Contracts\Plugin;
use Filament\Panel;

class SpreadsheetEditorPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-spreadsheet-editor';
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
