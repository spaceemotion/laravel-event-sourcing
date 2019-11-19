<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests\EventStore;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spaceemotion\LaravelEventSourcing\EventStore\DatabaseEventStore;
use Spaceemotion\LaravelEventSourcing\Exceptions\ConcurrentModificationException;
use Spaceemotion\LaravelEventSourcing\Tests\TestAggregateRoot;
use Spaceemotion\LaravelEventSourcing\Tests\TestCase;

class DatabaseEventStoreTest extends TestCase
{
    use RefreshDatabase;

    /** @var DatabaseEventStore */
    protected $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->createStore();
    }

    /** @test */
    public function it_properly_stores_and_reads_data(): void
    {
        $root = TestAggregateRoot::new();
        $root->set(['foo' => 'bar']);

        $this->store->persist($root);

        $copy = TestAggregateRoot::forId($root->getId())->rebuild($this->store);

        self::assertEquals($root->state, $copy->state);
    }

    /** @test */
    public function it_properly_handles_bulk_insertion(): void
    {
        /** @var Connection $connection */
        $connection = $this->app->get(Connection::class);
        $connection->enableQueryLog();

        $root = TestAggregateRoot::new();

        // Since the batch size is 25, try to store a few plus a smaller chunk at the end
        foreach (range(0, 64) as $index) {
            $root->set(['index' => $index]);
        }

        $this->store->persist($root);

        $log = $connection->getQueryLog();

        self::assertCount(1, $log);
        self::assertStringStartsWith('insert into', $log[0]['query']);
    }

    /** @test */
    public function it_handles_concurrent_modification(): void
    {
        $first = TestAggregateRoot::new();
        $first->set(['foo' => 'bar']);

        $second = TestAggregateRoot::forId($first->getId());
        $second->set(['foo' => 'baz']);

        $this->store->persist($first);

        $this->expectException(ConcurrentModificationException::class);

        $this->store->persist($second);
    }

    protected function createStore(): DatabaseEventStore
    {
        return $this->app->make(DatabaseEventStore::class);
    }
}
