<?php

namespace Mivento\FilamentSpreadsheetEditor\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class FilamentTenantContext
{
    public static function current(): mixed
    {
        if (! class_exists(Filament::class)) {
            return null;
        }

        try {
            return Filament::getTenant();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{type: class-string<Model>|null, id: string|null}
     */
    public static function serialize(mixed $tenant = null): array
    {
        $tenant ??= self::current();

        if (! $tenant instanceof Model) {
            return [
                'type' => null,
                'id' => null,
            ];
        }

        return [
            'type' => $tenant::class,
            'id' => (string) $tenant->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    public static function restore(?array $context): mixed
    {
        $type = $context['type'] ?? null;
        $id = $context['id'] ?? null;

        if (! is_string($type) || ! is_subclass_of($type, Model::class) || $id === null || $id === '') {
            return null;
        }

        return $type::query()->whereKey($id)->first();
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    public static function matchesCurrent(?array $context): bool
    {
        $expected = self::normalize($context);
        $current = self::normalize(self::serialize());

        return $expected['type'] === $current['type']
            && $expected['id'] === $current['id'];
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @return array{type: string|null, id: string|null}
     */
    protected static function normalize(?array $context): array
    {
        return [
            'type' => is_string($context['type'] ?? null) ? $context['type'] : null,
            'id' => isset($context['id']) ? (string) $context['id'] : null,
        ];
    }
}
