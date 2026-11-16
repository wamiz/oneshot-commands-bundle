<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Runner;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;
use Wamiz\OneshotCommands\Model\OneshotCommandDefinition;

/**
 * Runs a one-shot command in its own process, so a command that breaks its kernel
 * (closed EntityManager, exhausted memory...) does not affect the next ones.
 */
final readonly class ProcessRunner
{
    public function __construct(
        private string $consolePath,
        private string $environment,
        private ?float $timeout = null,
        private string $phpBinary = \PHP_BINARY,
    ) {
    }

    /**
     * @param callable(string): void $onOutput receives the output of the command as it comes
     *
     * @return int the exit code of the command
     */
    public function run(OneshotCommandDefinition $definition, callable $onOutput): int
    {
        $process = new Process(
            [$this->phpBinary, $this->consolePath, $definition->name, ...$definition->args, '--no-interaction'],
            env: ['APP_ENV' => $this->environment],
            timeout: $this->timeout,
        );

        try {
            return $process->run(static function (string $type, string $buffer) use ($onOutput): void {
                $onOutput($buffer);
            });
        } catch (RuntimeException $exception) {
            $onOutput(\PHP_EOL.$exception->getMessage().\PHP_EOL);

            return Command::FAILURE;
        }
    }
}
