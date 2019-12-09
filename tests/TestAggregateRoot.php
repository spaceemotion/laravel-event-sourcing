<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use Spaceemotion\LaravelEventSourcing\AggregateRoot;
use Spaceemotion\LaravelEventSourcing\Ids\Uuid;

class TestAggregateRoot extends AggregateRoot
{
    /** @var array */
    public array $state;

    public static function new(): self
    {
        return static::forId(Uuid::next());
    }

    protected function applySnapshot(array $snapshot): void
    {
        $this->state = $snapshot;
    }

    protected function buildSnapshot(): array
    {
        return $this->state;
    }

    public function fresh(): self
    {
        return self::forId($this->getId());
    }

    public function set(array $state): self
    {
        return $this->record(new TestEvent($state));
    }

    protected function getEventHandlers(): array
    {
        return [
            TestEvent::class => function (TestEvent $event): void {
                $this->state = $event->attributes;
            },
        ];
    }
}
