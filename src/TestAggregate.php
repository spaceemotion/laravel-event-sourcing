<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\Assert;
use Spaceemotion\LaravelEventSourcing\EventStore\InMemoryEventStore;

/**
 * Represents a wrapping class to make testing aggregate roots easier
 * without loosing type safety (so no usage of magic methods like
 * __call() to delegate calls to the instance we are testing.
 */
class TestAggregate
{
    protected AggregateRoot $aggregate;

    /** @var StoredEvent[]|array<string,StoredEvent>|null */
    protected ?array $events = null;

    public function __construct(AggregateRoot $aggregate)
    {
        $this->aggregate = $aggregate;
    }

    /**
     * Rebuilds the given aggregate using the provided list of events.
     *
     * @return $this
     */
    public function given(Event ...$events): self
    {
        $repository = new InMemoryEventStore();
        $repository->setEvents($this->aggregate, $events);

        $this->aggregate->rebuild($repository);

        return $this;
    }

    /**
     * Calls the given callback and stores the recorded events
     * afterwards for later use by the assertion methods.
     *
     * @return $this
     */
    public function when(callable $callback): self
    {
        $callback($this->aggregate);

        return $this;
    }

    /**
     * Checks if the recorded events are empty.
     *
     * @return $this
     */
    public function assertNothingRecorded(): self
    {
        Assert::assertEmpty($this->events);

        return $this;
    }

    /**
     * Checks if the given event type has not been recorded during the when() call.
     *
     * @return $this
     */
    public function assertNotRecorded(string $eventClass): self
    {
        Assert::assertArrayNotHasKey($eventClass, $this->getRecordedEvents());

        return $this;
    }

    /**
     * Checks if the given event type has been recorded during the when() call
     * and invokes the optional callback with the recorded instance so
     * some further testing can be done with the event itself.
     *
     *  @return $this
     */
    public function assertRecordedInstance(Event $event): self
    {
        Assert::assertContainsEquals($event, $this->getRecordedEvents()[get_class($event)]);

        return $this;
    }

    /**
     * Checks if the given event type has been recorded during the when() call.
     * Since the same event type could have been recorded multiple types
     * this only checks for an occurrence of at least once.
     *
     * @return $this
     */
    public function assertRecorded(string $event): self
    {
        Assert::assertArrayHasKey($event, $this->getRecordedEvents());

        return $this;
    }

    /**
     * Returns the list of recorded events on the aggregate.
     * They're grouped by their event class.
     *
     * @return StoredEvent[][]|array<string,StoredEvent[]>
     */
    protected function getRecordedEvents(): array
    {
        if ($this->events === null) {
            $this->events = (new LazyCollection($this->aggregate->flushEvents()))
                ->map(static fn(StoredEvent $event): Event => $event->getEvent())
                ->groupBy(static fn(Event $event): string => get_class($event))
                ->toArray();
        }

        return $this->events;
    }
}
