<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\EventStore;

use Aws\Result;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Spaceemotion\LaravelEventSourcing\AggregateId;
use Spaceemotion\LaravelEventSourcing\AggregateRoot;
use Spaceemotion\LaravelEventSourcing\ClassMapper\EventClassMapper;
use Spaceemotion\LaravelEventSourcing\Event;
use Spaceemotion\LaravelEventSourcing\EventDispatcher;
use Spaceemotion\LaravelEventSourcing\Exceptions\ConcurrentModificationException;

use function get_class;

class DynamoDbEventStore implements EventStore, SnapshotEventStore
{
    public const FIELD_CREATED_AT = 'CreatedAt';
    public const FIELD_EVENT_STREAM = 'EventStream';
    public const FIELD_EVENT_TYPE = 'EventType';
    public const FIELD_PAYLOAD = 'Payload';
    public const FIELD_VERSION = 'Version';

    protected EventDispatcher $events;

    protected EventClassMapper $classMapper;

    protected Marshaler $marshaler;

    protected DynamoDbClient $client;

    protected string $table;

    public function __construct(
        EventDispatcher $events,
        EventClassMapper $classMapper,
        DynamoDbClient $client,
        string $table
    ) {
        $this->events = $events;
        $this->classMapper = $classMapper;

        $this->client = $client;
        $this->table = $table;

        $this->marshaler = new Marshaler();
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveAll(AggregateId $id): iterable
    {
        $response = $this->client->query([
            'TableName' => $this->table,
            'KeyConditionExpression' => '#Stream = :stream',
            'FilterExpression' => '#Type <> :type',
            'ConsistentRead' => true,
            'ExpressionAttributeNames' => [
                '#Stream' => self::FIELD_EVENT_STREAM,
                '#Type' => self::FIELD_EVENT_TYPE,
            ],
            'ExpressionAttributeValues' => [
                ':stream' => ['S' => (string) $id],
                ':type' => ['S' => self::EVENT_TYPE_SNAPSHOT],
            ],
        ]);

        yield from $this->itemsToEvents($response);
    }

    public function persist(AggregateRoot $aggregate): void
    {
        // Instead of using BatchWrite we use single PutItem requests
        // as they do not seem to work with conditional expressions.
        // Since that's how we detect race conditions we take the
        // performance hit for better data integrity/safety.

        foreach ($aggregate->flushEvents() as $version => $event) {
            try {
                $this->client->putItem([
                    'TableName' => $this->table,
                    'Item' => [
                        self::FIELD_EVENT_STREAM => ['S' => (string) $aggregate->getId()],
                        self::FIELD_EVENT_TYPE => ['S' => $this->classMapper->encode(get_class($event->getEvent()))],
                        self::FIELD_VERSION => ['N' => (int) $version],
                        self::FIELD_PAYLOAD => $this->marshaler->marshalValue($event->getEvent()->serialize()),
                        self::FIELD_CREATED_AT => ['S' => (string) $event->getPersistedAt()],
                    ],
                   'ExpressionAttributeNames' => [
                       '#Version' => self::FIELD_VERSION,
                   ],
                    'ConditionExpression' => 'attribute_not_exists(#Version)',
                    'ReturnValues' => 'NONE',
                ]);
            } catch (DynamoDbException $exception) {
                if (!$this->wasConcurrentModification($exception)) {
                    throw $exception;
                }

                // Throw a new exception right here, since we won't be able to
                // store any other events anyhow
                throw ConcurrentModificationException::forEvent($event, $exception);
            }

            // Only dispatch after we successfully stored them. That way, if there was
            // a concurrent modification, we didn't update the read models by accident.
            $this->events->dispatch($event);
        }
    }

    public function retrieveFromLastSnapshot(AggregateId $id): iterable
    {
        $response = $this->client->query([
            'TableName' => $this->table,
            'KeyConditionExpression' => '#Stream = :stream',
            'FilterExpression' => '#Type = :type',
            'ConsistentRead' => true,
            'ScanIndexForward' => false,
            'Limit' => 1,
             'ExpressionAttributeNames' => [
                 '#Stream' => self::FIELD_EVENT_STREAM,
                 '#Type' => self::FIELD_EVENT_TYPE,
             ],
            'ExpressionAttributeValues' => [
                ':stream' => ['S' => (string) $id],
                ':type' => ['S' => self::EVENT_TYPE_SNAPSHOT],
            ],
        ]);

        yield from $this->itemsToEvents($response);

        $response = $this->client->query([
            'TableName' => $this->table,
            'KeyConditionExpression' => '#Stream = :stream',
            'FilterExpression' => '#Type = :type',
            'ConsistentRead' => true,
            'ExpressionAttributeNames' => [
                '#Stream' => self::FIELD_EVENT_STREAM,
                '#Type' => self::FIELD_EVENT_TYPE,
            ],
            'ExpressionAttributeValues' => [
                ':stream' => ['S' => (string) $id],
                ':type' => ['S' => self::EVENT_TYPE_SNAPSHOT],
            ],
        ]);

        yield from $this->itemsToEvents($response);
    }

    protected function wasConcurrentModification(DynamoDbException $exception): bool
    {
        return $exception->getAwsErrorCode() === 'ConditionalCheckFailedException';
    }

    protected function itemsToEvents(Result $response): iterable
    {
        foreach ($response['Items'] as $item) {
            /** @var Event $class */
            $class = $this->classMapper->decode($item[self::FIELD_EVENT_TYPE]['S']);

            yield $class::deserialize($this->marshaler->unmarshalValue($item[self::FIELD_PAYLOAD]));
        }
    }
}
