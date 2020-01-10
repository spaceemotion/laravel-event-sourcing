<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Spaceemotion\LaravelEventSourcing\ClassMapper\ConfigurableEventClassMapper;
use Spaceemotion\LaravelEventSourcing\ClassMapper\EventClassMapper;

class ServiceProvider extends LaravelServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-event-sourcing.php' => config_path('laravel-event-sourcing.php'),
        ]);

        $this->loadMigrationsFrom(__DIR__ . '/../migrations');
    }

    public function register(): void
    {
        $this->app->singleton(EventClassMapper::class, static fn(): EventClassMapper => (
            new ConfigurableEventClassMapper(config('laravel-event-sourcing.event_class', []))
        ));

        $this->app->singleton(EventDispatcher::class);
    }
}
