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
    protected array $events;

    /**
     * @param  Event[]  $events
     */
    public function setEvents(AggregateRoot $aggregate, array $events): void
    {
        $this->events[(string) $aggregate->getId()] = $this->buildStoredEvents($aggregate, $events);
    }

    /**
     * @return StoredEvent[]|iterable<StoredEvent>
     */
    public function retrieveAll(AggregateRoot $aggregate): iterable
    {
        return $this->events[(string) $aggregate->getId()];
    }

    /**
     * Stores all recorded events of the given aggregate.
     */
    public function persist(AggregateRoot $aggregate): void
    {
        array_push($this->events[(string) $aggregate->getId()], ...$aggregate->flushEvents());
    }

    /**
     * @param  Event[]|array<int,Event>  $events
     * @return StoredEvent[]
     */
    protected function buildStoredEvents(AggregateRoot $aggregate, iterable $events): array
    {
        $store = [];

        foreach ($events as $version => $event) {
            $store[] = new StoredEvent(
                $aggregate,
                $event,
                (int) $version,
                Carbon::now()->toImmutable(),
            );
        }

        return $store;
    }
}
