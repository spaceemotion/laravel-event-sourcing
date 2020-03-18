<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use PHPUnit\Framework\TestCase;
use Spaceemotion\LaravelEventSourcing\Ids\Uuid;
use Spaceemotion\LaravelEventSourcing\TestAggregate;

class TestAggregateTest extends TestCase
{
    /** @test */
    public function it_records_the_events_recorded_during_creation(): void
    {
        $aggregate = TestAggregateRoot::new()
            ->set(['foo' => 'bar'])
            ->set(['age' => 42]);

        TestAggregate::for($aggregate)
            ->assertRecorded(TestEvent::class)
            ->assertRecordedInstance(new TestEvent(['foo' => 'bar']))
            ->assertRecordedInstance(new TestEvent(['age' => 42]));
    }

    /** @test */
    public function it_only_asserts_new_events(): void
    {
        $aggregate = TestAggregateRoot::rebuild(TestAggregate::given(Uuid::next(), [
            new TestEvent(['foo' => 'bar'])
        ]));

        TestAggregate::for($aggregate)
            ->assertNotRecorded(TestEvent::class)
            ->assertNothingRecorded();
    }
}
