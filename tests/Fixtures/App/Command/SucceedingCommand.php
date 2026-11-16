<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Tests\Fixtures\App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wamiz\OneshotCommands\Attribute\OneshotCommand;

#[AsCommand('app:succeeding')]
#[OneshotCommand(args: ['--flag', 'value'])]
final class SucceedingCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('value', InputArgument::OPTIONAL)
            ->addOption('flag', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $value = $input->getArgument('value');
        $output->writeln(\sprintf('<info>value=%s flag=%s</info>', \is_string($value) ? $value : '', true === $input->getOption('flag') ? 'yes' : 'no'));

        return Command::SUCCESS;
    }
}
