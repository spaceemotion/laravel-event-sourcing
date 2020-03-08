<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use Spaceemotion\LaravelEventSourcing\AggregateId;
use Spaceemotion\LaravelEventSourcing\AggregateRoot;
use Spaceemotion\LaravelEventSourcing\Ids\Uuid;

final class TestAggregateRoot extends AggregateRoot
{
    public array $state = [];
    private AggregateId $id;

    public static function new(): self
    {
        return (new self())->record(new TestCreatedEvent(Uuid::next()));
    }

    public function getId(): AggregateId
    {
        return $this->id;
    }

    /**
     * {@inheritDoc}
     */
    protected function applySnapshot(array $snapshot): void
    {
        $this->id = Uuid::fromString($snapshot['id']);
        $this->state = $snapshot['state'];
    }

    /**
     * {@inheritDoc}
     */
    protected function buildSnapshot(): array
    {
        return [
            'id' => (string) $this->id,
            'state' => $this->state,
        ];
    }

    public function set(array $state): self
    {
        return $this->record(new TestEvent($state));
    }

    /**
     * {@inheritDoc}
     */
    protected function getEventHandlers(): array
    {
        return [
            TestCreatedEvent::class => function (TestCreatedEvent $event): void {
                $this->id = $event->getId();
            },
            TestEvent::class => function (TestEvent $event): void {
                $this->state = $event->attributes;
            },
        ];
    }
}
