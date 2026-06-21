<?php

namespace Mivento\FilamentSpreadsheetEditor\Support;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mivento\FilamentSpreadsheetEditor\Builders\SpreadsheetEditor;

class SpreadsheetEditorRegistry
{
    /** @var array<string, SpreadsheetEditor> */
    protected array $editors = [];

    /** @var array<string, Closure(): SpreadsheetEditor> */
    protected array $resolvers = [];

    /** @var array<string, string> */
    protected array $keysByToken = [];

    /** @var array<int, string> */
    protected array $tokensByObjectId = [];

    public function register(SpreadsheetEditor $editor, ?string $key = null): string
    {
        $objectId = spl_object_id($editor);

        if (isset($this->tokensByObjectId[$objectId])) {
            return $this->tokensByObjectId[$objectId];
        }

        $token = $this->tokenFor($key ?? Str::uuid()->toString());

        $this->editors[$token] = $editor;
        $this->tokensByObjectId[$objectId] = $token;

        return $token;
    }

    public function tokenForEditor(SpreadsheetEditor $editor): ?string
    {
        return $this->tokensByObjectId[spl_object_id($editor)] ?? null;
    }

    /**
     * Register a resolver during application boot so the editor can be rebuilt
     * for each HTTP request without serializing models or callbacks.
     *
     * @param  Closure(): SpreadsheetEditor  $resolver
     */
    public function define(string $key, Closure $resolver): string
    {
        $token = $this->tokenFor($key);

        $this->forget($token);
        $this->resolvers[$token] = $resolver;
        $this->keysByToken[$token] = $key;

        return $token;
    }

    public function get(string $token): ?SpreadsheetEditor
    {
        if (isset($this->editors[$token])) {
            return $this->editors[$token];
        }

        if (! isset($this->resolvers[$token])) {
            return null;
        }

        $editor = ($this->resolvers[$token])();

        if (! $editor instanceof SpreadsheetEditor) {
            throw new InvalidArgumentException('Spreadsheet editor registry resolvers must return a SpreadsheetEditor instance.');
        }

        $this->register($editor, $this->keysByToken[$token]);

        return $editor;
    }

    public function editor(string $key): SpreadsheetEditor
    {
        $editor = $this->get($this->tokenFor($key));

        if ($editor === null) {
            throw new InvalidArgumentException("Spreadsheet editor [{$key}] is not defined.");
        }

        return $editor;
    }

    public function has(string $token): bool
    {
        return $this->get($token) !== null;
    }

    public function forget(string $token): void
    {
        if (isset($this->editors[$token])) {
            unset($this->tokensByObjectId[spl_object_id($this->editors[$token])]);
        }

        unset(
            $this->editors[$token],
            $this->resolvers[$token],
            $this->keysByToken[$token],
        );
    }

    protected function tokenFor(string $key): string
    {
        $secret = (string) config('app.key', 'filament-spreadsheet-editor');

        return hash_hmac('sha256', $key, $secret);
    }
}
