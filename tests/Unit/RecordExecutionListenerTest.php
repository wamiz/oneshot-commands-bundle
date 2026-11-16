<?php

declare(strict_types=1);

use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Wamiz\OneshotCommands\EventListener\RecordExecutionListener;
use Wamiz\OneshotCommands\Model\Execution;
use Wamiz\OneshotCommands\Model\OneshotCommandDefinition;
use Wamiz\OneshotCommands\Persistence\InMemoryExecutionStore;
use Wamiz\OneshotCommands\Registry\OneshotCommandRegistry;

function start(RecordExecutionListener $listener, Command $command): void
{
    $listener->onConsoleCommand(new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput()));
}

function terminate(RecordExecutionListener $listener, Command $command, int $exitCode): void
{
    $listener->onConsoleTerminate(new ConsoleTerminateEvent($command, new ArrayInput([]), new NullOutput(), $exitCode));
}

it('subscribes to the end of console commands', function (): void {
    expect(RecordExecutionListener::getSubscribedEvents())->toBe([
        ConsoleEvents::COMMAND => 'onConsoleCommand',
        ConsoleEvents::TERMINATE => 'onConsoleTerminate',
    ]);
});

it('records the runs of one-shot commands only, with their duration', function (): void {
    $store = new InMemoryExecutionStore();
    $clock = new MockClock('2026-11-16 10:00:00', 'UTC');
    $listener = new RecordExecutionListener(new OneshotCommandRegistry([new OneshotCommandDefinition('app:oneshot')]), $store, $clock);

    foreach ([$oneshot = new Command('app:oneshot'), $regular = new Command('app:regular'), $unnamed = new Command()] as $command) {
        start($listener, $command);
    }
    $clock->sleep(1.2346);
    terminate($listener, $oneshot, 3);
    terminate($listener, $regular, 0);
    terminate($listener, $unnamed, 0);

    expect($store->all())->toEqual(['app:oneshot' => new Execution('app:oneshot', $clock->now(), 3, 1235)]);
});

it('records a run without duration when its start is unknown', function (): void {
    $store = new InMemoryExecutionStore();
    $listener = new RecordExecutionListener(new OneshotCommandRegistry([new OneshotCommandDefinition('app:oneshot')]), $store, new MockClock());

    terminate($listener, new Command('app:oneshot'), 0);

    expect($store->find('app:oneshot')?->durationMs)->toBeNull();
});
