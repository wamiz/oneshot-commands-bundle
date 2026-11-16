<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Wamiz\OneshotCommands\Doctrine\SchemaListener;
use Wamiz\OneshotCommands\Persistence\DbalExecutionStore;

function memoryConnection(): Connection
{
    return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
}

it('adds the execution table to the schema of its connection', function (): void {
    $connection = memoryConnection();
    $schema = new DbalExecutionStore($connection, 'my_table')->configureSchema(new Schema(), $connection);

    $table = $schema->getTable('my_table');
    expect(array_map(static fn (Column $column): string => $column->getName(), $table->getColumns()))
        ->toBe(['command_name', 'executed_at', 'exit_code', 'duration_ms'])
        ->and($table->getPrimaryKeyConstraint()?->getColumnNames())->toHaveCount(1);
});

it('leaves the schema of another connection alone', function (): void {
    $store = new DbalExecutionStore(memoryConnection());
    $schema = new Schema();

    expect($store->configureSchema($schema, memoryConnection()))->toBe($schema);
});

it('leaves a schema that already has the table alone', function (): void {
    $connection = memoryConnection();
    $store = new DbalExecutionStore($connection);
    $schema = $store->configureSchema(new Schema(), $connection);

    expect($store->configureSchema($schema, $connection))->toBe($schema);
});

it('adds the execution table to the ORM schema', function (): void {
    $connection = memoryConnection();
    $configuration = ORMSetup::createAttributeMetadataConfig([], true);
    $configuration->enableNativeLazyObjects(true);
    $event = new GenerateSchemaEventArgs(new EntityManager($connection, $configuration), new Schema());

    new SchemaListener(new DbalExecutionStore($connection))->postGenerateSchema($event);

    expect($event->getSchema()->hasTable(DbalExecutionStore::DEFAULT_TABLE_NAME))->toBeTrue();
});
