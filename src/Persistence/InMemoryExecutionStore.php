<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Persistence;

use Wamiz\OneshotCommands\Model\Execution;

final class InMemoryExecutionStore implements ExecutionStore
{
    /** @var array<string, Execution> */
    private array $executions = [];

    public function find(string $commandName): ?Execution
    {
        return $this->executions[$commandName] ?? null;
    }

    public function all(): array
    {
        return $this->executions;
    }

    public function save(Execution $execution): void
    {
        $this->executions[$execution->commandName] = $execution;
    }

    public function remove(string $commandName): void
    {
        unset($this->executions[$commandName]);
    }

    public function removeAllExcept(array $commandNames): void
    {
        $this->executions = array_intersect_key($this->executions, array_flip($commandNames));
    }
}
