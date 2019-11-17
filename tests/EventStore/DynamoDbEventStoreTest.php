<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests\EventStore;

use PHPUnit\Framework\TestCase;
use Aws\DynamoDb\DynamoDbClient;
use Spaceemotion\LaravelEventSourcing\Tests\TestEvent;
use Spaceemotion\LaravelEventSourcing\Tests\TestAggregateRoot;
use Spaceemotion\LaravelEventSourcing\EventStore\DynamoDbEventStore;
use Spaceemotion\LaravelEventSourcing\ClassMapper\ConfigurableEventClassMapper;
use function in_array;

class DynamoDbEventStoreTest extends TestCase
{
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

    protected function createStore(): DynamoDbEventStore
    {
        static $tableName = 'phpunit';

        $client = new DynamoDbClient([
            'region' => 'eu-central-1',
            'version' => 'latest',
            'endpoint' => 'http://localhost:8000',
        ]);

        if (!in_array($tableName, $client->listTables()->get('TableNames'), true)) {
            self::assertNotNull($client->createTable([
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
            ]));
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
