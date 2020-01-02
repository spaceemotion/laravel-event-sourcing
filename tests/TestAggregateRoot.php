<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use Spaceemotion\LaravelEventSourcing\AggregateRoot;
use Spaceemotion\LaravelEventSourcing\Ids\Uuid;

class TestAggregateRoot extends AggregateRoot
{
    public array $state;

    public static function new(): self
    {
        return static::forId(Uuid::next());
    }

    /**
     * {@inheritDoc}
     */
    protected function applySnapshot(array $snapshot): void
    {
        $this->state = $snapshot;
    }

    /**
     * {@inheritDoc}
     */
    protected function buildSnapshot(): array
    {
        return $this->state;
    }

    /**
     * {@inheritDoc}
     */
    public function set(array $state): self
    {
        return $this->record(new TestEvent($state));
    }

    /**
     * {@inheritDoc}
     */
    protected function getEventHandlers(): array
    {
        return [
            TestEvent::class => function (TestEvent $event): void {
                $this->state = $event->attributes;
            },
        ];
    }
}
