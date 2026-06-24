<?php

use Illuminate\Support\Facades\Event;
use Mivento\FilamentSpreadsheetEditor\Events\SpreadsheetCellUpdating;
use Mivento\FilamentSpreadsheetEditor\Models\SpreadsheetCellAudit;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\Product;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\User;

function registeredAuditSpreadsheetEditor(): string
{
    return app(SpreadsheetEditorRegistry::class)->define(
        'audit-products',
        fn (): SpreadsheetEditor => SpreadsheetEditor::make()
            ->model(Product::class)
            ->columns([
                SpreadsheetColumn::make('price')->number()->min(0)->editable(),
                SpreadsheetColumn::make('stock')->integer()->min(0)->editable(),
                SpreadsheetColumn::make('secret_key')->text()->editable(),
            ])
            ->authorize(fn (?User $user): bool => $user !== null),
    );
}

function seedAuditProduct(): Product
{
    return Product::query()->create([
        'sku' => 'SKU-AUDIT',
        'name' => 'Audit Chair',
        'price' => 10,
        'stock' => 4,
        'secret_key' => 'old-secret',
    ]);
}

it('does not create audits when audit logging is disabled', function (): void {
    config()->set('filament-spreadsheet-editor.audit_enabled', false);

    $product = seedAuditProduct();

    $this
        ->actingAs(new User)
        ->postJson(route('filament-spreadsheet-editor.rows.update', [
            'token' => registeredAuditSpreadsheetEditor(),
        ]), [
            'changes' => [
                ['id' => $product->id, 'field' => 'stock', 'old' => 4, 'value' => 5],
            ],
        ])
        ->assertOk();

    expect(SpreadsheetCellAudit::query()->count())->toBe(0);
});

it('creates one audit per committed cell with shared batch metadata', function (): void {
    config()->set('filament-spreadsheet-editor.audit_enabled', true);

    $product = seedAuditProduct();
    $user = new User(['id' => 42]);

    $this
        ->actingAs($user)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('User-Agent', 'Spreadsheet Audit Test')
        ->postJson(route('filament-spreadsheet-editor.rows.update', [
            'token' => registeredAuditSpreadsheetEditor(),
        ]), [
            'changes' => [
                ['id' => $product->id, 'field' => 'price', 'old' => '10.00', 'value' => '12.50'],
                ['id' => $product->id, 'field' => 'stock', 'old' => 4, 'value' => 5],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', false);

    $audits = SpreadsheetCellAudit::query()->orderBy('id')->get();

    expect($audits)->toHaveCount(2)
        ->and($audits->pluck('batch_uuid')->unique())->toHaveCount(1)
        ->and($audits->pluck('field')->all())->toBe(['price', 'stock'])
        ->and($audits[0]->user_id)->toBe(42)
        ->and($audits[0]->model_type)->toBe($product->getMorphClass())
        ->and($audits[0]->model_id)->toBe((string) $product->getKey())
        ->and($audits[0]->old_value)->toEqual(10)
        ->and($audits[0]->new_value)->toEqual(12.5)
        ->and($audits[0]->ip_address)->toBe('203.0.113.10')
        ->and($audits[0]->user_agent)->toBe('Spreadsheet Audit Test')
        ->and($product->spreadsheetCellAudits()->count())->toBe(2);
});

it('rolls back audit rows when the batch update fails', function (): void {
    config()->set('filament-spreadsheet-editor.audit_enabled', true);

    $product = seedAuditProduct();

    Event::listen(SpreadsheetCellUpdating::class, function (SpreadsheetCellUpdating $event): void {
        if ($event->field === 'stock') {
            throw new RuntimeException('Stop audited update.');
        }
    });

    $this
        ->actingAs(new User)
        ->postJson(route('filament-spreadsheet-editor.rows.update', [
            'token' => registeredAuditSpreadsheetEditor(),
        ]), [
            'changes' => [
                ['id' => $product->id, 'field' => 'price', 'old' => '10.00', 'value' => '12.50'],
                ['id' => $product->id, 'field' => 'stock', 'old' => 4, 'value' => 5],
            ],
        ])
        ->assertStatus(500);

    expect(SpreadsheetCellAudit::query()->count())->toBe(0)
        ->and($product->refresh()->price)->toEqual(10)
        ->and($product->stock)->toBe(4);
});

it('redacts sensitive audit values by default', function (): void {
    config()->set('filament-spreadsheet-editor.audit_enabled', true);
    config()->set('filament-spreadsheet-editor.sensitive_fields', ['secret_key']);

    $product = seedAuditProduct();

    $this
        ->actingAs(new User)
        ->postJson(route('filament-spreadsheet-editor.rows.update', [
            'token' => registeredAuditSpreadsheetEditor(),
        ]), [
            'changes' => [
                ['id' => $product->id, 'field' => 'secret_key', 'old' => 'old-secret', 'value' => 'new-secret'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', false);

    $audit = SpreadsheetCellAudit::query()->firstOrFail();

    expect($audit->old_value)->toBe('[redacted]')
        ->and($audit->new_value)->toBe('[redacted]')
        ->and($product->refresh()->secret_key)->toBe('new-secret');
});

it('can explicitly keep sensitive audit values when redaction is disabled', function (): void {
    config()->set('filament-spreadsheet-editor.audit_enabled', true);
    config()->set('filament-spreadsheet-editor.sensitive_fields', ['secret_key']);
    config()->set('filament-spreadsheet-editor.audit.redact_sensitive_fields', false);

    $product = seedAuditProduct();

    $this
        ->actingAs(new User)
        ->postJson(route('filament-spreadsheet-editor.rows.update', [
            'token' => registeredAuditSpreadsheetEditor(),
        ]), [
            'changes' => [
                ['id' => $product->id, 'field' => 'secret_key', 'old' => 'old-secret', 'value' => 'new-secret'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', false);

    $audit = SpreadsheetCellAudit::query()->firstOrFail();

    expect($audit->old_value)->toBe('old-secret')
        ->and($audit->new_value)->toBe('new-secret');
});
