<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\EventListener;

use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wamiz\OneshotCommands\Model\Execution;
use Wamiz\OneshotCommands\Persistence\ExecutionStore;
use Wamiz\OneshotCommands\Registry\OneshotCommandRegistry;

/**
 * Records every run of a one-shot command, whoever launched it, with its duration.
 */
final readonly class RecordExecutionListener implements EventSubscriberInterface
{
    /** @var \WeakMap<Command, \DateTimeImmutable> */
    private \WeakMap $startedAt;

    public function __construct(
        private OneshotCommandRegistry $registry,
        private ExecutionStore $store,
        private ClockInterface $clock,
    ) {
        $this->startedAt = new \WeakMap();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onConsoleCommand',
            ConsoleEvents::TERMINATE => 'onConsoleTerminate',
        ];
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (null !== $command && $this->registry->has((string) $command->getName())) {
            $this->startedAt[$command] = $this->clock->now();
        }
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();
        $name = $command?->getName();
        if (null === $command || null === $name || !$this->registry->has($name)) {
            return;
        }

        $now = $this->clock->now();
        $startedAt = $this->startedAt[$command] ?? null;
        unset($this->startedAt[$command]);

        $this->store->save(new Execution(
            $name,
            $now,
            $event->getExitCode(),
            null === $startedAt ? null : Execution::millisecondsBetween($startedAt, $now),
        ));
    }
}
