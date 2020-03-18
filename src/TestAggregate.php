<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\Assert;

use function get_class;

/**
 * Represents a wrapping class to make testing aggregate roots easier
 * without loosing type safety (so no usage of magic methods like
 * __call() to delegate calls to the instance we are testing.
 */
class TestAggregate
{
    /** @var StoredEvent[]|array<string,StoredEvent> */
    protected array $events;

    /**
     * Receives the given aggregate root to do some assertions
     * when creating events by calling methods on it.
     *
     * Usually, the aggregate has been created by using the rebuild()
     * method with any previous events ("given").
     */
    protected function __construct(AggregateRoot $aggregate)
    {
        $this->events = (new LazyCollection($aggregate->flushEvents()))
            ->map(static fn(StoredEvent $event): Event => $event->getEvent())
            ->groupBy(static fn(Event $event): string => get_class($event))
            ->toArray();
    }

    /**
     * Creates a list of stored events from the given list of regular events.
     *
     * @param AggregateId $id
     * @param Event[] $events
     * @return iterable|StoredEvent[]
     */
    public static function given(AggregateId $id, array $events): iterable
    {
        $version = 1;

        foreach ($events as $event) {
            yield new StoredEvent(
                $id,
                $event,
                $version,
                Carbon::now()->toImmutable()
            );

            $version++;
        }
    }

    /**
     * Convenience method for the constructor.
     */
    public static function for(AggregateRoot $aggregate): self
    {
        return new self($aggregate);
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
        Assert::assertArrayNotHasKey($eventClass, $this->events);

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
        Assert::assertContainsEquals($event, $this->events[get_class($event)]);

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
        Assert::assertArrayHasKey($event, $this->events);

        return $this;
    }
}
