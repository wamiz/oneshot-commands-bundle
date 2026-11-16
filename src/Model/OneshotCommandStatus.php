<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Model;

final readonly class OneshotCommandStatus
{
    public function __construct(
        public OneshotCommandDefinition $definition,
        public ?Execution $lastExecution,
    ) {
    }

    public function state(): OneshotCommandState
    {
        return match (true) {
            null !== $this->lastExecution => $this->lastExecution->isSuccessful() ? OneshotCommandState::Succeeded : OneshotCommandState::Failed,
            $this->definition->immediate => OneshotCommandState::Pending,
            default => OneshotCommandState::Manual,
        };
    }
}
