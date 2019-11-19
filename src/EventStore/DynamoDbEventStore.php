<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\EventStore;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Carbon;
use Spaceemotion\LaravelEventSourcing\AggregateRoot;
use Spaceemotion\LaravelEventSourcing\ClassMapper\EventClassMapper;
use Spaceemotion\LaravelEventSourcing\Event;
use Spaceemotion\LaravelEventSourcing\Exceptions\ConcurrentModificationException;
use Spaceemotion\LaravelEventSourcing\StoredEvent;

use function get_class;

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
        $this->client = $client;
        $this->classMapper = $classMapper;
        $this->table = $table;

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
        // Instead of using BatchWrite we use single PutItem requests
        // as they do not seem to work with conditional expressions.
        // Since that's how we detect race conditions we take the
        // performance hit for better data integrity/safety.

        $now = Carbon::now();
        $nowString = (string) $now;

        foreach ($aggregate->flushEvents() as $version => $event) {
            try {
                $this->client->putItem([
                    'TableName' => $this->table,
                    'Item' => [
                        'EventStream' => ['S' => (string) $aggregate->getId()],
                        'EventType' => ['S' => $this->classMapper->encode(get_class($event))],
                        'Version' => ['N' => (int) $version],
                        'Payload' => $this->marshaler->marshalValue($event->jsonSerialize()),
                        'CreatedAt' => ['S' => $nowString],
                    ],
                    'ConditionExpression' => 'EventStream <> :stream AND Version <> :version',
                    'ExpressionAttributeValues' => [
                        ':stream' => ['S' => (string) $aggregate->getId()],
                        ':version' => ['N' => (int) $version],
                    ],
                ]);
            } catch (DynamoDbException $e) {
                if ($e->getAwsErrorCode() !== 'ConditionalCheckFailedException') {
                    throw $e;
                }

                // Throw a new exception right here, since we won't be able to
                // store any other events anyhow
                throw ConcurrentModificationException::forEvent(new StoredEvent(
                    $aggregate,
                    $event,
                    $version,
                    $now,
                ), $e);
            }
        }
    }
}
