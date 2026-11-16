<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Tests\Fixtures\App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wamiz\OneshotCommands\Attribute\OneshotCommand;

#[AsCommand('app:failing')]
#[OneshotCommand]
final class FailingCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Something went wrong');

        return 3;
    }
}
