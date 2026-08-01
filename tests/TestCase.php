<?php

declare(strict_types=1);

namespace Thijssensoftware\RequestId\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Thijssensoftware\RequestId\RequestIdServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [RequestIdServiceProvider::class];
    }
}
