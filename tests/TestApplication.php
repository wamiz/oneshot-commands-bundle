<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Tests;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Wamiz\OneshotCommands\Persistence\ExecutionStore;
use Wamiz\OneshotCommands\Tests\Fixtures\App\TestKernel;

/**
 * A booted test kernel, with its console and its test container.
 */
final class TestApplication
{
    private static ?TestKernel $kernel = null;
    private static mixed $exceptionHandler = null;

    private function __construct(
        public readonly ApplicationTester $console,
        public readonly ContainerInterface $container,
    ) {
    }

    /**
     * Boots the kernel of the given environment, with an empty store.
     */
    public static function boot(string $environment): self
    {
        self::shutdown();
        self::$exceptionHandler = self::currentExceptionHandler();
        $kernel = self::$kernel = new TestKernel($environment, false);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);

        if ('test' === $environment) {
            $connection = $container->get('doctrine.dbal.default_connection');
            \assert($connection instanceof Connection);
            TestKernel::resetDatabase($connection);
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);

        return new self(new ApplicationTester($application), $container);
    }

    /**
     * Shuts the kernel down, and removes the exception handlers it registered.
     */
    public static function shutdown(): void
    {
        if (null === self::$kernel) {
            return;
        }

        self::$kernel->shutdown();
        self::$kernel = null;
        while (self::$exceptionHandler !== self::currentExceptionHandler()) {
            restore_exception_handler();
        }
    }

    private static function currentExceptionHandler(): mixed
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function service(string $id, string $class): object
    {
        $service = $this->container->get($id);
        \assert($service instanceof $class);

        return $service;
    }

    public function store(): ExecutionStore
    {
        return $this->service(ExecutionStore::class, ExecutionStore::class);
    }
}
