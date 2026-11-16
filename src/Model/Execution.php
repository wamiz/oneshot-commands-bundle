<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Model;

use Symfony\Component\Console\Command\Command;

/**
 * The last run of a one-shot command.
 */
final readonly class Execution
{
    /** When the run ended, in UTC. */
    public \DateTimeImmutable $executedAt;

    /**
     * @param int|null $durationMs null when the start of the run is unknown
     */
    public function __construct(
        public string $commandName,
        \DateTimeImmutable $executedAt,
        public int $exitCode,
        public ?int $durationMs = null,
    ) {
        $this->executedAt = $executedAt->setTimezone(new \DateTimeZone('UTC'));
    }

    public static function millisecondsBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) round(((float) $end->format('U.u') - (float) $start->format('U.u')) * 1000);
    }

    public function isSuccessful(): bool
    {
        return Command::SUCCESS === $this->exitCode;
    }
}
