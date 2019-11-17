<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use Closure;
use RuntimeException;
use Spaceemotion\LaravelEventSourcing\EventStore\EventStore;
use Spaceemotion\LaravelEventSourcing\EventStore\SnapshotEventStore;
use Traversable;

/**
 * The aggregate root is the source of truth. It holds the source data,
 * validates before modification and records any events.
 */
class AggregateRoot
{
    /** @var array<string,array<string,callable|Closure>> */
    protected $callableCache = [];

    /** @var Event[] */
    protected $events = [];

    /** @var int */
    protected $version = 0;

    /** @var AggregateId */
    protected $id;

    /**
     * Returns the unique identifier of this aggregate.
     *
     * @return AggregateId
     */
    public function getId(): AggregateId
    {
        return $this->id;
    }

    /**
     * Returns the version number of this aggregate.
     *
     * This is used to track its number of changes
     * and helps detecting concurrency problems.
     *
     * @return int
     */
    public function getCurrentVersion(): int
    {
        return $this->version;
    }

    /**
     * Returns a mapping of all methods that handle events
     * raised by this aggregate using the record() method.
     *
     * @return callable[]|array<string,callable|Closure>
     */
    protected function getEventHandlers(): array
    {
        return [];
    }

    /**
     * Replaces the current state with the data from the given snapshot.
     *
     * @param  array  $snapshot
     */
    protected function applySnapshot(array $snapshot): void
    {
        // To be implemented by subclasses
    }

    /**
     * Builds an easy to store representation of the current state.
     *
     * @return array
     */
    public function buildSnapshot(): array
    {
        throw new RuntimeException('Snapshots are not implemented by this aggregate.');
    }

    /**
     * Reconstitutes the current state according to the data
     * from the given event store. This is done by fetching
     * all past events and applying them in order.
     *
     * @param  EventStore  $store
     * @return $this
     */
    public function rebuild(EventStore $store): self
    {
        foreach ($store->retrieveAll($this) as $event) {
            $this->apply($event->event);

            $this->version = $event->version;
        }

        return $this;
    }

    /**
     * Reconstitutes the current state based on a snapshot.
     * Afterwards, the cached state will be updated
     * by any events that have been stored since.
     *
     * @param  SnapshotEventStore  $store
     * @return $this
     */
    public function rebuildFromSnapshot(SnapshotEventStore $store): self
    {
        $snapshot = $store->retrieveLastSnapshot($this);

        if ($snapshot !== null) {
            $this->applySnapshot((array) $snapshot->event);

            $this->version = $snapshot->version;
        }

        return $this->rebuild($store);
    }

    /**
     * Stores the given event in local state and
     * increases the current version number.
     *
     * @param  Event  $event
     * @return $this
     */
    public function record(Event $event): self
    {
        $this->apply($event);

        $this->events[++$this->version] = $event;

        return $this;
    }

    /**
     * Calls the registered handler method (if any) for the given event.
     * This is used to modify the local state.
     *
     * @param  Event  $event
     */
    protected function apply(Event $event): void
    {
        $callable = $this->getHandlingCallable($event);

        if ($callable === null) {
            return;
        }

        $callable($event);
    }

    /**
     * Determines the callable that's associated with the given event.
     * For performance reasons, this uses a per-instance cache
     * based on the result of getEventHandlers().
     *
     * @param  Event  $event
     * @return callable|null
     */
    protected function getHandlingCallable(Event $event): ?callable
    {
        $handlers = $this->callableCache[static::class] ?? (
            $this->callableCache[static::class] = $this->getEventHandlers()
        );

        return $handlers[get_class($event)] ?? null;
    }

    /**
     * Drains the stored event list.
     * (The keys represent the versions when each event got recorded).
     *
     * @return Event[]|Traversable|array<int,Event>
     */
    public function flushEvents(): iterable
    {
        yield from $this->events;

        $this->events = [];
    }

    /**
     * Creates a new instance for the given aggregate ID.
     * This does not load any existing data yet.
     *
     * @param  AggregateId  $id
     * @return static
     */
    public static function forId(AggregateId $id): self
    {
        $instance = new static();
        $instance->id = $id;

        return $instance;
    }
}