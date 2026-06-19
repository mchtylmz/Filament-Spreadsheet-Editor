<?php

namespace Mivento\FilamentSpreadsheetEditor\Support;

use Illuminate\Support\Str;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;

class SpreadsheetEditorRegistry
{
    /** @var array<string, SpreadsheetEditor> */
    protected array $editors = [];

    public function register(SpreadsheetEditor $editor, ?string $key = null): string
    {
        $token = $this->tokenFor($key ?? Str::uuid()->toString());

        $this->editors[$token] = $editor;

        return $token;
    }

    public function get(string $token): ?SpreadsheetEditor
    {
        return $this->editors[$token] ?? null;
    }

    public function has(string $token): bool
    {
        return $this->get($token) !== null;
    }

    public function forget(string $token): void
    {
        unset($this->editors[$token]);
    }

    protected function tokenFor(string $key): string
    {
        $secret = (string) config('app.key', 'filament-spreadsheet-editor');

        return hash_hmac('sha256', $key, $secret);
    }
}
