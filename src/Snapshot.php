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

    /**
     * {@inheritDoc}
     */
    public static function fromJson(array $payload): self
    {
        $instance = new self();
        $instance->payload = $payload;

        return $instance;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->payload;
    }
}
