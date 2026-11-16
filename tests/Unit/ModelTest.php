<?php

declare(strict_types=1);

use Wamiz\OneshotCommands\Model\Execution;
use Wamiz\OneshotCommands\Model\OneshotCommandDefinition;
use Wamiz\OneshotCommands\Model\OneshotCommandState;
use Wamiz\OneshotCommands\Model\OneshotCommandStatus;

it('is successful only with a zero exit code', function (int $exitCode, bool $successful): void {
    expect(new Execution('app:foo', new DateTimeImmutable(), $exitCode)->isSuccessful())->toBe($successful);
})->with([[0, true], [1, false], [3, false]]);

it('stores the end of the run in UTC', function (): void {
    $execution = new Execution('app:foo', new DateTimeImmutable('2026-11-16 11:00:00', new DateTimeZone('Europe/Paris')), 0, 12);

    expect($execution->executedAt->format('Y-m-d H:i:s e'))->toBe('2026-11-16 10:00:00 UTC')
        ->and($execution->durationMs)->toBe(12);
});

it('computes a duration in milliseconds', function (string $start, string $end, int $milliseconds): void {
    expect(Execution::millisecondsBetween(new DateTimeImmutable($start), new DateTimeImmutable($end)))->toBe($milliseconds);
})->with([
    ['2026-11-16 10:00:00', '2026-11-16 10:00:00', 0],
    ['2026-11-16 10:00:00.000000', '2026-11-16 10:00:00.001499', 1],
    ['2026-11-16 10:00:00.250000', '2026-11-16 10:01:05.750000', 65500],
]);

it('computes the state of a one-shot command', function (bool $immediate, ?int $exitCode, OneshotCommandState $state): void {
    $execution = null === $exitCode ? null : new Execution('app:foo', new DateTimeImmutable(), $exitCode);

    expect(new OneshotCommandStatus(new OneshotCommandDefinition('app:foo', immediate: $immediate), $execution)->state())->toBe($state);
})->with([
    'never run, immediate' => [true, null, OneshotCommandState::Pending],
    'never run, manual' => [false, null, OneshotCommandState::Manual],
    'succeeded' => [true, 0, OneshotCommandState::Succeeded],
    'failed' => [false, 2, OneshotCommandState::Failed],
]);
