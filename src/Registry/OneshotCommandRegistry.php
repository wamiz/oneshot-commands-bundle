<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Registry;

use Wamiz\OneshotCommands\Model\OneshotCommandDefinition;

/**
 * The one-shot commands found in the code, collected at compile time.
 */
final readonly class OneshotCommandRegistry
{
    /** @var array<string, OneshotCommandDefinition> */
    private array $definitions;

    /**
     * @param iterable<OneshotCommandDefinition> $definitions
     */
    public function __construct(iterable $definitions = [])
    {
        $indexed = [];
        foreach ($definitions as $definition) {
            $indexed[$definition->name] = $definition;
        }
        ksort($indexed);
        $this->definitions = $indexed;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    /**
     * @return array<string, OneshotCommandDefinition> sorted by name
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->definitions);
    }
}
