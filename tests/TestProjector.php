<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use Closure;
use Spaceemotion\LaravelEventSourcing\Projector;

class TestProjector extends Projector
{
    /** @var callable[]|array<string,callable|Closure> */
    public array $handlers;

    /**
     * @param  callable[]|array<string,callable|Closure>  $handlers
     */
    public function __construct(array $handlers)
    {
        $this->handlers = $handlers;
    }

    protected function getEventHandlers(): array
    {
        return $this->handlers;
    }
}
