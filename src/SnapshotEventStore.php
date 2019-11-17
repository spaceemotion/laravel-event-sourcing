<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

/**
 * Snapshots allow storing the full current state of an aggregate
 * without having to replay all of its past events.
 */
interface SnapshotEventStore extends EventStore
{
    /**
     * Returns the last stored snapshot for the given aggregate.
     *
     * @param  AggregateRoot  $aggregate
     * @return StoredEvent|null
     */
    public function retrieveLastSnapshot(AggregateRoot $aggregate): ?StoredEvent;

    /**
     * Stores the current state of the given aggregate in a snapshot.
     * This saves the current version to know which to resume from.
     *
     * @param  AggregateRoot  $aggregate
     */
    public function persistSnapshot(AggregateRoot $aggregate): void;
}
