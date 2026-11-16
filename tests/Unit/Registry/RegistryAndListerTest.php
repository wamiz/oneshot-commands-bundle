<?php

declare(strict_types=1);

use Wamiz\OneshotCommands\Model\Execution;
use Wamiz\OneshotCommands\Model\OneshotCommandDefinition;
use Wamiz\OneshotCommands\Model\OneshotCommandState;
use Wamiz\OneshotCommands\Model\OneshotCommandStatus;
use Wamiz\OneshotCommands\Persistence\InMemoryExecutionStore;
use Wamiz\OneshotCommands\Registry\OneshotCommandLister;
use Wamiz\OneshotCommands\Registry\OneshotCommandRegistry;

function registry(): OneshotCommandRegistry
{
    return new OneshotCommandRegistry([
        new OneshotCommandDefinition('app:c', ['--dry-run']),
        new OneshotCommandDefinition('app:a'),
        new OneshotCommandDefinition('app:b', immediate: false),
        new OneshotCommandDefinition('app:d'),
    ]);
}

it('indexes the definitions by name, sorted', function (): void {
    $registry = registry();

    expect($registry->names())->toBe(['app:a', 'app:b', 'app:c', 'app:d'])
        ->and($registry->all()['app:c']->args)->toBe(['--dry-run'])
        ->and($registry->has('app:a'))->toBeTrue()
        ->and($registry->has('app:unknown'))->toBeFalse()
        ->and(new OneshotCommandRegistry()->all())->toBe([]);
});

it('crosses the registry with the store, and forgets the commands deleted from the code', function (): void {
    $store = new InMemoryExecutionStore();
    $store->save(new Execution('app:a', new DateTimeImmutable(), 0));
    $store->save(new Execution('app:d', new DateTimeImmutable(), 1));
    $store->save(new Execution('app:deleted', new DateTimeImmutable(), 0));

    $statuses = new OneshotCommandLister(registry(), $store)->statuses();

    expect(array_map(static fn (OneshotCommandStatus $status): array => [$status->definition->name, $status->state()], $statuses))->toBe([
        ['app:a', OneshotCommandState::Succeeded],
        ['app:b', OneshotCommandState::Manual],
        ['app:c', OneshotCommandState::Pending],
        ['app:d', OneshotCommandState::Failed],
    ])->and(array_keys($store->all()))->toBe(['app:a', 'app:d']);
});

it('lists the immediate commands that never ran', function (): void {
    $store = new InMemoryExecutionStore();
    $store->save(new Execution('app:a', new DateTimeImmutable(), 0));

    $pending = new OneshotCommandLister(registry(), $store)->pending();

    expect(array_map(static fn (OneshotCommandDefinition $definition): string => $definition->name, $pending))->toBe(['app:c', 'app:d']);
});
