<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use Carbon\CarbonImmutable;

final class StoredEvent
{
    private AggregateRoot $aggregate;
    private Event $event;
    private int $version;
    private CarbonImmutable $persistedAt;

    public function __construct(AggregateRoot $aggregate, Event $event, int $version, CarbonImmutable $persistedAt)
    {
        $this->event = $event;
        $this->aggregate = $aggregate;
        $this->version = $version;
        $this->persistedAt = $persistedAt;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getAggregate(): AggregateRoot
    {
        return $this->aggregate;
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
