<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Filesystem\Filesystem;
use Wamiz\OneshotCommands\Tests\Fixtures\App\TestKernel;
use Wamiz\OneshotCommands\Tests\TestApplication;

TestKernel::$cacheNamespace = 'suite';
new Filesystem()->remove(__DIR__.'/Fixtures/App/var/cache/suite');

pest()->afterEach(fn () => TestApplication::shutdown())->in('Integration');

/**
 * The lines of a console output, trimmed, with their spaces collapsed.
 */
function normalizedDisplay(string $display): string
{
    return implode("\n", array_map(
        static fn (string $line): string => (string) preg_replace('/\s+/', ' ', trim($line)),
        explode("\n", $display),
    ));
}

/**
 * An in-memory SQLite connection holding an empty execution table.
 */
function sqliteConnection(): Connection
{
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    TestKernel::resetDatabase($connection);

    return $connection;
}
