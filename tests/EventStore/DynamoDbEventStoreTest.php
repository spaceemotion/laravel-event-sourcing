<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests\EventStore;

use Aws\DynamoDb\DynamoDbClient;
use PHPUnit\Framework\TestCase;
use Spaceemotion\LaravelEventSourcing\ClassMapper\ConfigurableEventClassMapper;
use Spaceemotion\LaravelEventSourcing\EventStore\DynamoDbEventStore;
use Spaceemotion\LaravelEventSourcing\Exceptions\ConcurrentModificationException;
use Spaceemotion\LaravelEventSourcing\Tests\TestAggregateRoot;
use Spaceemotion\LaravelEventSourcing\Tests\TestEvent;

use function env;
use function in_array;
use function sprintf;

class DynamoDbEventStoreTest extends TestCase
{
    protected DynamoDbEventStore $store;

    protected function setUp(): void
    {
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
    public function it_handles_concurrent_modification(): void
    {
        $first = TestAggregateRoot::new();
        $first->set(['foo' => 'bar']);

        $second = $first->fresh();
        $second->set(['foo' => 'baz']);

        $this->store->persist($first);

        $this->expectException(ConcurrentModificationException::class);

        $this->store->persist($second);
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

        return new DynamoDbEventStore(
            $client,
            new ConfigurableEventClassMapper([
                'test' => TestEvent::class,
            ]),
            $tableName,
        );
    }
}
