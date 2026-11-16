<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;
use Wamiz\OneshotCommands\Model\Execution;
use Wamiz\OneshotCommands\Tests\TestApplication;

it('lists the one-shot commands and their last run', function (): void {
    $app = TestApplication::boot('in_memory');
    $app->store()->save(new Execution('app:failing', new DateTimeImmutable('2026-11-15 09:30:00', new DateTimeZone('Europe/Paris')), 3, 1250));
    $app->store()->save(new Execution('app:deleted', new DateTimeImmutable(), 0));

    expect($app->console->run(['command' => 'wamiz:oneshot-commands:list']))->toBe(Command::SUCCESS);

    expect(explode("\n", normalizedDisplay($app->console->getDisplay())))->toContain(
        'Command Arguments Immediate Last run (UTC) Exit code Duration Status',
        'app:failing yes 2026-11-15 08:30:00 3 1250 ms failed',
        'app:manual no - - - manual',
        'app:slow no - - - manual',
        'app:succeeding --flag value yes - - - pending',
    )->and($app->console->getDisplay())->not->toContain('app:deleted')
        ->and($app->store()->find('app:deleted'))->toBeNull();
});

it('resets a one-shot command', function (): void {
    $app = TestApplication::boot('in_memory');
    $app->store()->save(new Execution('app:failing', new DateTimeImmutable(), 3));

    expect($app->console->run(['command' => 'wamiz:oneshot-commands:reset', 'command-name' => 'app:failing']))->toBe(Command::SUCCESS)
        ->and($app->console->getDisplay())->toContain('"app:failing" has been reset')
        ->and($app->store()->find('app:failing'))->toBeNull();
});

it('refuses to reset a command that is not a one-shot', function (): void {
    $app = TestApplication::boot('in_memory');

    expect($app->console->run(['command' => 'wamiz:oneshot-commands:reset', 'command-name' => 'app:regular']))->toBe(Command::FAILURE)
        ->and($app->console->getDisplay())->toContain('"app:regular" is not a one-shot command.');
});

it('runs the pending one-shot commands once, each in its own process', function (): void {
    $app = TestApplication::boot('test');

    expect($app->console->run(['command' => 'wamiz:oneshot-commands:run']))->toBe(Command::FAILURE)
        ->and($app->console->getDisplay())->toContain('value=value flag=yes', 'Something went wrong', 'app:failing (exit code 3)')
        ->and(array_map(static fn (Execution $execution): array => [$execution->exitCode, $execution->durationMs > 0], $app->store()->all()))
        ->toEqual(['app:failing' => [3, true], 'app:succeeding' => [0, true]]);

    $summary = normalizedDisplay($app->console->getDisplay());
    expect($summary)->toContain('Ran now Command Arguments Immediate Last run (UTC) Exit code Duration Status', 'no app:manual no - - - manual')
        ->toMatch('/^yes app:failing yes \d{4}-\d\d-\d\d \d\d:\d\d:\d\d 3 \d+ ms failed$/m')
        ->toMatch('/^yes app:succeeding --flag value yes \d{4}-\d\d-\d\d \d\d:\d\d:\d\d 0 \d+ ms succeeded$/m');

    expect($app->console->run(['command' => 'wamiz:oneshot-commands:run']))->toBe(Command::SUCCESS)
        ->and($app->console->getDisplay())->toContain('No pending one-shot command.')
        ->and(normalizedDisplay($app->console->getDisplay()))->toMatch('/^no app:failing yes \S+ \S+ 3 \d+ ms failed$/m');
});

it('succeeds when every pending one-shot command succeeds', function (): void {
    $app = TestApplication::boot('test');
    $app->store()->save(new Execution('app:failing', new DateTimeImmutable(), 3));

    expect($app->console->run(['command' => 'wamiz:oneshot-commands:run']))->toBe(Command::SUCCESS)
        ->and($app->console->getDisplay())->toContain('1 one-shot command(s) run.');
});

it('lists the pending one-shot commands without running them', function (): void {
    $app = TestApplication::boot('test');

    expect($app->console->run(['command' => 'wamiz:oneshot-commands:run', '--dry-run' => true]))->toBe(Command::SUCCESS)
        ->and($app->console->getDisplay())->toContain('* app:failing', '* app:succeeding --flag value')
        ->and($app->store()->all())->toBe([]);
});
