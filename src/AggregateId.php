<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing;

interface AggregateId
{
    /**
     * Generates the next available ID.
     * e.g. can be random or the next digit in a sequence.
     *
     * @return static
     */
    public static function next(): self;

    /**
     * Parses the given serialized ID.
     *
     * @return static
     */
    public static function fromString(string $id): self;

    /**
     * Converts this ID into a serialized form.
     */
    public function __toString(): string;
}
