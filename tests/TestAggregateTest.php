<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use PHPUnit\Framework\TestCase;
use Spaceemotion\LaravelEventSourcing\TestAggregate;

class TestAggregateTest extends TestCase
{
    /** @test */
    public function it_rebuilds_the_aggregate_based_on_a_list_of_events(): void
    {
        $aggregate = TestAggregateRoot::new();

        (new TestAggregate($aggregate))
            ->given(new TestEvent(['foo' => 'bar']));

        self::assertEquals(['foo' => 'bar'], $aggregate->state);
    }

    /** @test */
    public function it_records_the_events_recorded_during_when(): void
    {
        (new TestAggregate(TestAggregateRoot::new()))
            ->when(static function (TestAggregateRoot $aggregate): void {
                $aggregate
                    ->record(new TestEvent(['foo' => 'bar']))
                    ->record(new TestEvent(['age' => 42]));
            })
            ->assertRecorded(TestEvent::class)
            ->assertRecordedInstance(new TestEvent(['foo' => 'bar']))
            ->assertRecordedInstance(new TestEvent(['age' => 42]));
    }

    /** @test */
    public function assertions_only_affect_the_events_during_when(): void
    {
        (new TestAggregate(TestAggregateRoot::new()))
            ->given(new TestEvent())
            ->assertNotRecorded(TestEvent::class)
            ->assertNothingRecorded();
    }
}
