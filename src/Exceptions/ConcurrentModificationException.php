<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Exceptions;

use Throwable;
use RuntimeException;
use Spaceemotion\LaravelEventSourcing\StoredEvent;

class ConcurrentModificationException extends RuntimeException
{
    /** @var StoredEvent */
    protected $event;

    public function __construct(string $message, StoredEvent $event, Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->event = $event;
    }

    /**
     * Returns the event that could not be saved.
     *
     * @return StoredEvent
     */
    public function getStoredEvent(): StoredEvent
    {
        return $this->event;
    }

    /**
     * Creates a new exception instance that happened
     * while trying to save the given event.
     *
     * @param  StoredEvent  $event
     * @param  Throwable|null  $previous
     * @return static
     */
    public static function forEvent(StoredEvent $event, Throwable $previous = null): self
    {
        return new self('Cannot store event due to concurrent modification', $event, $previous);
    }
}
