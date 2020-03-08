<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests\EventStore;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spaceemotion\LaravelEventSourcing\EventStore\DatabaseEventStore;
use Spaceemotion\LaravelEventSourcing\Tests\TestAggregateRoot;

class DatabaseEventStoreTest extends EventStoreTest
{
    use RefreshDatabase;

    /** @test */
    public function it_properly_handles_bulk_insertion(): void
    {
        /** @var Connection $connection */
        $connection = $this->app->get(Connection::class);
        $connection->enableQueryLog();

        $this->createStore()->persist(TestAggregateRoot::new()
            ->set([1])
            ->set([2])
            ->set([3]));

        $log = $connection->getQueryLog();

        self::assertCount(1, $log);
        self::assertStringStartsWith('insert into', $log[0]['query']);
    }

    protected function createStore(): DatabaseEventStore
    {
        return $this->app->make(DatabaseEventStore::class);
    }
}
