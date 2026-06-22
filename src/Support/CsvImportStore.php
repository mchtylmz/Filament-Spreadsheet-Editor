<?php

namespace Mivento\FilamentSpreadsheetEditor\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CsvImportStore
{
    public function store(UploadedFile $file): string
    {
        $token = Str::uuid()->toString();
        $stored = $file->storeAs(
            'filament-spreadsheet-editor/imports',
            $token.'.csv',
            $this->disk(),
        );

        if ($stored === false) {
            throw new RuntimeException('The CSV import file could not be stored.');
        }

        return $token;
    }

    /**
     * @return resource
     */
    public function readStream(string $token)
    {
        $stream = Storage::disk($this->disk())->readStream($this->path($token));

        if (! is_resource($stream)) {
            throw new RuntimeException('The CSV import file could not be read.');
        }

        return $stream;
    }

    public function delete(string $token): void
    {
        Storage::disk($this->disk())->delete($this->path($token));
    }

    public function exists(string $token): bool
    {
        return $this->validToken($token)
            && Storage::disk($this->disk())->exists($this->path($token));
    }

    protected function path(string $token): string
    {
        if (! $this->validToken($token)) {
            throw new RuntimeException('Invalid CSV import token.');
        }

        return 'filament-spreadsheet-editor/imports/'.$token.'.csv';
    }

    protected function validToken(string $token): bool
    {
        return preg_match('/^[0-9a-f-]{36}$/i', $token) === 1;
    }

    protected function disk(): string
    {
        return (string) config('filament-spreadsheet-editor.import_disk', 'local');
    }
}
