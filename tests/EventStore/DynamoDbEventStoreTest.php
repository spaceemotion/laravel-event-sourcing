<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests\EventStore;

use Carbon\Carbon;
use Aws\DynamoDb\DynamoDbClient;
use Illuminate\Support\Facades\Event;
use Spaceemotion\LaravelEventSourcing\Tests\TestEvent;
use Spaceemotion\LaravelEventSourcing\EventStore\DynamoDbEventStore;
use Spaceemotion\LaravelEventSourcing\Exceptions\ConcurrentModificationException;
use Spaceemotion\LaravelEventSourcing\StoredEvent;
use Spaceemotion\LaravelEventSourcing\Tests\TestAggregateRoot;
use Spaceemotion\LaravelEventSourcing\Tests\TestCase;

use function env;
use function in_array;
use function sprintf;

class DynamoDbEventStoreTest extends TestCase
{
    protected DynamoDbEventStore $store;

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

        $copy = $root->fresh()->rebuild($this->store);

        self::assertEquals($root->state, $copy->state);
    }

    /** @test */
    public function it_loads_nothing_for_empty_aggregate_roots(): void
    {
        $root = TestAggregateRoot::new()->rebuild($this->store);
        $events = [];

        array_push($events, ...$root->flushEvents());

        self::assertCount(0, $events);
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
    public function it_handles_concurrent_modification(): void
    {
        Carbon::setTestNow('2020-01-01');

        $first = TestAggregateRoot::new();
        $first->set(['foo' => 'bar']);

        $second = $first->fresh();
        $second->set(['foo' => 'baz']);

        $this->store->persist($first);

        try {
            $this->store->persist($second);
            self::fail('Expected ' . ConcurrentModificationException::class);
        } catch (ConcurrentModificationException $e) {
            self::assertEquals(new StoredEvent(
                $second,
                new TestEvent(['foo' => 'baz']),
                0,
                Carbon::getTestNow()->toImmutable()
            ), $e->getStoredEvent());
        }
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

        $clone = $aggregate->fresh();
        $clone->rebuildFromSnapshot($store);

        self::assertEquals($aggregate->state, $clone->state);
        self::assertEquals($aggregate->getCurrentVersion(), $clone->getCurrentVersion());

        // The following just tests if we're still able to store stuff
        // after saving a snapshot (unique constraint violation)
        $clone->set(['foo' => 'meow']);
        $store->persist($clone);
    }

    protected function createStore(): DynamoDbEventStore
    {
        static $tableName = 'phpunit';

        $client = new DynamoDbClient([
            'region' => 'eu-central-1',
            'version' => 'latest',
            'endpoint' => sprintf('http://%s:%s', env('DYNAMO_DB_HOST'), env('DYNAMO_DB_PORT')),
            'credentials' => [
                'key' => 'local',
                'secret' => 'local',
            ],
        ]);

        if (!in_array($tableName, $client->listTables()->get('TableNames'), true)) {
            $client->createTable([
                'TableName' => $tableName,
                'BillingMode' => 'PAY_PER_REQUEST',
                'AttributeDefinitions' => [
                    ['AttributeName' => 'EventStream', 'AttributeType' => 'S'],
                    ['AttributeName' => 'Version', 'AttributeType' => 'N'],
                ],
                'KeySchema' => [
                    ['AttributeName' => 'EventStream', 'KeyType' => 'HASH'],
                    ['AttributeName' => 'Version', 'KeyType' => 'RANGE'],
                ],
            ]);
        }

        $this->app->instance(DynamoDbClient::class, $client);

        return $this->app->make(DynamoDbEventStore::class, [
            'table' => $tableName,
        ]);
    }
}
