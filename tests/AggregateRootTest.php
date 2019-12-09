<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use PHPUnit\Framework\TestCase;

use function array_push;

class AggregateRootTest extends TestCase
{
    /** @test */
    public function it_records_events(): void
    {
        $root = TestAggregateRoot::new();

        $root->set(['foo' => 'bar']);

        $events = [];

        array_push($events, ...$root->flushEvents());

        self::assertCount(1, $events);
    }

    /** @test */
    public function it_allows_shallow_copies(): void
    {
        $original = TestAggregateRoot::new();
        $copy = TestAggregateRoot::forId($original->getId());

        self::assertEquals($original->getId(), $copy->getId());
    }
}
