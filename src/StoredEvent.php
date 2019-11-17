<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

class StoredEvent
{
    /** @var Event */
    public $event;

    /** @var AggregateRoot */
    public $aggregate;

    /** @var int */
    public $version;
}
