<?php

declare(strict_types=1);

use Wamiz\OneshotCommands\Model\Execution;
use Wamiz\OneshotCommands\Persistence\DbalExecutionStore;
use Wamiz\OneshotCommands\Persistence\ExecutionStore;
use Wamiz\OneshotCommands\Persistence\InMemoryExecutionStore;

dataset('stores', [
    'in memory' => fn (): ExecutionStore => new InMemoryExecutionStore(),
    'dbal' => fn (): ExecutionStore => new DbalExecutionStore(sqliteConnection()),
]);

function execution(string $name, string $date = '2026-11-16 10:00:00', int $exitCode = 0, ?int $durationMs = 42): Execution
{
    return new Execution($name, new DateTimeImmutable($date, new DateTimeZone('UTC')), $exitCode, $durationMs);
}

it('finds nothing in an empty store', function (ExecutionStore $store): void {
    expect($store->find('app:foo'))->toBeNull()
        ->and($store->all())->toBe([]);
})->with('stores');

it('saves and finds an execution', function (ExecutionStore $store): void {
    $store->save(execution('app:foo', exitCode: 3));

    expect($store->find('app:foo'))->toEqual(execution('app:foo', exitCode: 3))
        ->and($store->all())->toEqual(['app:foo' => execution('app:foo', exitCode: 3)]);
})->with('stores');

it('keeps the date in UTC and the duration, whatever the default timezone', function (ExecutionStore $store): void {
    $defaultTimezone = date_default_timezone_get();
    date_default_timezone_set('Pacific/Auckland');
    try {
        $store->save(execution('app:foo', '2026-11-16 10:00:00'));
        $store->save(execution('app:bar', durationMs: null));

        expect($store->find('app:foo')?->executedAt->format('Y-m-d H:i:s e'))->toBe('2026-11-16 10:00:00 UTC')
            ->and($store->find('app:foo')?->durationMs)->toBe(42)
            ->and($store->find('app:bar')?->durationMs)->toBeNull();
    } finally {
        date_default_timezone_set($defaultTimezone);
    }
})->with('stores');

it('keeps only the last run of a command', function (ExecutionStore $store): void {
    $store->save(execution('app:foo', '2026-11-16 10:00:00', 1));
    $store->save(execution('app:foo', '2026-11-17 10:00:00', 0));

    expect($store->all())->toEqual(['app:foo' => execution('app:foo', '2026-11-17 10:00:00', 0)]);
})->with('stores');

it('removes an execution', function (ExecutionStore $store): void {
    $store->save(execution('app:foo'));
    $store->save(execution('app:bar'));
    $store->remove('app:foo');

    expect($store->find('app:foo'))->toBeNull()
        ->and(array_keys($store->all()))->toBe(['app:bar']);
})->with('stores');

it('removes the executions of the commands not listed', function (ExecutionStore $store): void {
    $store->save(execution('app:foo'));
    $store->save(execution('app:bar'));
    $store->save(execution('app:baz'));
    $store->removeAllExcept(['app:bar', 'app:unknown']);

    expect(array_keys($store->all()))->toBe(['app:bar']);
})->with('stores');

it('removes every execution when no command is listed', function (ExecutionStore $store): void {
    $store->save(execution('app:foo'));
    $store->removeAllExcept([]);

    expect($store->all())->toBe([]);
})->with('stores');
