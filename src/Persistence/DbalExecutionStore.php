<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Persistence;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Wamiz\OneshotCommands\Model\Execution;

final readonly class DbalExecutionStore implements ExecutionStore
{
    public const string DEFAULT_TABLE_NAME = 'oneshot_command_execution';

    /**
     * @param non-empty-string $tableName
     */
    public function __construct(
        private Connection $connection,
        private string $tableName = self::DEFAULT_TABLE_NAME,
    ) {
    }

    public function find(string $commandName): ?Execution
    {
        $row = $this->connection->fetchAssociative(
            "SELECT command_name, executed_at, exit_code, duration_ms FROM {$this->tableName} WHERE command_name = ?",
            [$commandName],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function all(): array
    {
        $executions = [];
        foreach ($this->connection->fetchAllAssociative("SELECT command_name, executed_at, exit_code, duration_ms FROM {$this->tableName}") as $row) {
            $execution = $this->hydrate($row);
            $executions[$execution->commandName] = $execution;
        }

        return $executions;
    }

    public function save(Execution $execution): void
    {
        $this->connection->transactional(function (Connection $connection) use ($execution): void {
            $connection->delete($this->tableName, ['command_name' => $execution->commandName]);
            $connection->insert(
                $this->tableName,
                [
                    'command_name' => $execution->commandName,
                    'executed_at' => $execution->executedAt,
                    'exit_code' => $execution->exitCode,
                    'duration_ms' => $execution->durationMs,
                ],
                [
                    'command_name' => Types::STRING,
                    'executed_at' => Types::DATETIME_IMMUTABLE,
                    'exit_code' => Types::INTEGER,
                    'duration_ms' => Types::INTEGER,
                ],
            );
        });
    }

    public function remove(string $commandName): void
    {
        $this->connection->delete($this->tableName, ['command_name' => $commandName]);
    }

    public function removeAllExcept(array $commandNames): void
    {
        if ([] === $commandNames) {
            $this->connection->executeStatement("DELETE FROM {$this->tableName}");

            return;
        }

        $this->connection->executeStatement(
            "DELETE FROM {$this->tableName} WHERE command_name NOT IN (?)",
            [$commandNames],
            [ArrayParameterType::STRING],
        );
    }

    /**
     * Adds the execution table to the schema of the store's connection, unless it is already there.
     */
    public function configureSchema(Schema $schema, Connection $forConnection): Schema
    {
        if ($forConnection !== $this->connection || $schema->hasTable($this->tableName)) {
            return $schema;
        }

        return $schema->edit()->addTable($this->createTable())->create();
    }

    public function createTable(): Table
    {
        return Table::editor()
            ->setUnquotedName($this->tableName)
            ->setColumns(
                Column::editor()->setUnquotedName('command_name')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('executed_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('exit_code')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('duration_ms')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('command_name')->create())
            ->create();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Execution
    {
        $platform = $this->connection->getDatabasePlatform();
        $commandName = $row['command_name'];
        $executedAt = $row['executed_at'];
        $exitCode = Type::getType(Types::INTEGER)->convertToPHPValue($row['exit_code'], $platform);
        $durationMs = Type::getType(Types::INTEGER)->convertToPHPValue($row['duration_ms'], $platform);
        \assert(\is_string($commandName) && \is_string($executedAt) && \is_int($exitCode) && (null === $durationMs || \is_int($durationMs)));

        // Dates are stored in UTC, without timezone: don't let the default timezone of PHP reinterpret them
        return new Execution($commandName, new \DateTimeImmutable($executedAt, new \DateTimeZone('UTC')), $exitCode, $durationMs);
    }
}
