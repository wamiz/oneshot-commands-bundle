<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Registry;

use Wamiz\OneshotCommands\Model\OneshotCommandDefinition;
use Wamiz\OneshotCommands\Model\OneshotCommandState;
use Wamiz\OneshotCommands\Model\OneshotCommandStatus;
use Wamiz\OneshotCommands\Persistence\ExecutionStore;

/**
 * Crosses the one-shot commands of the code with their recorded runs.
 *
 * Runs of commands that no longer exist in the code are removed from the store along the way.
 */
final readonly class OneshotCommandLister
{
    public function __construct(
        private OneshotCommandRegistry $registry,
        private ExecutionStore $store,
    ) {
    }

    /**
     * @return list<OneshotCommandStatus> sorted by command name
     */
    public function statuses(): array
    {
        $this->store->removeAllExcept($this->registry->names());
        $executions = $this->store->all();

        $statuses = [];
        foreach ($this->registry->all() as $name => $definition) {
            $statuses[] = new OneshotCommandStatus($definition, $executions[$name] ?? null);
        }

        return $statuses;
    }

    /**
     * The commands `wamiz:oneshot-commands:run` has to play: immediate ones that never ran.
     *
     * @return list<OneshotCommandDefinition>
     */
    public function pending(): array
    {
        return array_values(array_map(
            static fn (OneshotCommandStatus $status): OneshotCommandDefinition => $status->definition,
            array_filter($this->statuses(), static fn (OneshotCommandStatus $status): bool => OneshotCommandState::Pending === $status->state()),
        ));
    }
}
