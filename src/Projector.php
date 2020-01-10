<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;

abstract class Projector
{
    public function register(Dispatcher $events): void
    {
        foreach ($this->getEventHandlers() as $event => $handler) {
            $events->listen($event, $handler);
        }
    }

    /**
     * Returns a mapping of all methods that handle events
     * raised by this aggregate using the record() method.
     *
     * @return callable[]|array<string,callable|Closure>
     */
    protected function getEventHandlers(): array
    {
        return [];
    }
}
