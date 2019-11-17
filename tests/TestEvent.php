<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use Spaceemotion\LaravelEventSourcing\Event;

class TestEvent implements Event
{
    /** @var array */
    public $attributes;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public static function fromJson(array $payload): Event
    {
        return new static($payload);
    }

    public function jsonSerialize(): array
    {
        return $this->attributes;
    }
}
