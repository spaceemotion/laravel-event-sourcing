<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use Spaceemotion\LaravelEventSourcing\ServiceProvider;

use function config;

class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * {@inheritDoc}
     * @return string[]
     */
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    /**
     * {@inheritDoc}
     */
    protected function getEnvironmentSetUp($app): void
    {
        config(['laravel-event-sourcing.event_class' => [
            'test' => TestEvent::class,
            'created' => TestCreatedEvent::class,
        ]]);
    }
}
