<?php

namespace Mivento\FilamentSpreadsheetEditor\Jobs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Mivento\FilamentSpreadsheetEditor\Actions\ApplySpreadsheetCsvImport;
use Mivento\FilamentSpreadsheetEditor\Support\FilamentTenantContext;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;
use RuntimeException;

class ProcessSpreadsheetCsvImport implements ShouldQueue
{
    /**
     * @param  array<string, string>  $mapping
     * @param  array{type?: string|null, id?: string|null}|null  $user
     * @param  array{type?: string|null, id?: string|null}|null  $tenant
     * @param  array{ip?: string|null, user_agent?: string|null}  $requestContext
     */
    public function __construct(
        public string $editorToken,
        public string $importToken,
        public array $mapping,
        public string $matchBy,
        public ?array $user = null,
        public ?array $tenant = null,
        public array $requestContext = [],
    ) {
        //
    }

    public function handle(
        SpreadsheetEditorRegistry $registry,
        ApplySpreadsheetCsvImport $import,
    ): void {
        $editor = $registry->get($this->editorToken);

        if ($editor === null) {
            throw new RuntimeException('The queued spreadsheet editor is not registered.');
        }

        $user = $this->restoreUser();

        if (! $editor->isAuthorized($user)) {
            throw new RuntimeException('The queued CSV import is no longer authorized.');
        }

        $tenant = FilamentTenantContext::restore($this->tenant);

        if ($this->tenant !== null && $tenant === null) {
            throw new RuntimeException('The queued CSV import tenant could not be restored.');
        }

        $request = Request::create(
            '/',
            'POST',
            server: array_filter([
                'REMOTE_ADDR' => $this->requestContext['ip'] ?? null,
                'HTTP_USER_AGENT' => $this->requestContext['user_agent'] ?? null,
            ], fn (mixed $value): bool => $value !== null),
        );

        $result = $import->applyStored(
            $editor,
            $this->importToken,
            $this->mapping,
            $this->matchBy,
            $user,
            $request,
            $tenant,
        );

        if ($result['has_errors']) {
            throw new RuntimeException('The queued CSV import failed validation.');
        }
    }

    protected function restoreUser(): ?Authenticatable
    {
        $type = $this->user['type'] ?? null;
        $id = $this->user['id'] ?? null;

        if (! is_string($type) || ! is_subclass_of($type, Model::class) || $id === null || $id === '') {
            return null;
        }

        $user = $type::query()->whereKey($id)->first();

        return $user instanceof Authenticatable ? $user : null;
    }
}
