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
    private int $version;

    public function __construct(int $version, array $payload)
    {
        $this->version = $version;
        $this->payload = $payload;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * {@inheritDoc}
     */
    public static function deserialize(array $payload): self
    {
        return new self($payload['version'], $payload['payload']);
    }

    /**
     * {@inheritDoc}
     */
    public function serialize(): array
    {
        return [
            'version' => $this->version,
            'payload' => $this->payload,
        ];
    }
}
