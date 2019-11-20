<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\EventStore;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Spaceemotion\LaravelEventSourcing\AggregateRoot;
use Spaceemotion\LaravelEventSourcing\ClassMapper\EventClassMapper;
use Spaceemotion\LaravelEventSourcing\Event;
use Spaceemotion\LaravelEventSourcing\Exceptions\ConcurrentModificationException;
use Spaceemotion\LaravelEventSourcing\Snapshot;
use Spaceemotion\LaravelEventSourcing\StoredEvent;
use stdClass;

use function get_class;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

class DatabaseEventStore implements SnapshotEventStore
{
    public const FIELD_AGGREGATE_ID = 'aggregate_id';
    public const FIELD_CREATED_AT = 'created_at';
    public const FIELD_EVENT_TYPE = 'event_type';
    public const FIELD_META_DATA = 'meta_data';
    public const FIELD_PAYLOAD = 'payload';
    public const FIELD_VERSION = 'version';

    /** @var EventClassMapper */
    protected $classMapper;

    /** @var Dispatcher */
    protected $events;

    public function __construct(EventClassMapper $classMapper, Dispatcher $events)
    {
        $this->classMapper = $classMapper;
        $this->events = $events;
    }

    public function retrieveAll(AggregateRoot $aggregate): iterable
    {
        $version = $aggregate->getCurrentVersion();

        return $this->newQuery()
            ->where(self::FIELD_AGGREGATE_ID, $aggregate->getId())
            ->when($version > 0, static function (Builder $query) use ($version) {
                $query->where(self::FIELD_VERSION, '>=', $version);
            })
            ->cursor()
            ->reject(static function (stdClass $row) {
                return $row->{self::FIELD_EVENT_TYPE} === self::EVENT_TYPE_SNAPSHOT;
            })
            ->map(function (stdClass $row) use ($aggregate): StoredEvent {
                $payload = json_decode($row->{self::FIELD_PAYLOAD}, true, 32, JSON_THROW_ON_ERROR);

                /** @var Event $base */
                $base = $this->classMapper->decode($row->{self::FIELD_EVENT_TYPE});

                return new StoredEvent(
                    $aggregate,
                    $base::fromJson($payload),
                    (int) $row->{self::FIELD_VERSION},
                    Carbon::parse($row->{self::FIELD_CREATED_AT}),
                );
            });
    }

    public function retrieveLastSnapshot(AggregateRoot $aggregate): ?StoredEvent
    {
        /** @var stdClass|null $row */
        $row = $this->newQuery()
            ->where(self::FIELD_AGGREGATE_ID, $aggregate->getId())
            ->where(self::FIELD_EVENT_TYPE, self::EVENT_TYPE_SNAPSHOT)
            ->latest()
            ->orderByDesc(self::FIELD_VERSION)
            ->first();

        if ($row === null) {
            return null;
        }

        $payload = json_decode($row->{self::FIELD_PAYLOAD}, true, 32, JSON_THROW_ON_ERROR);

        return new StoredEvent(
            $aggregate,
            Snapshot::fromJson($payload),
            (int) $row->{self::FIELD_VERSION},
            Carbon::parse($row->{self::FIELD_CREATED_AT}),
        );
    }

    public function persist(AggregateRoot $aggregate): void
    {
        $events = (new LazyCollection($aggregate->flushEvents()))
            ->each(function (StoredEvent $event): void {
                $this->events->dispatch($event);
            });

        try {
            $this->newQuery()->insert($events->map(function (StoredEvent $event) use ($aggregate) {
                return [
                    self::FIELD_AGGREGATE_ID => (string) $aggregate->getId(),
                    self::FIELD_CREATED_AT => (string) $event->getPersistedAt(),
                    self::FIELD_EVENT_TYPE => $this->classMapper->encode(get_class($event->getEvent())),
                    self::FIELD_META_DATA => json_encode([], JSON_THROW_ON_ERROR, 32), // TODO
                    self::FIELD_PAYLOAD => json_encode($event->getEvent(), JSON_THROW_ON_ERROR, 32),
                    self::FIELD_VERSION => $event->getVersion(),
                ];
            })->toArray());
        } catch (QueryException $e) {
            // Code for 'integrity constraint violation'
            // https://en.wikipedia.org/wiki/SQLSTATE
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }

            throw ConcurrentModificationException::forEvent($events->first(), $e);
        }
    }

    public function persistSnapshot(AggregateRoot $aggregate): void
    {
        // Better to be safe than sorry
        $this->persist($aggregate);

        $snapshot = $aggregate->newSnapshot();

        $this->newQuery()->insert([
            self::FIELD_AGGREGATE_ID => (string) $aggregate->getId(),
            self::FIELD_CREATED_AT => (string) $snapshot->getPersistedAt(),
            self::FIELD_EVENT_TYPE => self::EVENT_TYPE_SNAPSHOT,
            self::FIELD_META_DATA => json_encode([], JSON_THROW_ON_ERROR, 32), // TODO
            self::FIELD_PAYLOAD => json_encode($snapshot->getEvent(), JSON_THROW_ON_ERROR, 32),
            self::FIELD_VERSION => $snapshot->getVersion(),
        ]);
    }

    protected function newQuery(): Builder
    {
        return DB::table('stored_events');
    }
}
