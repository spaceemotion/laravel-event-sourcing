<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\EventStore;

use Aws\Result;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Carbon;
use Spaceemotion\LaravelEventSourcing\AggregateId;
use Spaceemotion\LaravelEventSourcing\AggregateRoot;
use Spaceemotion\LaravelEventSourcing\ClassMapper\EventClassMapper;
use Spaceemotion\LaravelEventSourcing\Event;
use Spaceemotion\LaravelEventSourcing\EventDispatcher;
use Spaceemotion\LaravelEventSourcing\Exceptions\ConcurrentModificationException;
use Spaceemotion\LaravelEventSourcing\Snapshot;
use Spaceemotion\LaravelEventSourcing\StoredEvent;

use function get_class;

class DynamoDbEventStore implements EventStore, SnapshotEventStore
{
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
            'KeyConditionExpression' => 'EventStream = :stream',
            'FilterExpression' => 'EventType <> :type',
            'ConsistentRead' => true,
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
                        'EventStream' => ['S' => (string) $aggregate->getId()],
                        'EventType' => ['S' => $this->classMapper->encode(get_class($event->getEvent()))],
                        'Version' => ['N' => (int) $version],
                        'Payload' => $this->marshaler->marshalValue($event->getEvent()->serialize()),
                        'CreatedAt' => ['S' => (string) $event->getPersistedAt()],
                    ],
                    'ConditionExpression' => 'EventStream <> :stream AND (Version <> :version OR Version < :version)',
                    'ExpressionAttributeValues' => [
                        ':stream' => ['S' => (string) $aggregate->getId()],
                        ':version' => ['N' => (int) $version],
                    ],
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
            'KeyConditionExpression' => 'EventStream = :stream',
            'FilterExpression' => 'EventType = :type',
            'ConsistentRead' => true,
            'ScanIndexForward' => false,
            'Limit' => 1,
            'ExpressionAttributeValues' => [
                ':stream' => ['S' => (string) $id],
                ':type' => ['S' => self::EVENT_TYPE_SNAPSHOT],
            ],
        ]);

        yield from $this->itemsToEvents($response);

        // TODO does not load any data after the last snapshot
    }

    /**
     * Stores the current state of the given aggregate in a snapshot.
     * This saves the current version to know which to resume from.
     */
    public function persistSnapshot(AggregateRoot $aggregate): void
    {
        $snapshot = $aggregate->newSnapshot();

        try {
            $this->client->putItem([
                'TableName' => $this->table,
                'Item' => [
                    'EventStream' => ['S' => (string) $aggregate->getId()],
                    'EventType' => ['S' => self::EVENT_TYPE_SNAPSHOT],
                    'Version' => ['N' => $snapshot->getVersion()],
                    'Payload' => $this->marshaler->marshalValue($snapshot->getEvent()->serialize()),
                    'CreatedAt' => ['S' => (string) $snapshot->getPersistedAt()],
                ],
                'ConditionExpression' => 'EventStream <> :stream AND (Version <> :version OR Version < :version)',
                'ExpressionAttributeValues' => [
                    ':stream' => ['S' => (string) $aggregate->getId()],
                    ':version' => ['N' => $snapshot->getVersion()],
                ],
            ]);
        } catch (DynamoDbException $exception) {
            if (!$this->wasConcurrentModification($exception)) {
                throw $exception;
            }

            throw ConcurrentModificationException::forSnapshot($snapshot, $exception);
        }
    }

    protected function wasConcurrentModification(DynamoDbException $exception): bool
    {
        return $exception->getAwsErrorCode() === 'ConditionalCheckFailedException';
    }

    protected function itemsToEvents(Result $response): iterable
    {
        foreach ($response['Items'] as $item) {
            /** @var Event $class */
            $class = $this->classMapper->decode($item['EventType']['S']);

            yield $class::deserialize($this->marshaler->unmarshalValue($item['Payload']));
        }
    }
}
