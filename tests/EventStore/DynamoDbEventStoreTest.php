<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests\EventStore;

use Aws\DynamoDb\DynamoDbClient;
use Spaceemotion\LaravelEventSourcing\Ids\Uuid;
use Spaceemotion\LaravelEventSourcing\EventStore\DynamoDbEventStore;
use Spaceemotion\LaravelEventSourcing\Tests\TestAggregateRoot;

use function env;
use function in_array;
use function sprintf;

class DynamoDbEventStoreTest extends EventStoreTest
{
    protected function createStore(): DynamoDbEventStore
    {
        $tableName = 'phpunit';

        $client = new DynamoDbClient([
            'region' => 'localhost',
            'version' => 'latest',
            'endpoint' => sprintf('http://%s:%s', env('DYNAMO_DB_HOST'), env('DYNAMO_DB_PORT')),
            'credentials' => [
                'key' => 'x2ny5g',
                'secret' => 'qwc28',
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
