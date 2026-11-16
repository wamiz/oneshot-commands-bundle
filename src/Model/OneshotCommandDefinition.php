<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Model;

final readonly class OneshotCommandDefinition
{
    /**
     * @param list<string> $args
     */
    public function __construct(
        public string $name,
        public array $args = [],
        public bool $immediate = true,
    ) {
    }
}
