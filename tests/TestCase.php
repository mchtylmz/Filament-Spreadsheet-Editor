<?php

namespace Mivento\FilamentSpreadsheetEditor\Tests;

use Mivento\FilamentSpreadsheetEditor\FilamentSpreadsheetEditorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilamentSpreadsheetEditorServiceProvider::class,
        ];
    }
}
