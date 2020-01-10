<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Spaceemotion\LaravelEventSourcing\EventStore\InMemoryEventStore;

use function array_push;

class AggregateRootTest extends TestCase
{
    /** @test */
    public function it_records_events(): void
    {
        $root = TestAggregateRoot::new();

        $root->set(['foo' => 'bar']);

        $events = [];

        array_push($events, ...$root->flushEvents());

        self::assertCount(1, $events);
    }

    /** @test */
    public function it_only_applies_known_events(): void
    {
        $root = TestAggregateRoot::new();
        $root->record(new TestSubEvent(['fuzz' => 'buzz']));

        self::assertCount(1, $root->flushEvents());
        self::assertEquals([], $root->state);
    }

    /** @test */
    public function it_allows_shallow_copies(): void
    {
        $original = TestAggregateRoot::new();
        $copy = $original->fresh();

        self::assertEquals($original->getId(), $copy->getId());
    }

    /** @test */
    public function it_only_allows_rebuilding_fresh_copies(): void
    {
        $root = TestAggregateRoot::new();
        $root->set([123]);

        $store = new InMemoryEventStore();

        $this->expectException(LogicException::class);
        $root->rebuild($store);
    }

    /** @test */
    public function it_keeps_the_version_correct_across_loads_and_saves(): void
    {
        $root = TestAggregateRoot::new();
        $root->set([1]);
        $root->set([2]);

        $store = new InMemoryEventStore();
        $store->persist($root);

        $clone = $root->fresh()->rebuild($store);
        self::assertEquals($root->getCurrentVersion(), $clone->getCurrentVersion());
    }
}
