<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

/**
 * Snapshots are containers for the full state of an aggregate
 * so they don't have to load and apply all events on every
 * load/read. They can hold any kind of (nested) data.
 */
final class Snapshot implements Event
{
    private array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * {@inheritDoc}
     */
    public static function deserialize(array $payload): self
    {
        return new self($payload);
    }

    /**
     * {@inheritDoc}
     */
    public function serialize(): array
    {
        return $this->payload;
    }
}
