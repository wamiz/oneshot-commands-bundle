<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Command;

use Symfony\Component\Console\Style\SymfonyStyle;
use Wamiz\OneshotCommands\Model\OneshotCommandStatus;

/**
 * Renders the status of the one-shot commands, for `list` and `run`.
 *
 * @internal
 */
final class StatusTable
{
    /**
     * @param list<OneshotCommandStatus> $statuses
     * @param list<string>|null          $ranNow   the commands run by this `run`, null outside of `run`
     */
    public static function render(SymfonyStyle $io, array $statuses, ?array $ranNow = null): void
    {
        $headers = ['Command', 'Arguments', 'Immediate', 'Last run (UTC)', 'Exit code', 'Duration', 'Status'];
        if (null !== $ranNow) {
            array_unshift($headers, 'Ran now');
        }

        $rows = [];
        foreach ($statuses as $status) {
            $execution = $status->lastExecution;
            $row = [
                $status->definition->name,
                implode(' ', $status->definition->args),
                $status->definition->immediate ? 'yes' : 'no',
                $execution?->executedAt->format('Y-m-d H:i:s') ?? '-',
                $execution->exitCode ?? '-',
                null === $execution?->durationMs ? '-' : \sprintf('%d ms', $execution->durationMs),
                $status->state()->value,
            ];
            if (null !== $ranNow) {
                array_unshift($row, \in_array($status->definition->name, $ranNow, true) ? 'yes' : 'no');
            }
            $rows[] = $row;
        }

        $io->table($headers, $rows);
    }
}
