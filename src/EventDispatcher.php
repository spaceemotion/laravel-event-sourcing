<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use Illuminate\Contracts\Events\Dispatcher;

class EventDispatcher
{
    protected Dispatcher $events;

    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
    }

    public function dispatch(StoredEvent $event): void
    {
        // Send off the general event (for things like logging)
        $this->events->dispatch($event);

        // Then let individual listeners handle the change on a per-event basis
        $this->events->dispatch(get_class($event->getEvent()), [$event, $event->getEvent()]);
    }
}
