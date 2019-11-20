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
use Spaceemotion\LaravelEventSourcing\Snapshot;
use Spaceemotion\LaravelEventSourcing\StoredEvent;

use function get_class;

class DynamoDbEventStore implements SnapshotEventStore
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
            'KeyConditionExpression' => 'EventStream = :stream and Version >= :version',
            'FilterExpression' => 'EventType <> :type',
            'ConsistentRead' => true,
            'ExpressionAttributeValues' => [
                ':stream' => ['S' => (string) $aggregate->getId()],
                ':type' => ['S' => self::EVENT_TYPE_SNAPSHOT],
                ':version' => ['N' => $aggregate->getCurrentVersion()],
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

        foreach ($aggregate->flushEvents() as $version => $event) {
            try {
                $this->client->putItem([
                    'TableName' => $this->table,
                    'Item' => [
                        'EventStream' => ['S' => (string) $aggregate->getId()],
                        'EventType' => ['S' => $this->classMapper->encode(get_class($event->getEvent()))],
                        'Version' => ['N' => (int) $version],
                        'Payload' => $this->marshaler->marshalValue($event->getEvent()->jsonSerialize()),
                        'CreatedAt' => ['S' => (string) $event->getPersistedAt()],
                    ],
                    'ConditionExpression' => 'EventStream <> :stream AND Version <> :version',
                    'ExpressionAttributeValues' => [
                        ':stream' => ['S' => (string) $aggregate->getId()],
                        ':version' => ['N' => (int) $version],
                    ],
                ]);
            } catch (DynamoDbException $e) {
                if (!$this->wasConcurrentModification($e)) {
                    throw $e;
                }

                // Throw a new exception right here, since we won't be able to
                // store any other events anyhow
                throw ConcurrentModificationException::forEvent($event, $e);
            }
        }
    }

    /**
     * Returns the last stored snapshot for the given aggregate.
     *
     * @param  AggregateRoot  $aggregate
     * @return StoredEvent|null
     */
    public function retrieveLastSnapshot(AggregateRoot $aggregate): ?StoredEvent
    {
        $response = $this->client->query([
            'TableName' => $this->table,
            'KeyConditionExpression' => 'EventStream = :stream',
            'FilterExpression' => 'EventType = :type',
            'ConsistentRead' => true,
            'LastEvaluatedKey' => 1,
            'ScanIndexForward' => false,
            'ExpressionAttributeValues' => [
                ':stream' => ['S' => (string) $aggregate->getId()],
                ':type' => ['S' => self::EVENT_TYPE_SNAPSHOT],
            ],
        ]);

        if ((int) ($response['Count'] ?? 0) < 1) {
            return null;
        }

        $record = $response['Items'][0];

        return new StoredEvent(
            $aggregate,
            Snapshot::fromJson($this->marshaler->unmarshalValue($record['Payload'])),
            (int) $record['Version']['N'],
            Carbon::parse($record['CreatedAt']['S']),
        );
    }

    /**
     * Stores the current state of the given aggregate in a snapshot.
     * This saves the current version to know which to resume from.
     *
     * @param  AggregateRoot  $aggregate
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
                    'Payload' => $this->marshaler->marshalValue($snapshot->getEvent()->jsonSerialize()),
                    'CreatedAt' => ['S' => (string) $snapshot->getPersistedAt()],
                ],
                'ConditionExpression' => 'EventStream <> :stream AND Version <> :version',
                'ExpressionAttributeValues' => [
                    ':stream' => ['S' => (string) $aggregate->getId()],
                    ':version' => ['N' => $snapshot->getVersion()],
                ],
            ]);
        } catch (DynamoDbException $e) {
            if (!$this->wasConcurrentModification($e)) {
                throw $e;
            }

            throw ConcurrentModificationException::forSnapshot($snapshot, $e);
        }
    }

    protected function wasConcurrentModification(DynamoDbException $e): bool
    {
        return $e->getAwsErrorCode() === 'ConditionalCheckFailedException';
    }
}
