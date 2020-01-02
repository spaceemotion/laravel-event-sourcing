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
    public static function fromJson(array $payload): Event
    {
        return new static($payload);
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }
}
