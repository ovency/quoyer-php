<?php

declare(strict_types=1);

namespace Quoyer\Tests\Laravel;

use Orchestra\Testbench\TestCase as Orchestra;
use Quoyer\Laravel\Facades\Quoyer;
use Quoyer\Laravel\QuoyerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [QuoyerServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Quoyer' => Quoyer::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('quoyer.api_key', 'quoy_sandbox_laravel.secret');
        $app['config']->set('quoyer.base_url', 'https://quoyer.example');
        $app['config']->set('quoyer.webhook.secret', 'whsec_laravel');
    }
}
