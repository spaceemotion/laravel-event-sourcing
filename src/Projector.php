<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;

abstract class Projector
{
    /**
     * Registers all event handlers with the given event dispatcher.
     */
    public function register(Dispatcher $events): void
    {
        foreach ($this->getEventHandlers() as $event => $handler) {
            $events->listen((string) $event, $handler);
        }
    }

    /**
     * Returns an array of all event classes that are
     * being handled by this projector.
     *
     * @return string[]
     */
    public function getProjectedEvents(): array
    {
        return array_keys($this->getEventHandlers());
    }

    /**
     * Returns a mapping of all methods that handle events
     * raised by this aggregate using the record() method.
     *
     * All handlers can receive more than one argument:
     * - In cases of StoredEvents there's only one.
     * - For individual (unpacked) events the real instance
     *   is being provided as the second argument.
     *
     * @return Closure[]|array<string,Closure>
     */
    protected function getEventHandlers(): array
    {
        return [];
    }
}
