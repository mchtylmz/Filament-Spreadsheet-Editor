# Development Rules

- Keep the package compatible with PHP 8.2+, Laravel 11/12, Filament v5, and Livewire v4.
- Prefer small, contract-driven additions over feature-heavy changes.
- Keep grid-specific logic behind `Mivento\FilamentSpreadsheetEditor\Contracts\GridAdapter`.
- Treat Tabulator as the default adapter, but do not couple public APIs to Tabulator-only concepts.
- Add Pest coverage for service provider behavior, plugin registration, and adapter contracts as features land.
- Do not add migrations unless a feature clearly needs package-owned database tables.
- Use Vite-compatible frontend entry points in `resources/js` and `resources/css`.
- Keep README examples current with the public API.
