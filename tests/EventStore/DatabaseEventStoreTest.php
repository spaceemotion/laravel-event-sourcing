<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests\EventStore;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spaceemotion\LaravelEventSourcing\EventStore\DatabaseEventStore;
use Spaceemotion\LaravelEventSourcing\Exceptions\ConcurrentModificationException;
use Spaceemotion\LaravelEventSourcing\StoredEvent;
use Spaceemotion\LaravelEventSourcing\Tests\TestAggregateRoot;
use Spaceemotion\LaravelEventSourcing\Tests\TestCase;

class DatabaseEventStoreTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_properly_stores_and_reads_data(): void
    {
        $store = $this->createStore();

        $root = TestAggregateRoot::new();
        $root->set(['foo' => 'bar']);

        $store->persist($root);

        $copy = $root->fresh()->rebuild($store);

        self::assertEquals($root->state, $copy->state);
    }

    /** @test */
    public function it_properly_handles_bulk_insertion(): void
    {
        $store = $this->createStore();

        /** @var Connection $connection */
        $connection = $this->app->get(Connection::class);
        $connection->enableQueryLog();

        $root = TestAggregateRoot::new();

        // Since the batch size is 25, try to store a few plus a smaller chunk at the end
        foreach (range(0, 64) as $index) {
            $root->set(['index' => $index]);
        }

        $store->persist($root);

        $log = $connection->getQueryLog();

        self::assertCount(1, $log);
        self::assertStringStartsWith('insert into', $log[0]['query']);
    }

    /** @test */
    public function it_handles_concurrent_modification(): void
    {
        $store = $this->createStore();

        $first = TestAggregateRoot::new();
        $first->set(['foo' => 'bar']);

        $second = $first->fresh();
        $second->set(['foo' => 'baz']);

        $store->persist($first);

        $this->expectException(ConcurrentModificationException::class);

        $store->persist($second);
    }

    /** @test */
    public function it_dispatches_events_during_persistence(): void
    {
        $events = Event::fake([StoredEvent::class]);

        $root = TestAggregateRoot::new();
        $root->set(['foo' => 'bar']);
        $root->set(['foo' => 'baz']);

        $this->createStore()->persist($root);

        $events->assertDispatchedTimes(StoredEvent::class, 2);
    }

    /** @test */
    public function it_stores_snapshots(): void
    {
        $store = $this->createStore();

        $aggregate = TestAggregateRoot::new();
        $aggregate->set(['foo' => 'bar']);

        $store->persist($aggregate);

        $aggregate->set(['foo' => 'baz']);

        $store->persistSnapshot($aggregate);

        $clone = $aggregate->fresh();
        $clone->rebuildFromSnapshot($store);

        $this->assertEquals($aggregate->state, $clone->state);
        $this->assertEquals($aggregate->getCurrentVersion(), $clone->getCurrentVersion());

        // The following just tests if we're still able to store stuff
        // after saving a snapshot (unique constraint violation)
        $clone->set(['foo' => 'meow']);
        $store->persist($clone);
    }

    protected function createStore(): DatabaseEventStore
    {
        return $this->app->make(DatabaseEventStore::class);
    }
}
