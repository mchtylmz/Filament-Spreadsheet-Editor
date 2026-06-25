<?php

namespace Mivento\FilamentSpreadsheetEditor;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Routing\Router;

class SpreadsheetEditorPlugin implements Plugin
{
    protected string $defaultAdapter = 'tabulator';

    protected ?bool $auditLogEnabled = null;

    protected ?bool $csvImportEnabled = null;

    protected ?bool $csvExportEnabled = null;

    public function getId(): string
    {
        return 'filament-spreadsheet-editor';
    }

    public function register(Panel $panel): void
    {
        config([
            'filament-spreadsheet-editor.grid.adapter' => $this->defaultAdapter,
            'filament-spreadsheet-editor.audit_enabled' => $this->hasAuditLogEnabled(),
            'filament-spreadsheet-editor.csv_import_enabled' => $this->hasCsvImportEnabled(),
            'filament-spreadsheet-editor.csv_export_enabled' => $this->hasCsvExportEnabled(),
        ]);

        if ((bool) config('filament-spreadsheet-editor.routes.use_panel_middleware', true)) {
            $middleware = [
                ...$panel->getMiddleware(),
                ...$panel->getAuthMiddleware(),
                ...($panel->hasTenancy() ? $panel->getTenantMiddleware() : []),
            ];

            app(Router::class)->middlewareGroup(
                'filament-spreadsheet-editor',
                array_values(array_unique($middleware)),
            );
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function defaultAdapter(string $adapter): static
    {
        $this->defaultAdapter = $adapter;

        return $this;
    }

    public function enableAuditLog(bool $condition = true): static
    {
        $this->auditLogEnabled = $condition;

        return $this;
    }

    public function enableCsvImport(bool $condition = true): static
    {
        $this->csvImportEnabled = $condition;

        return $this;
    }

    public function enableCsvExport(bool $condition = true): static
    {
        $this->csvExportEnabled = $condition;

        return $this;
    }

    public function getDefaultAdapter(): string
    {
        return $this->defaultAdapter;
    }

    public function hasAuditLogEnabled(): bool
    {
        return $this->auditLogEnabled
            ?? (bool) config('filament-spreadsheet-editor.audit_enabled', false);
    }

    public function hasCsvImportEnabled(): bool
    {
        return $this->csvImportEnabled
            ?? (bool) config('filament-spreadsheet-editor.csv_import_enabled', false);
    }

    public function hasCsvExportEnabled(): bool
    {
        return $this->csvExportEnabled
            ?? (bool) config('filament-spreadsheet-editor.csv_export_enabled', false);
    }
}
