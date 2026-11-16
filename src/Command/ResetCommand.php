<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wamiz\OneshotCommands\Persistence\ExecutionStore;
use Wamiz\OneshotCommands\Registry\OneshotCommandRegistry;

#[AsCommand('wamiz:oneshot-commands:reset', 'Forget the last run of a one-shot command, so it runs again')]
final class ResetCommand extends Command
{
    public function __construct(
        private readonly OneshotCommandRegistry $registry,
        private readonly ExecutionStore $store,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('command-name', InputArgument::REQUIRED, 'The one-shot command to reset');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('command-name');
        \assert(\is_string($name));

        if (!$this->registry->has($name)) {
            $io->error(\sprintf('"%s" is not a one-shot command.', $name));

            return Command::FAILURE;
        }

        $this->store->remove($name);
        $io->success(\sprintf('"%s" has been reset: it will run again with wamiz:oneshot-commands:run, if immediate.', $name));

        return Command::SUCCESS;
    }
}
