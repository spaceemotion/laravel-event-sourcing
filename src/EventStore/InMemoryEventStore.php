<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\EventStore;

use Spaceemotion\LaravelEventSourcing\AggregateId;
use Spaceemotion\LaravelEventSourcing\AggregateRoot;
use Spaceemotion\LaravelEventSourcing\Event;
use Spaceemotion\LaravelEventSourcing\StoredEvent;

use function array_push;

class InMemoryEventStore implements EventStore
{
    /** @var StoredEvent[][]|array<string|StoredEvent[]> */
    protected $events;

    /**
     * @param  AggregateId  $id
     * @param  Event[]  $events
     */
    public function setEvents(AggregateId $id, array $events): void
    {
        $this->events[(string) $id] = $this->buildStoredEvents($events);
    }

    /**
     * @param  AggregateRoot  $aggregate
     * @return StoredEvent[]|iterable<StoredEvent>
     */
    public function retrieveAll(AggregateRoot $aggregate): iterable
    {
        return $this->events[(string) $aggregate->getId()];
    }

    /**
     * Stores all recorded events of the given aggregate.
     *
     * @param  AggregateRoot  $aggregate
     */
    public function persist(AggregateRoot $aggregate): void
    {
        $events = $this->buildStoredEvents($aggregate->flushEvents());

        array_push($this->events[(string) $aggregate->getId()], ...$events);
    }

    /**
     * @param  Event[]|array<int,Event>  $events
     * @return array
     */
    protected function buildStoredEvents(iterable $events): array
    {
        $store = [];

        foreach ($events as $version => $event) {
            $storedEvent = new StoredEvent();
            $storedEvent->event = $event;
            $storedEvent->version = (int) $version;

            $store[] = $storedEvent;
        }

        return $store;
    }
}
