<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\EventStore;

use Spaceemotion\LaravelEventSourcing\AggregateId;
use Spaceemotion\LaravelEventSourcing\Event;

/**
 * Snapshots allow storing the full current state of an aggregate
 * without having to replay all of its past events.
 */
interface SnapshotEventStore
{
    public const EVENT_TYPE_SNAPSHOT = 'snapshot';

    /**
     * Returns the last stored snapshot for the given aggregate.
     *
     * @return Event[]|iterable<Event>
     */
    public function retrieveFromLastSnapshot(AggregateId $id): iterable;
}
