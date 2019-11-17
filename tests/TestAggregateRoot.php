<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use Spaceemotion\LaravelEventSourcing\AggregateRoot;
use Spaceemotion\LaravelEventSourcing\Ids\Uuid;

class TestAggregateRoot extends AggregateRoot
{
    /** @var array */
    public $state;

    public static function new(): self
    {
        return static::forId(Uuid::next());
    }

    public function set(array $state): self
    {
        return $this->record(new TestEvent($state));
    }

    protected function getEventHandlers(): array
    {
        return [
            TestEvent::class => function (TestEvent $event) {
                $this->state = $event->attributes;
            },
        ];
    }
}
