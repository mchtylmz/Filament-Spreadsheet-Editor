<?php

namespace Mivento\FilamentSpreadsheetEditor\Tests;

use Illuminate\Support\Facades\Schema;
use Mivento\FilamentSpreadsheetEditor\FilamentSpreadsheetEditorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('spreadsheet_cell_audits');
        Schema::dropIfExists('products');

        $auditMigration = require __DIR__.'/../database/migrations/create_spreadsheet_cell_audits_table.php.stub';
        $auditMigration->up();

        $productMigration = require __DIR__.'/Fixtures/database/migrations/create_products_table.php.stub';
        $productMigration->up();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilamentSpreadsheetEditorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
