<?php

namespace Mivento\FilamentSpreadsheetEditor\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CsvImportStore
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function store(UploadedFile $file, array $metadata = []): string
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

        $this->putMetadata($token, [
            ...$metadata,
            'created_at' => now()->toISOString(),
            'expires_at' => now()
                ->addMinutes(max(1, (int) config('filament-spreadsheet-editor.import_ttl_minutes', 60)))
                ->toISOString(),
            'consumed' => false,
        ]);

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
        Storage::disk($this->disk())->delete([
            $this->path($token),
            $this->metadataPath($token),
        ]);
    }

    public function exists(string $token): bool
    {
        return $this->validToken($token)
            && Storage::disk($this->disk())->exists($this->path($token));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function metadata(string $token): ?array
    {
        if (! $this->validToken($token) || ! Storage::disk($this->disk())->exists($this->metadataPath($token))) {
            return null;
        }

        $contents = Storage::disk($this->disk())->get($this->metadataPath($token));
        $metadata = json_decode($contents, true);

        return is_array($metadata) ? $metadata : null;
    }

    public function markConsumed(string $token): void
    {
        $metadata = $this->metadata($token);

        if ($metadata === null) {
            return;
        }

        $this->putMetadata($token, [
            ...$metadata,
            'consumed' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function putMetadata(string $token, array $metadata): void
    {
        if (! $this->validToken($token)) {
            throw new RuntimeException('Invalid CSV import token.');
        }

        Storage::disk($this->disk())->put(
            $this->metadataPath($token),
            json_encode($metadata, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
        );
    }

    protected function path(string $token): string
    {
        if (! $this->validToken($token)) {
            throw new RuntimeException('Invalid CSV import token.');
        }

        return 'filament-spreadsheet-editor/imports/'.$token.'.csv';
    }

    protected function metadataPath(string $token): string
    {
        if (! $this->validToken($token)) {
            throw new RuntimeException('Invalid CSV import token.');
        }

        return 'filament-spreadsheet-editor/imports/'.$token.'.json';
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
