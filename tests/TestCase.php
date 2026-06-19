<?php

namespace Mivento\FilamentSpreadsheetEditor\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mivento\FilamentSpreadsheetEditor\FilamentSpreadsheetEditorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('products');

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->boolean('active')->default(true);
            $table->string('category')->nullable();
            $table->decimal('internal_cost', 10, 2)->nullable();
            $table->timestamps();
        });
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
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
