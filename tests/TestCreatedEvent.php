<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use Spaceemotion\LaravelEventSourcing\AggregateId;
use Spaceemotion\LaravelEventSourcing\Event;
use Spaceemotion\LaravelEventSourcing\Ids\Uuid;

class TestCreatedEvent implements Event
{
    private AggregateId $id;

    public function __construct(AggregateId $id)
    {
        $this->id = $id;
    }

    public function getId(): AggregateId
    {
        return $this->id;
    }

    public function serialize(): array
    {
        return ['id' => (string) $this->id];
    }

    public static function deserialize(array $payload): Event
    {
        return new self(Uuid::fromString($payload['id']));
    }
}
