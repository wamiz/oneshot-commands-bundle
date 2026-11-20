# wamiz/oneshot-commands-bundle

Run the Symfony console commands marked as **one-shot** exactly once, typically on deploy, and keep track of their last run.

A one-shot command is a data change (fix, purge, backfill...) shipped as a console command rather than as a Doctrine migration: migrations are for the schema only.

- Mark a command with `#[OneshotCommand]`.
- `wamiz:oneshot-commands:run` (in your deploy pipeline) runs, each in its own process, the one-shot commands that never ran.
- Every run of a one-shot command, automatic or by hand, is recorded: date (UTC), exit code and duration. Only the last run is kept.
- `wamiz:oneshot-commands:list` shows what ran and what did not.
- A failed command is **not** retried: fix it, then `wamiz:oneshot-commands:reset` it so the next `run` plays it again.
- Once a one-shot command is deleted from the code, its run is silently forgotten.

Requirements: PHP 8.4+, Symfony 7.4 or 8.x, and (for the default store) Doctrine DBAL 4.5+.

## Installation

```bash
composer require wamiz/oneshot-commands-bundle
```

```php
// config/bundles.php
return [
    // ...
    Wamiz\OneshotCommands\OneshotCommandsBundle::class => ['all' => true],
];
```

The runs are stored in the `oneshot_command_execution` table. With Doctrine ORM, the bundle adds it to the ORM schema, so it shows up in `doctrine:migrations:diff`:

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Without the ORM, create it by hand:

```sql
CREATE TABLE oneshot_command_execution (
    command_name VARCHAR(255) NOT NULL,
    executed_at DATETIME NOT NULL,
    exit_code INT NOT NULL,
    duration_ms INT DEFAULT NULL,
    PRIMARY KEY (command_name)
);
```

## Configuration

Defaults:

```yaml
# config/packages/wamiz_oneshot_commands.yaml
wamiz_oneshot_commands:
    # "dbal", "in_memory", or the id of a service implementing Wamiz\OneshotCommands\Persistence\ExecutionStore
    store: dbal
    dbal:
        connection: default
        table_name: oneshot_command_execution
    # The console the one-shot commands are run with
    console_path: '%kernel.project_dir%/bin/console'
    # Timeout of each one-shot command, in seconds (null: none)
    process_timeout: null
```

## Writing a one-shot command

```php
use Symfony\Component\Console\Attribute\AsCommand;
use Wamiz\OneshotCommands\Attribute\OneshotCommand;

#[AsCommand('app:data:fix-breed-names', 'Fix the misspelled breed names')]
#[OneshotCommand(args: ['--dry-run'])]
final class FixBreedNamesCommand extends Command
{
    // ...
}
```

- `args`: the command-line tokens `wamiz:oneshot-commands:run` passes to the command, e.g. `['--dry-run', '--limit=100', 'file.csv']`. The bundle gives no special meaning to any of them, `--dry-run` included.
- `immediate: false`: never played by `wamiz:oneshot-commands:run`, only run by hand. Its runs are still recorded.

A one-shot command should be **idempotent** and **non-interactive**: it runs in a process without a terminal.

## Lifecycle

1. Merge and deploy the command. The deploy pipeline calls `wamiz:oneshot-commands:run`, which plays it once with its `args`.
2. Check the outcome with `wamiz:oneshot-commands:list`. If it ran with `--dry-run`, run it for real by hand: this run replaces the previous one.
3. If it failed, fix it, deploy, then `wamiz:oneshot-commands:reset <name>`: the next `run` plays it again.
4. Delete the command from the code. Its run is forgotten on the next `list` or `run`.

## Commands

| Command | |
| --- | --- |
| `wamiz:oneshot-commands:list` | The one-shot commands, their last run (date in UTC, exit code, duration) and status: `pending`, `manual`, `succeeded`, `failed`. |
| `wamiz:oneshot-commands:run [--dry-run]` | Runs the immediate one-shot commands that never ran, in alphabetical order, each in its own process. Ends with a summary table of every one-shot command, showing which ones ran now. Exits with `1` if one of them failed. `--dry-run` only lists them. |
| `wamiz:oneshot-commands:reset <name>` | Forgets the last run of a one-shot command. |

Example deploy step:

```bash
bin/console wamiz:oneshot-commands:run --no-interaction
```

## How runs are recorded

A listener on `console.command` / `console.terminate` records the run of any one-shot command (name, end date in UTC, exit code, duration in milliseconds), however it was launched. `wamiz:oneshot-commands:run` also records the exit code of each process it spawns, which covers the runs the command could not record itself (fatal error, timeout, killed process).

Durations are therefore measured in two ways: around the command itself for a run by hand, and around the whole process (kernel boot included) for a run by `wamiz:oneshot-commands:run`.

## Development

```bash
composer ci   # php-cs-fixer, PHPStan (level max), Pest with 100% coverage
```

## License

Proprietary, all rights reserved: see [LICENSE](LICENSE). The source code is public for reference only; using, copying or distributing it requires the prior written permission of Wamiz.
