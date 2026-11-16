<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Tests\Fixtures\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Wamiz\OneshotCommands\OneshotCommandsBundle;
use Wamiz\OneshotCommands\Persistence\DbalExecutionStore;
use Wamiz\OneshotCommands\Persistence\InMemoryExecutionStore;

/**
 * Each environment is a configuration of the bundle:
 * - `test`: DBAL store on a SQLite file, shared with the processes spawned by wamiz:oneshot-commands:run;
 * - `in_memory`: in-memory store, mock clock;
 * - `custom_store`: a store service of the application.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string DATABASE_PATH = __DIR__.'/var/test.sqlite';

    /**
     * Set by the test suite, so its containers are compiled in the suite's process (and covered),
     * apart from the ones of the console processes it spawns.
     */
    public static string $cacheNamespace = 'console';

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new OneshotCommandsBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return __DIR__.'/var/cache/'.self::$cacheNamespace.'/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return __DIR__.'/var/log';
    }

    /**
     * Creates the SQLite database of the `test` environment, empty.
     */
    public static function resetDatabase(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS '.DbalExecutionStore::DEFAULT_TABLE_NAME);
        $schema = new DbalExecutionStore($connection)->configureSchema(new Schema(), $connection);
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///'.self::DATABASE_PATH],
            'orm' => [],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->load(__NAMESPACE__.'\\Command\\', __DIR__.'/Command/');

        match ($this->environment) {
            'in_memory' => $this->configureInMemory($container),
            'custom_store' => $this->configureCustomStore($container),
            default => null,
        };
    }

    private function configureInMemory(ContainerConfigurator $container): void
    {
        $container->extension('wamiz_oneshot_commands', ['store' => 'in_memory']);
        $container->services()->set('clock', MockClock::class)->args(['2026-11-16 10:00:00'])->public();
    }

    private function configureCustomStore(ContainerConfigurator $container): void
    {
        $container->extension('wamiz_oneshot_commands', ['store' => 'app.execution_store']);
        $container->services()->set('app.execution_store', InMemoryExecutionStore::class)->public();
    }
}
