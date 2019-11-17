<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use JsonSerializable;

interface Event extends JsonSerializable
{
    /**
     * Recreates the event instance from the serialized data.
     *
     * @param  array  $payload
     * @return static
     */
    public static function fromJson(array $payload): self;
}