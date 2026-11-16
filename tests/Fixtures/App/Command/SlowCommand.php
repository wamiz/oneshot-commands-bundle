<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Tests\Fixtures\App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Wamiz\OneshotCommands\Attribute\OneshotCommand;

/**
 * An invokable command, too slow for the process timeout of the tests.
 */
#[AsCommand('app:slow')]
#[OneshotCommand(immediate: false)]
final class SlowCommand
{
    public function __invoke(): int
    {
        sleep(5);

        return 0;
    }
}
