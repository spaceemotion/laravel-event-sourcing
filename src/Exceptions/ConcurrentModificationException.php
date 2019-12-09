<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\Exceptions;

use RuntimeException;
use Spaceemotion\LaravelEventSourcing\StoredEvent;
use Throwable;

class ConcurrentModificationException extends RuntimeException
{
    protected StoredEvent $event;

    public function __construct(string $message, StoredEvent $event, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->event = $event;
    }

    /**
     * Returns the event that could not be saved.
     */
    public function getStoredEvent(): StoredEvent
    {
        return $this->event;
    }

    /**
     * Creates a new exception instance that happened
     * while trying to create a snapshot for the
     * embedded aggregate root instance.
     */
    public static function forSnapshot(StoredEvent $event, ?Throwable $previous = null): self
    {
        return new self('Cannot store snapshot due to concurrent modification', $event, $previous);
    }

    /**
     * Creates a new exception instance that happened
     * while trying to save the given event.
     */
    public static function forEvent(StoredEvent $event, ?Throwable $previous = null): self
    {
        return new self('Cannot store event due to concurrent modification', $event, $previous);
    }
}
