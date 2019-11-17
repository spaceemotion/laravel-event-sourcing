<?php

declare(strict_types=1);

namespace Spaceemotion\LaravelEventSourcing\ClassMapper;

use function array_flip;

class ConfigurableEventClassMapper implements EventClassMapper
{
    /** @var string[]|array<string, string> */
    protected $toClassName;

    /** @var string[]|array<string, string> */
    protected $fromClassName;

    public function __construct(array $classMap = [])
    {
        $this->toClassName = $classMap;
        $this->fromClassName = array_flip($this->toClassName);
    }

    public function encode(string $class): string
    {
        return $this->fromClassName[$class];
    }

    public function decode(string $name): string
    {
        return $this->toClassName[$name];
    }
}