<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests\EventStore;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Spaceemotion\LaravelEventSourcing\EventStore\EventStore;
use Spaceemotion\LaravelEventSourcing\Exceptions\ConcurrentModificationException;
use Spaceemotion\LaravelEventSourcing\Ids\Uuid;
use Spaceemotion\LaravelEventSourcing\StoredEvent;
use Spaceemotion\LaravelEventSourcing\Tests\TestAggregateRoot;
use Spaceemotion\LaravelEventSourcing\Tests\TestCase;
use Spaceemotion\LaravelEventSourcing\Tests\TestCreatedEvent;
use Spaceemotion\LaravelEventSourcing\Tests\TestEvent;

use function array_push;

abstract class EventStoreTest extends TestCase
{
    abstract protected function createStore(): EventStore;

    /** @test */
    public function it_loads_nothing_for_empty_aggregate_roots(): void
    {
        $store = $this->createStore();

        $root = TestAggregateRoot::rebuild($store->retrieveAll(Uuid::next()));
        $events = [];

        array_push($events, ...$root->flushEvents());

        self::assertCount(0, $events);
    }

    /** @test */
    public function it_properly_stores_and_reads_data(): void
    {
        $store = $this->createStore();

        $root = TestAggregateRoot::new();
        $root->set(['foo' => 'bar']);

        $store->persist($root);

        $copy = TestAggregateRoot::rebuild($store->retrieveAll($root->getId()));

        self::assertEquals($root->state, $copy->state);
    }

    /** @test */
    public function it_dispatches_events_during_persistence(): void
    {
        $events = Event::fake([StoredEvent::class, TestCreatedEvent::class, TestEvent::class]);

        $root = TestAggregateRoot::new();
        $root->set(['foo' => 'bar']);

        $this->createStore()->persist($root);

        $events->assertDispatchedTimes(TestCreatedEvent::class, 1);
        $events->assertDispatchedTimes(TestEvent::class, 1);
        $events->assertDispatchedTimes(StoredEvent::class, 2);
    }

    /** @test */
    public function it_stores_snapshots(): void
    {
        $store = $this->createStore();
        $aggregate = TestAggregateRoot::new();

        $aggregate->set(['val' => 'oldest']);
        $store->persist($aggregate);

        $aggregate->set(['val' => 'old']);
        $store->persistSnapshot($aggregate);

        $aggregate->set(['val' => 'new']);
        $store->persistSnapshot($aggregate);

        $clone = TestAggregateRoot::rebuild($store->retrieveFromLastSnapshot($aggregate->getId()));

        self::assertEquals($aggregate->state, $clone->state);
        self::assertEquals($aggregate->getCurrentVersion(), $clone->getCurrentVersion());

        // The following just tests if we're still able to store stuff
        // after saving a snapshot (unique constraint violation)
        $clone->set(['foo' => 'meow']);
        $store->persist($clone);
    }

    /** @test */
    public function it_handles_concurrent_modification(): void
    {
        Carbon::setTestNow('2020-01-01');

        $store = $this->createStore();

        $first = TestAggregateRoot::new();
        $store->persist($first);

        $first->set([1]);
        $first->set([2]);

        $second = TestAggregateRoot::rebuild($store->retrieveAll($first->getId()));
        $second->set(['foo' => 'bar']);

        $store->persist($first);

        try {
            $store->persist($second);
            self::fail('Expected ' . ConcurrentModificationException::class);
        } catch (ConcurrentModificationException $e) {
            self::assertEquals(new StoredEvent(
                $second->getId(),
                new TestEvent(['foo' => 'bar']),
                2,
                Carbon::now()->toImmutable(),
            ), $e->getStoredEvent());
        }
    }
}
