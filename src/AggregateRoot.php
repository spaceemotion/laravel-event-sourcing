<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use Closure;
use Illuminate\Support\Carbon;
use RuntimeException;
use Spaceemotion\LaravelEventSourcing\EventStore\EventStore;
use Spaceemotion\LaravelEventSourcing\EventStore\SnapshotEventStore;
use Traversable;

use function get_class;

/**
 * The aggregate root is the source of truth. It holds the source data,
 * validates before modification and records any events.
 */
class AggregateRoot
{
    /** @var array<string,array<string,callable|Closure>> */
    protected array $callableCache = [];

    /** @var StoredEvent[] */
    protected array $events = [];

    /** @var int */
    protected int $version = 0;

    /** @var AggregateId */
    protected AggregateId $id;

    /**
     * Locked constructor so we can call methods like "forId"
     * without having to worry about dependency resolution.
     */
    final public function __construct()
    {
        // Nothing to do here
    }

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
    protected function buildSnapshot(): array
    {
        throw new RuntimeException('Snapshots are not implemented by this aggregate.');
    }

    /**
     * Creates a new, storable snapshot instance.
     *
     * This also increases the internal version number so any further
     * changes don't try to overwrite this "snapshot version".
     *
     * @return StoredEvent
     */
    public function newSnapshot(): StoredEvent
    {
        return new StoredEvent(
            $this,
            Snapshot::fromJson($this->buildSnapshot()),
            $this->version++,
            Carbon::now(),
        );
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
            $this->apply($event->getEvent());

            $this->version = $event->getVersion();
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
            // jsonSerialize() just gives back the payload, there's no conversion happening
            $this->applySnapshot($snapshot->getEvent()->jsonSerialize());

            // increase version to not overwrite the snapshot in future saves
            $this->version = $snapshot->getVersion() + 1;
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

        $this->events[$this->version] = new StoredEvent(
            $this,
            $event,
            $this->version,
            Carbon::now(),
        );

        $this->version++;

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
     * @return StoredEvent[]|Traversable|array<int,StoredEvent>
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
