<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use PHPUnit\Framework\TestCase;
use Spaceemotion\LaravelEventSourcing\EventStore\InMemoryEventStore;
use Spaceemotion\LaravelEventSourcing\StoredEvent;

use function array_push;

class AggregateRootTest extends TestCase
{
    /** @test */
    public function it_records_events(): void
    {
        $root = TestAggregateRoot::new();

        /** @var StoredEvent[] $events */
        $events = [];

        array_push($events, ...$root->flushEvents());

        self::assertCount(1, $events);

        self::assertEquals($root->getId(), $events[0]->getAggregateId());
        self::assertEquals(1, $events[0]->getVersion());
        self::assertEquals(new TestCreatedEvent($root->getId()), $events[0]->getEvent());
    }

    /** @test */
    public function it_keeps_the_version_correct_across_loads_and_saves(): void
    {
        $root = TestAggregateRoot::new();
        $root->set([1]);
        $root->set([2]);

        $store = new InMemoryEventStore();
        $store->persist($root);

        $clone = TestAggregateRoot::rebuild($store->retrieveAll($root->getId()));
        self::assertEquals($root->getCurrentVersion(), $clone->getCurrentVersion());
    }
}
