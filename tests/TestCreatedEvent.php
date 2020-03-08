<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use Spaceemotion\LaravelEventSourcing\Event;
use Spaceemotion\LaravelEventSourcing\Ids\Uuid;
use Spaceemotion\LaravelEventSourcing\AggregateId;

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
