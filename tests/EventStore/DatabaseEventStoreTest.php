<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests\EventStore;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spaceemotion\LaravelEventSourcing\EventStore\DatabaseEventStore;
use Spaceemotion\LaravelEventSourcing\Tests\TestAggregateRoot;
use Spaceemotion\LaravelEventSourcing\Tests\TestCase;

class DatabaseEventStoreTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_properly_stores_and_reads_data(): void
    {
        $repo = $this->createStore();

        $root = TestAggregateRoot::new();
        $root->set(['foo' => 'bar']);

        $repo->persist($root);

        $copy = TestAggregateRoot::forId($root->getId())->rebuild($repo);

        self::assertEquals($root->state, $copy->state);
    }

    /** @test */
    public function it_properly_handles_bulk_insertion(): void
    {
        /** @var Connection $connection */
        $connection = $this->app->get(Connection::class);
        $connection->enableQueryLog();

        $repo = $this->createStore();
        $root = TestAggregateRoot::new();

        // Since the batch size is 25, try to store a few plus a smaller chunk at the end
        foreach (range(0, 64) as $index) {
            $root->set(['index' => $index]);
        }

        $repo->persist($root);

        $log = $connection->getQueryLog();

        self::assertCount(1, $log);
        self::assertStringStartsWith('insert into', $log[0]['query']);
    }

    protected function createStore(): DatabaseEventStore
    {
        return $this->app->make(DatabaseEventStore::class);
    }
}
