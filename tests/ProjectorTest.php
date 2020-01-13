<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use PHPUnit\Framework\TestCase;
use Spaceemotion\LaravelEventSourcing\EventDispatcher;
use Spaceemotion\LaravelEventSourcing\StoredEvent;

class ProjectorTest extends TestCase
{
    /** @test */
    public function it_handles_dispatched_events(): void
    {
        // Generate an event
        $root = TestAggregateRoot::new();
        $root->set(['foo' => 'bar']);
        $event = [...$root->flushEvents()][0];

        $handled = false;

        // Creates a new projector
        $projector = new TestProjector([
            StoredEvent::class => static function () use (&$handled): void {
                $handled = true;
            },
            TestEvent::class => static function (StoredEvent $instance, TestEvent $evt) use ($event): void {
                self::assertEquals($event, $instance);
                self::assertEquals($event->getEvent(), $evt);
            },
        ]);

        self::assertEquals([StoredEvent::class, TestEvent::class], $projector->getProjectedEvents());

        // Register the projector
        $dispatcher = new \Illuminate\Events\Dispatcher();
        $projector->register($dispatcher);

        // Dispatch it just as we would in the event stores
        (new EventDispatcher($dispatcher))->dispatch($event);

        self::assertTrue($handled);
    }
}
