<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Attribute;

/**
 * Marks a console command as a one-shot.
 *
 * - `immediate: true`: `wamiz:oneshot-commands:run` plays it once, with `args`, as long as it has never run;
 * - `immediate: false`: never played automatically, only run by hand.
 *
 * Every run of the command (automatic or by hand, whatever its arguments) is recorded, and replaces the previous one.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class OneshotCommand
{
    /**
     * @param list<string> $args raw command-line tokens, e.g. `['--dry-run', '--limit=100', 'file.csv']`
     */
    public function __construct(
        public array $args = [],
        public bool $immediate = true,
    ) {
    }
}
