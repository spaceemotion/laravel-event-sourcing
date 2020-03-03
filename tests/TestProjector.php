<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Tests;

use Closure;
use Spaceemotion\LaravelEventSourcing\Projector;

class TestProjector extends Projector
{
    /** @var Closure[]|array<string,Closure> */
    public array $handlers;

    /**
     * @param  Closure[]|array<string,Closure>  $handlers
     */
    public function __construct(array $handlers)
    {
        $this->handlers = $handlers;
    }

    /**
     * {@inheritDoc}
     */
    protected function getEventHandlers(): array
    {
        return $this->handlers;
    }
}
