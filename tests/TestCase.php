<?php

declare(strict_types=1);

namespace Stringer\Laravel\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Stringer\Laravel\StringerServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            StringerServiceProvider::class,
        ];
    }
}
