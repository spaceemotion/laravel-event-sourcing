<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\EventStore;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Spaceemotion\LaravelEventSourcing\AggregateId;
use Spaceemotion\LaravelEventSourcing\AggregateRoot;
use Spaceemotion\LaravelEventSourcing\ClassMapper\EventClassMapper;
use Spaceemotion\LaravelEventSourcing\Event;
use Spaceemotion\LaravelEventSourcing\EventDispatcher;
use Spaceemotion\LaravelEventSourcing\Exceptions\ConcurrentModificationException;
use Spaceemotion\LaravelEventSourcing\StoredEvent;
use stdClass;

use function get_class;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

class DatabaseEventStore implements EventStore, SnapshotEventStore
{
    public const FIELD_AGGREGATE_ID = 'aggregate_id';
    public const FIELD_CREATED_AT = 'created_at';
    public const FIELD_EVENT_TYPE = 'event_type';
    public const FIELD_META_DATA = 'meta_data';
    public const FIELD_PAYLOAD = 'payload';
    public const FIELD_VERSION = 'version';

    protected const CHUNK_SIZE_PERSIST = 128;

    protected EventClassMapper $classMapper;

    protected EventDispatcher $events;

    public function __construct(EventClassMapper $classMapper, EventDispatcher $events)
    {
        $this->classMapper = $classMapper;
        $this->events = $events;
    }

    public function retrieveAll(AggregateId $id): iterable
    {
        $query = $this->newQuery()
            ->where(self::FIELD_EVENT_TYPE, '!=', self::EVENT_TYPE_SNAPSHOT);

        return $this->retrieveByQuery($id, $query);
    }

    public function retrieveFromLastSnapshot(AggregateId $id): iterable
    {
        $query = $this->newQuery()
            ->where('version', '>=', static function (Builder $query) use ($id): void {
                $query
                    ->from('stored_events')
                    ->where(self::FIELD_AGGREGATE_ID, (string) $id)
                    ->where(self::FIELD_EVENT_TYPE, self::EVENT_TYPE_SNAPSHOT)
                    ->selectRaw('MAX(' . self::FIELD_VERSION . ')');
            });

        return $this->retrieveByQuery($id, $query);
    }

    public function persist(AggregateRoot $aggregate): void
    {
        $events = new LazyCollection($aggregate->flushEvents());

        // Build and execute a bulk query
        $rows = $events->map(fn (StoredEvent $event): array => $this->mapStoredEvent($event));

        try {
            $rows->chunk(self::CHUNK_SIZE_PERSIST)->each(fn (LazyCollection $chunk) => (
                $this->newQuery()->insert($chunk->toArray())
            ));
        } catch (QueryException $exception) {
            if (!$this->wasConcurrentModification($exception)) {
                throw $exception;
            }

            throw ConcurrentModificationException::forEvent($events->first(), $exception);
        }

        // Update projections after storing the data
        $events->each(fn (StoredEvent $event) => $this->events->dispatch($event));
    }

    protected function newQuery(): Builder
    {
        return DB::table('stored_events');
    }

    protected function wasConcurrentModification(QueryException $exception): bool
    {
        // Code for 'integrity constraint violation'
        // https://en.wikipedia.org/wiki/SQLSTATE
        return (int) $exception->getCode() === 23_000;
    }

    protected function mapStoredEvent(StoredEvent $event): array
    {
        return [
            self::FIELD_AGGREGATE_ID => (string) $event->getAggregateId(),
            self::FIELD_CREATED_AT => (string) $event->getPersistedAt(),
            self::FIELD_EVENT_TYPE => $this->classMapper->encode(get_class($event->getEvent())),
            self::FIELD_META_DATA => json_encode([], JSON_THROW_ON_ERROR, 32), // TODO
            self::FIELD_PAYLOAD => json_encode($event->getEvent()->serialize(), JSON_THROW_ON_ERROR, 32),
            self::FIELD_VERSION => $event->getVersion(),
        ];
    }

    protected function retrieveByQuery(AggregateId $id, Builder $builder): LazyCollection
    {
        return $builder
            ->where(self::FIELD_AGGREGATE_ID, $id)
            ->orderBy(self::FIELD_VERSION)
            ->cursor()
            ->map(
                function (stdClass $row) use ($id): StoredEvent {
                    $payload = json_decode($row->{self::FIELD_PAYLOAD}, true, 32, JSON_THROW_ON_ERROR);

                    /** @var Event $base */
                    $base = $this->classMapper->decode($row->{self::FIELD_EVENT_TYPE});

                    return new StoredEvent(
                        $id::fromString($row->{self::FIELD_AGGREGATE_ID}),
                        $base::deserialize($payload),
                        (int)$row->{self::FIELD_VERSION},
                        Carbon::parse($row->{self::FIELD_CREATED_AT})->toImmutable(),
                    );
                },
            );
    }
}
