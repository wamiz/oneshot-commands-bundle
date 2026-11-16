<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;
use Wamiz\OneshotCommands\Model\OneshotCommandDefinition;
use Wamiz\OneshotCommands\Runner\ProcessRunner;

const CONSOLE = __DIR__.'/../Fixtures/App/bin/console';

it('runs a command in its own process, with its arguments, and streams its output', function (): void {
    $output = '';
    $exitCode = new ProcessRunner(CONSOLE, 'in_memory')->run(
        new OneshotCommandDefinition('app:succeeding', ['--flag', 'some value']),
        static function (string $buffer) use (&$output): void {
            $output .= $buffer;
        },
    );

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($output)->toContain('value=some value flag=yes');
});

it('returns the exit code of the command', function (): void {
    expect(new ProcessRunner(CONSOLE, 'in_memory')->run(new OneshotCommandDefinition('app:failing'), static fn (string $buffer) => null))->toBe(3);
});

it('fails when the process times out', function (): void {
    $output = '';
    $exitCode = new ProcessRunner(CONSOLE, 'in_memory', 0.5)->run(
        new OneshotCommandDefinition('app:slow'),
        static function (string $buffer) use (&$output): void {
            $output .= $buffer;
        },
    );

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output)->toContain('exceeded the timeout of 0.5 seconds');
});
