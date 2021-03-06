<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use Spaceemotion\LaravelEventSourcing\Event;

class TestEvent implements Event
{
    public array $attributes;

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     * @param  array  $attributes
     */
    final public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * {@inheritDoc}
     */
    public static function deserialize(array $payload): Event
    {
        return new static($payload);
    }

    /**
     * {@inheritDoc}
     */
    public function serialize(): array
    {
        return $this->attributes;
    }
}
