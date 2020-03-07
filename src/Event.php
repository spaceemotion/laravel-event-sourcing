<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

interface Event
{
    /**
     * Returns an object that can be stored (as JSON).
     */
    public function serialize(): array;

    /**
     * Recreates the event instance from the serialized data.
     *
     * @return static|Event
     */
    public static function deserialize(array $payload): self;
}
