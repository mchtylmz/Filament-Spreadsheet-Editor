<?php

namespace Mivento\FilamentSpreadsheetEditor;

use Filament\Contracts\Plugin;
use Filament\Panel;

class SpreadsheetEditorPlugin implements Plugin
{
    protected string $defaultAdapter = 'tabulator';

    protected bool $auditLogEnabled = false;

    protected bool $csvImportEnabled = false;

    protected bool $csvExportEnabled = false;

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
        return $this->auditLogEnabled;
    }

    public function hasCsvImportEnabled(): bool
    {
        return $this->csvImportEnabled;
    }

    public function hasCsvExportEnabled(): bool
    {
        return $this->csvExportEnabled;
    }
}
