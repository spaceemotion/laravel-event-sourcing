<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\EventStore;

use Spaceemotion\LaravelEventSourcing\AggregateId;
use Spaceemotion\LaravelEventSourcing\AggregateRoot;
use Spaceemotion\LaravelEventSourcing\Event;
use Spaceemotion\LaravelEventSourcing\StoredEvent;

use function array_map;

class InMemoryEventStore implements EventStore
{
    /** @var array<string,iterable<Event>> */
    protected array $events = [];

    /**
     * @return StoredEvent[]|iterable<Event>
     */
    public function retrieveAll(AggregateId $id): iterable
    {
        return $this->events[(string) $id];
    }

    /**
     * Stores all recorded events of the given aggregate.
     */
    public function persist(AggregateRoot $aggregate): void
    {
        $this->events[(string) $aggregate->getId()] = array_map(
            static fn (StoredEvent $event) => $event->getEvent(),
            [...$aggregate->flushEvents()],
        );
    }
}
