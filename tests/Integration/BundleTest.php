<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Wamiz\OneshotCommands\Model\Execution;
use Wamiz\OneshotCommands\Persistence\DbalExecutionStore;
use Wamiz\OneshotCommands\Persistence\ExecutionStore;
use Wamiz\OneshotCommands\Persistence\InMemoryExecutionStore;
use Wamiz\OneshotCommands\Registry\OneshotCommandRegistry;
use Wamiz\OneshotCommands\Tests\TestApplication;

it('registers the one-shot commands of the application', function (): void {
    $registry = TestApplication::boot('in_memory')->service(OneshotCommandRegistry::class, OneshotCommandRegistry::class);

    expect($registry->names())->toBe(['app:failing', 'app:manual', 'app:slow', 'app:succeeding'])
        ->and($registry->all()['app:succeeding']->args)->toBe(['--flag', 'value'])
        ->and($registry->all()['app:manual']->immediate)->toBeFalse();
});

it('uses the DBAL store by default, and adds its table to the ORM schema', function (): void {
    $app = TestApplication::boot('test');
    $entityManager = $app->service('doctrine.orm.entity_manager', EntityManagerInterface::class);

    expect($app->store())->toBeInstanceOf(DbalExecutionStore::class)
        ->and(new SchemaTool($entityManager)->getSchemaFromMetadata([])->hasTable(DbalExecutionStore::DEFAULT_TABLE_NAME))->toBeTrue();
});

it('uses the store of the application', function (): void {
    $app = TestApplication::boot('custom_store');

    expect($app->store())->toBe($app->service('app.execution_store', ExecutionStore::class));
});

it('records the runs of one-shot commands, by their canonical name', function (): void {
    $app = TestApplication::boot('in_memory');
    $app->console->run(['command' => 'app:manual-alias']);
    $app->console->run(['command' => 'app:failing']);
    $app->console->run(['command' => 'app:regular']);

    $now = new DateTimeImmutable('2026-11-16 10:00:00');
    expect($app->store())->toBeInstanceOf(InMemoryExecutionStore::class)
        ->and($app->store()->all())->toEqual([
            'app:manual' => new Execution('app:manual', $now, 0),
            'app:failing' => new Execution('app:failing', $now, 3),
        ]);
});
