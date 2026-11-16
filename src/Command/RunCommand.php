<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Command;

use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wamiz\OneshotCommands\Model\Execution;
use Wamiz\OneshotCommands\Persistence\ExecutionStore;
use Wamiz\OneshotCommands\Registry\OneshotCommandLister;
use Wamiz\OneshotCommands\Runner\ProcessRunner;

#[AsCommand('wamiz:oneshot-commands:run', 'Run the immediate one-shot commands that never ran')]
final class RunCommand extends Command
{
    public function __construct(
        private readonly OneshotCommandLister $lister,
        private readonly ProcessRunner $runner,
        private readonly ExecutionStore $store,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the commands that would run, without running them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pending = $this->lister->pending();
        if ([] === $pending) {
            StatusTable::render($io, $this->lister->statuses(), []);
            $io->success('No pending one-shot command.');

            return Command::SUCCESS;
        }

        if (true === $input->getOption('dry-run')) {
            $io->listing(array_map(static fn ($definition): string => trim($definition->name.' '.implode(' ', $definition->args)), $pending));

            return Command::SUCCESS;
        }

        $ranNow = [];
        $failures = [];
        foreach ($pending as $definition) {
            $io->section(trim($definition->name.' '.implode(' ', $definition->args)));
            $startedAt = $this->clock->now();
            $exitCode = $this->runner->run($definition, static fn (string $buffer) => $output->write($buffer, false, OutputInterface::OUTPUT_RAW));
            $endedAt = $this->clock->now();

            // The child process records its own run; recording it here as well covers the runs
            // the child could not record (fatal error, timeout, killed process).
            // The duration measured here includes the boot of the child process.
            $this->store->save(new Execution($definition->name, $endedAt, $exitCode, Execution::millisecondsBetween($startedAt, $endedAt)));

            $ranNow[] = $definition->name;
            if (Command::SUCCESS !== $exitCode) {
                $failures[] = \sprintf('%s (exit code %d)', $definition->name, $exitCode);
            }
        }

        $io->section('Summary');
        StatusTable::render($io, $this->lister->statuses(), $ranNow);

        if ([] !== $failures) {
            $io->error(['Some one-shot commands failed. They will not be retried: fix them, then use wamiz:oneshot-commands:reset to replay them.', ...$failures]);

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d one-shot command(s) run.', \count($pending)));

        return Command::SUCCESS;
    }
}
