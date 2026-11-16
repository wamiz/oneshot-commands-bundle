<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Tests\Fixtures\App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wamiz\OneshotCommands\Attribute\OneshotCommand;

/**
 * Hidden, aliased, and never played automatically.
 */
#[AsCommand('|app:manual|app:manual-alias')]
#[OneshotCommand(immediate: false)]
final class ManualCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}
