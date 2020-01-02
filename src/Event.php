<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use JsonSerializable;

interface Event extends JsonSerializable
{
    /**
     * Recreates the event instance from the serialized data.
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     * @param  array  $payload
     * @return static|Event
     */
    public static function fromJson(array $payload): self;
}
