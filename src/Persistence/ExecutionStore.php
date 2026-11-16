<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Persistence;

use Wamiz\OneshotCommands\Model\Execution;

/**
 * Keeps the last run of each one-shot command.
 */
interface ExecutionStore
{
    public function find(string $commandName): ?Execution;

    /**
     * @return array<string, Execution> indexed by command name
     */
    public function all(): array;

    /**
     * Records a run, replacing the previous one of the same command.
     */
    public function save(Execution $execution): void;

    public function remove(string $commandName): void;

    /**
     * Removes the runs of every command not listed (commands deleted from the code).
     *
     * @param list<string> $commandNames
     */
    public function removeAllExcept(array $commandNames): void;
}
