<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use Carbon\CarbonImmutable;

final class StoredEvent
{
    private AggregateId $aggregateId;
    private Event $event;
    private int $version;
    private CarbonImmutable $persistedAt;


    public function __construct(AggregateId $aggregateId, Event $event, int $version, CarbonImmutable $persistedAt)
    {
        $this->event = $event;
        $this->aggregateId = $aggregateId;
        $this->version = $version;
        $this->persistedAt = $persistedAt;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getAggregateId(): AggregateId
    {
        return $this->aggregateId;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getPersistedAt(): CarbonImmutable
    {
        return $this->persistedAt;
    }
}
