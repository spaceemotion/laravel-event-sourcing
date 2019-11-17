<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use Illuminate\Support\Carbon;

final class StoredEvent
{
    /** @var AggregateRoot */
    private $aggregate;

    /** @var Event */
    private $event;

    /** @var int */
    private $version;

    /** @var Carbon */
    private $persistedAt;

    public function __construct(AggregateRoot $aggregate, Event $event, int $version, Carbon $persistedAt)
    {
        $this->event = $event;
        $this->aggregate = $aggregate;
        $this->version = $version;
        $this->persistedAt = $persistedAt->toImmutable();
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

    public function getPersistedAt(): Carbon
    {
        return $this->persistedAt;
    }
}
