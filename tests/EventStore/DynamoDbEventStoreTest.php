<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests\EventStore;

use Aws\DynamoDb\DynamoDbClient;
use PHPUnit\Framework\TestCase;
use Spaceemotion\LaravelEventSourcing\ClassMapper\ConfigurableEventClassMapper;
use Spaceemotion\LaravelEventSourcing\EventStore\DynamoDbEventStore;
use Spaceemotion\LaravelEventSourcing\Tests\TestAggregateRoot;
use Spaceemotion\LaravelEventSourcing\Tests\TestEvent;

use function env;
use function in_array;
use function sprintf;

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
            'endpoint' => sprintf('http://%s:8000', env('DYNAMO_DB_HOST')),
            'credentials' => [
                'key' => '',
                'secret' => '',
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
