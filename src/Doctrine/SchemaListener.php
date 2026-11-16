<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Doctrine;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Wamiz\OneshotCommands\Persistence\DbalExecutionStore;

/**
 * Adds the execution table to the ORM schema, so `doctrine:migrations:diff` and `doctrine:schema:update` see it.
 */
final readonly class SchemaListener
{
    public function __construct(
        private DbalExecutionStore $store,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $event->setSchema($this->store->configureSchema($event->getSchema(), $event->getEntityManager()->getConnection()));
    }
}
