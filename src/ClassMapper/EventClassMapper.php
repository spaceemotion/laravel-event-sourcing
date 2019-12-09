<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\ClassMapper;

/**
 * Maps the class names of events for persistence. Instead of
 * using get_class this makes sure that future refactorings
 * don't break replaying any previously recorded events.
 */
interface EventClassMapper
{
    /**
     * Returns the public event class name.
     */
    public function encode(string $class): string;

    /**
     * Returns the internal event class name.
     */
    public function decode(string $name): string;
}
