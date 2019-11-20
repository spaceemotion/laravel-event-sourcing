<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\EventStore;

use Illuminate\Support\Carbon;
use Spaceemotion\LaravelEventSourcing\AggregateRoot;
use Spaceemotion\LaravelEventSourcing\Event;
use Spaceemotion\LaravelEventSourcing\StoredEvent;

use function array_push;

class InMemoryEventStore implements EventStore
{
    /** @var StoredEvent[][]|array<string|StoredEvent[]> */
    protected $events;

    /**
     * @param  AggregateRoot  $aggregate
     * @param  Event[]  $events
     */
    public function setEvents(AggregateRoot $aggregate, array $events): void
    {
        $this->events[(string) $aggregate->getId()] = $this->buildStoredEvents($aggregate, $events);
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
        array_push($this->events[(string) $aggregate->getId()], ...$aggregate->flushEvents());
    }

    /**
     * @param  AggregateRoot  $aggregate
     * @param  Event[]|array<int,Event>  $events
     * @return array
     */
    protected function buildStoredEvents(AggregateRoot $aggregate, iterable $events): array
    {
        $store = [];

        foreach ($events as $version => $event) {
            $store[] = new StoredEvent(
                $aggregate,
                $event,
                (int) $version,
                Carbon::now(),
            );
        }

        return $store;
    }
}
