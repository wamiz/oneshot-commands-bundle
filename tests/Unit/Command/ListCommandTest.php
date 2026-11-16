<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Wamiz\OneshotCommands\Command\ListCommand;
use Wamiz\OneshotCommands\Persistence\InMemoryExecutionStore;
use Wamiz\OneshotCommands\Registry\OneshotCommandLister;
use Wamiz\OneshotCommands\Registry\OneshotCommandRegistry;

it('says when there is no one-shot command', function (): void {
    $tester = new CommandTester(new ListCommand(new OneshotCommandLister(new OneshotCommandRegistry(), new InMemoryExecutionStore())));

    expect($tester->execute([]))->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('No one-shot command found.');
});
