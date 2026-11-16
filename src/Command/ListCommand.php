<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wamiz\OneshotCommands\Registry\OneshotCommandLister;

#[AsCommand('wamiz:oneshot-commands:list', 'List the one-shot commands and their last run')]
final class ListCommand extends Command
{
    public function __construct(
        private readonly OneshotCommandLister $lister,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $statuses = $this->lister->statuses();
        if ([] === $statuses) {
            $io->note('No one-shot command found.');

            return Command::SUCCESS;
        }

        StatusTable::render($io, $statuses);

        return Command::SUCCESS;
    }
}
