<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mivento\FilamentSpreadsheetEditor\Jobs\ProcessSpreadsheetCsvImport;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetColumn;
use Mivento\FilamentSpreadsheetEditor\SpreadsheetEditor;
use Mivento\FilamentSpreadsheetEditor\Support\SpreadsheetEditorRegistry;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\Product;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\User;

function registeredCsvImportEditor(bool $authorized = true): string
{
    $editor = SpreadsheetEditor::make()
        ->model(Product::class)
        ->columns([
            SpreadsheetColumn::make('sku')->unique(),
            SpreadsheetColumn::make('name')->required()->editable(),
            SpreadsheetColumn::make('price')->number()->min(0)->editable(),
            SpreadsheetColumn::make('stock')->integer()->min(0)->editable(),
        ])
        ->importUniqueColumn('sku')
        ->authorize(fn (?User $user): bool => $authorized && $user !== null);

    return app(SpreadsheetEditorRegistry::class)
        ->define('csv-import-products-'.($authorized ? 'allowed' : 'denied'), fn (): SpreadsheetEditor => $editor);
}

function previewCsvImport(object $test, string $token, string $contents): array
{
    return $test
        ->actingAs(new User)
        ->post(route('filament-spreadsheet-editor.csv.import.preview', ['token' => $token]), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $contents),
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', false)
        ->json();
}

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('filament-spreadsheet-editor.csv_import_enabled', true);
    config()->set('filament-spreadsheet-editor.import_disk', 'local');
    config()->set('filament-spreadsheet-editor.max_sync_import_rows', 100);

    Product::query()->create([
        'sku' => 'SKU-001',
        'name' => 'Old Chair',
        'price' => 10,
        'stock' => 4,
    ]);
});

it('requires editor authorization before previewing imports', function (): void {
    $token = registeredCsvImportEditor(authorized: false);

    $this
        ->actingAs(new User)
        ->post(route('filament-spreadsheet-editor.csv.import.preview', ['token' => $token]), [
            'file' => UploadedFile::fake()->createWithContent(
                'products.csv',
                "sku,name\nSKU-001,Updated\n",
            ),
        ])
        ->assertForbidden();
});

it('previews twenty rows and applies updates by configured unique column', function (): void {
    $token = registeredCsvImportEditor();
    $preview = previewCsvImport(
        $this,
        $token,
        "sku,name,price,stock\nSKU-001,Updated Chair,12.50,5\n",
    );

    expect($preview['headers'])->toBe(['sku', 'name', 'price', 'stock'])
        ->and($preview['preview'])->toHaveCount(1)
        ->and($preview['suggested_mapping'])->toMatchArray([
            'sku' => 'sku',
            'name' => 'name',
            'price' => 'price',
            'stock' => 'stock',
        ]);

    $this
        ->actingAs(new User)
        ->postJson(route('filament-spreadsheet-editor.csv.import.apply', ['token' => $token]), [
            'import_token' => $preview['import_token'],
            'mapping' => $preview['suggested_mapping'],
            'match_by' => 'unique',
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', false)
        ->assertJsonPath('applied', true)
        ->assertJsonPath('updated_rows', 1);

    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();

    expect($product->name)->toBe('Updated Chair')
        ->and($product->price)->toEqual(12.50)
        ->and($product->stock)->toBe(5);
});

it('escapes imported text values that could be interpreted as spreadsheet formulas', function (): void {
    $token = registeredCsvImportEditor();
    $preview = previewCsvImport(
        $this,
        $token,
        "sku,name,price,stock\nSKU-001,\"=HYPERLINK(\"\"https://example.com\"\")\",12.50,5\n",
    );

    $this
        ->actingAs(new User)
        ->postJson(route('filament-spreadsheet-editor.csv.import.apply', ['token' => $token]), [
            'import_token' => $preview['import_token'],
            'mapping' => $preview['suggested_mapping'],
            'match_by' => 'unique',
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', false)
        ->assertJsonPath('applied', true);

    expect(Product::query()->where('sku', 'SKU-001')->value('name'))
        ->toBe("'=HYPERLINK(\"https://example.com\")");
});

it('limits previews to twenty rows', function (): void {
    $token = registeredCsvImportEditor();
    $rows = collect(range(1, 21))
        ->map(fn (int $index): string => "SKU-{$index},Product {$index},{$index},{$index}")
        ->implode("\n");
    $preview = previewCsvImport(
        $this,
        $token,
        "sku,name,price,stock\n{$rows}\n",
    );

    expect($preview['total_rows'])->toBe(21)
        ->and($preview['preview'])->toHaveCount(20);
});

it('applies updates by the model primary key', function (): void {
    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    $token = registeredCsvImportEditor();
    $preview = previewCsvImport(
        $this,
        $token,
        "id,name,price,stock\n{$product->id},Primary Updated,14,6\n",
    );

    $this
        ->actingAs(new User)
        ->postJson(route('filament-spreadsheet-editor.csv.import.apply', ['token' => $token]), [
            'import_token' => $preview['import_token'],
            'mapping' => $preview['suggested_mapping'],
            'match_by' => 'primary',
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', false)
        ->assertJsonPath('applied', true);

    expect($product->refresh()->name)->toBe('Primary Updated')
        ->and($product->stock)->toBe(6);
});

it('returns row level validation errors without applying valid sibling values', function (): void {
    $token = registeredCsvImportEditor();
    $preview = previewCsvImport(
        $this,
        $token,
        "sku,name,price,stock\nSKU-001,Updated Chair,-5,7\n",
    );

    $this
        ->actingAs(new User)
        ->postJson(route('filament-spreadsheet-editor.csv.import.apply', ['token' => $token]), [
            'import_token' => $preview['import_token'],
            'mapping' => $preview['suggested_mapping'],
            'match_by' => 'unique',
        ])
        ->assertOk()
        ->assertJsonPath('has_errors', true)
        ->assertJsonPath('applied', false)
        ->assertJsonPath('row_errors.0.line', 2)
        ->assertJsonPath('row_errors.0.field', 'price')
        ->assertJsonPath('row_errors.0.status', 'validation_error');

    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();

    expect($product->name)->toBe('Old Chair')
        ->and($product->price)->toEqual(10)
        ->and($product->stock)->toBe(4);
});

it('queues imports that exceed the synchronous row limit', function (): void {
    Queue::fake();
    config()->set('filament-spreadsheet-editor.max_sync_import_rows', 1);

    $token = registeredCsvImportEditor();
    $preview = previewCsvImport(
        $this,
        $token,
        "sku,name,price,stock\nSKU-001,Updated Chair,12,5\nSKU-002,Lamp,9,2\n",
    );

    $this
        ->actingAs(new User)
        ->postJson(route('filament-spreadsheet-editor.csv.import.apply', ['token' => $token]), [
            'import_token' => $preview['import_token'],
            'mapping' => $preview['suggested_mapping'],
            'match_by' => 'unique',
            'queue' => true,
        ])
        ->assertOk()
        ->assertJsonPath('queued', true)
        ->assertJsonPath('total_rows', 2);

    Queue::assertPushed(ProcessSpreadsheetCsvImport::class);
});
