<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use Spaceemotion\LaravelEventSourcing\ServiceProvider;

use function config;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        config(['laravel-event-sourcing.event_class' => [
            'test' => TestEvent::class,
        ]]);
    }
}
