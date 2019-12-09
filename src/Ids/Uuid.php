<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Ids;

use Illuminate\Support\Str;
use Spaceemotion\LaravelEventSourcing\AggregateId;

final class Uuid implements AggregateId
{
    private string $id;

    private function __construct(string $id)
    {
        $this->id = $id;
    }

    public static function fromString(string $id): AggregateId
    {
        return new static($id);
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public static function next(): AggregateId
    {
        return self::fromString(Str::uuid()->toString());
    }
}
