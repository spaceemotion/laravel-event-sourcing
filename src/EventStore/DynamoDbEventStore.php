<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\EventStore;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Carbon;
use Spaceemotion\LaravelEventSourcing\AggregateRoot;
use Spaceemotion\LaravelEventSourcing\ClassMapper\EventClassMapper;
use Spaceemotion\LaravelEventSourcing\Event;
use Spaceemotion\LaravelEventSourcing\StoredEvent;

use function array_chunk;

class DynamoDbEventStore implements EventStore
{
    /** @var EventClassMapper */
    protected $classMapper;

    /** @var Marshaler */
    protected $marshaler;

    /** @var DynamoDbClient */
    protected $client;

    /** @var string */
    protected $table;

    public function __construct(DynamoDbClient $client, EventClassMapper $classMapper, string $table)
    {
        $this->classMapper = $classMapper;

        $this->table = $table;
        $this->client = $client;
        $this->marshaler = new Marshaler();
    }

    public function retrieveAll(AggregateRoot $aggregate): iterable
    {
        $response = $this->client->query([
            'TableName' => $this->table,
            'KeyConditionExpression' => 'EventStream = :stream',
            'ConsistentRead' => true,
            'ExpressionAttributeValues' => [
                ':stream' => ['S' => (string) $aggregate->getId()],
            ],
        ]);

        foreach ($response['Items'] as $item) {
            /** @var Event $class */
            $class = $this->classMapper->decode($item['EventType']['S']);

            yield new StoredEvent(
                $aggregate,
                $class::fromJson($this->marshaler->unmarshalValue($item['Payload'])),
                (int) $item['Version']['N'],
                Carbon::parse($item['CreatedAt']['S']),
            );
        }
    }

    public function persist(AggregateRoot $aggregate): void
    {
        // BatchWrite does not work with more than 25 items at a time so we kind of
        // need to intelligently group them. Individual items can be as large as
        // 400KB, but reaching that limit any time soon is extremely unlikely.

        $items = [];

        foreach ($aggregate->flushEvents() as $version => $event) {
            $items[] = [
                'PutRequest' => [
                    'Item' => [
                        'EventStream' => ['S' => (string) $aggregate->getId()],
                        'EventType' => ['S' => $this->classMapper->encode(get_class($event))],
                        'Version' => ['N' => (int) $version],
                        'Payload' => $this->marshaler->marshalValue($event->jsonSerialize()),
                        'CreatedAt' => ['S' => (string) now()],
                    ],
                ],
            ];
        }

        foreach (array_chunk($items, 25) as $chunk) {
            $this->client->batchWriteItem([
                'RequestItems' => [
                    $this->table => $chunk,
                ],
            ]);
        }
    }
}
