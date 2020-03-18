<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use Closure;
use Illuminate\Support\Carbon;
use RuntimeException;
use Traversable;

use function get_class;

/**
 * The aggregate root is the source of truth. It holds the source data,
 * validates before modification and records any events.
 */
abstract class AggregateRoot
{
    /** @var array<string,array<string,callable|Closure>> */
    protected array $callableCache = [];

    /** @var StoredEvent[] */
    protected array $events = [];

    /**
     * Event though each event stores their version, we keep this counter,
     * so when they get flushed, the new events don't reset/overwrite it.
     */
    protected int $version = 0;

    /**
     * Locked constructor so we can call methods like "rebuild"
     * without having to worry about dependency resolution.
     */
    final protected function __construct()
    {
        // Nothing to do here
    }

    /**
     * Returns the unique identifier of this aggregate.
     */
    abstract public function getId(): AggregateId;

    /**
     * Returns the version number of this aggregate.
     *
     * This is used to track its number of changes
     * and helps detecting concurrency problems.
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
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     * @param  array  $snapshot
     */
    protected function applySnapshot(array $snapshot): void
    {
        // To be implemented by subclasses
    }

    /**
     * Builds an easy to store representation of the current state.
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint
     * @return array
     */
    protected function buildSnapshot(): array
    {
        throw new RuntimeException('Snapshots are not implemented by this aggregate.');
    }

    /**
     * Creates a new, storable snapshot instance and pushes it onto the event list.
     *
     * This also increases the internal version number so any further
     * changes don't try to overwrite this "snapshot version".
     */
    public function recordSnapshot(): self
    {
        return $this->record(new Snapshot($this->buildSnapshot()));
    }

    /**
     * Reconstitutes the current state according to the data
     * from the given event store. This is done by fetching
     * all past events and applying them in order.
     *
     * @param StoredEvent[] $events
     * @return $this
     */
    public static function rebuild(iterable $events): self
    {
        $instance = new static();

        foreach ($events as $event) {
            $instance->version = $event->getVersion();
            $instance->apply($event->getEvent());
        }

        return $instance;
    }

    /**
     * Stores the given event in local state and
     * increases the current version number.
     *
     * @return $this
     */
    protected function record(Event $event): self
    {
        $this->apply($event);

        $this->version++;

        $this->events[$this->version] = new StoredEvent(
            $this->getId(),
            $event,
            $this->version,
            Carbon::now()->toImmutable(),
        );

        return $this;
    }

    /**
     * Calls the registered handler method (if any) for the given event.
     * This is used to modify the local state.
     */
    protected function apply(Event $event): void
    {
        $callable = $this->getEventHandler($event);

        if ($callable === null) {
            return;
        }

        $callable($event);
    }

    /**
     * Determines the callable that's associated with the given event.
     * For performance reasons, this uses a per-instance cache
     * based on the result of getEventHandlers().
     */
    protected function getEventHandler(Event $event): ?callable
    {
        if (!isset($this->callableCache[static::class])) {
            $this->callableCache[static::class] = $this->getEventHandlers() + [
                Snapshot::class => function (Snapshot $event): void {
                    $this->applySnapshot($event->getPayload());
                },
            ];
        }

        return $this->callableCache[static::class][get_class($event)] ?? null;
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
}
