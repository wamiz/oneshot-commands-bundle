<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands;

use Doctrine\ORM\Tools\ToolEvents;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Wamiz\OneshotCommands\Command\ListCommand;
use Wamiz\OneshotCommands\Command\ResetCommand;
use Wamiz\OneshotCommands\Command\RunCommand;
use Wamiz\OneshotCommands\DependencyInjection\Compiler\OneshotCommandPass;
use Wamiz\OneshotCommands\Doctrine\SchemaListener;
use Wamiz\OneshotCommands\EventListener\RecordExecutionListener;
use Wamiz\OneshotCommands\Persistence\DbalExecutionStore;
use Wamiz\OneshotCommands\Persistence\ExecutionStore;
use Wamiz\OneshotCommands\Persistence\InMemoryExecutionStore;
use Wamiz\OneshotCommands\Registry\OneshotCommandLister;
use Wamiz\OneshotCommands\Registry\OneshotCommandRegistry;
use Wamiz\OneshotCommands\Runner\ProcessRunner;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class OneshotCommandsBundle extends AbstractBundle
{
    public const string STORE_DBAL = 'dbal';
    public const string STORE_IN_MEMORY = 'in_memory';

    protected string $extensionAlias = 'wamiz_oneshot_commands';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('store')
                    ->info(\sprintf('"%s", "%s", or the id of a service implementing %s', self::STORE_DBAL, self::STORE_IN_MEMORY, ExecutionStore::class))
                    ->defaultValue(self::STORE_DBAL)
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('dbal')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('connection')->defaultValue('default')->cannotBeEmpty()->end()
                        ->scalarNode('table_name')->defaultValue(DbalExecutionStore::DEFAULT_TABLE_NAME)->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->scalarNode('console_path')
                    ->info('The console the one-shot commands are run with')
                    ->defaultValue('%kernel.project_dir%/bin/console')
                    ->cannotBeEmpty()
                ->end()
                ->floatNode('process_timeout')
                    ->info('Timeout of each one-shot command, in seconds (null: none)')
                    ->defaultNull()
                ->end()
            ->end();
    }

    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        ['store' => $store, 'dbal' => $dbal, 'console_path' => $consolePath, 'process_timeout' => $processTimeout] = $config;
        // Guaranteed by the configuration tree
        \assert(\is_string($store) && \is_array($dbal) && \is_string($consolePath) && (null === $processTimeout || \is_float($processTimeout)));
        ['connection' => $connection, 'table_name' => $tableName] = $dbal;
        \assert(\is_string($connection) && \is_string($tableName) && '' !== $tableName);

        $services = $container->services();
        // Replaced by the application's "clock" service, when there is one (see OneshotCommandPass)
        $services->set(OneshotCommandPass::CLOCK_ID, NativeClock::class);
        $clock = service(OneshotCommandPass::CLOCK_ID);

        $storeId = match ($store) {
            self::STORE_DBAL => 'wamiz_oneshot_commands.store.dbal',
            self::STORE_IN_MEMORY => 'wamiz_oneshot_commands.store.in_memory',
            default => $store,
        };

        if (self::STORE_DBAL === $store) {
            $services->set($storeId, DbalExecutionStore::class)
                ->args([service(\sprintf('doctrine.dbal.%s_connection', $connection)), $tableName]);

            if (class_exists(ToolEvents::class)) {
                $services->set('wamiz_oneshot_commands.doctrine.schema_listener', SchemaListener::class)
                    ->args([service($storeId)])
                    ->tag('doctrine.event_listener', ['event' => ToolEvents::postGenerateSchema]);
            }
        }

        if (self::STORE_IN_MEMORY === $store) {
            $services->set($storeId, InMemoryExecutionStore::class);
        }

        $services->alias(ExecutionStore::class, $storeId);

        $services->set(OneshotCommandPass::REGISTRY_ID, OneshotCommandRegistry::class)->args([[]]);
        $services->alias(OneshotCommandRegistry::class, OneshotCommandPass::REGISTRY_ID);

        $services->set('wamiz_oneshot_commands.lister', OneshotCommandLister::class)
            ->args([service(OneshotCommandPass::REGISTRY_ID), service($storeId)]);

        $services->set('wamiz_oneshot_commands.runner', ProcessRunner::class)
            ->args([$consolePath, '%kernel.environment%', $processTimeout]);

        $services->set('wamiz_oneshot_commands.listener.record_execution', RecordExecutionListener::class)
            ->args([service(OneshotCommandPass::REGISTRY_ID), service($storeId), $clock])
            ->tag('kernel.event_subscriber');

        $services->set('wamiz_oneshot_commands.command.list', ListCommand::class)
            ->args([service('wamiz_oneshot_commands.lister')])
            ->tag('console.command', ['command' => 'wamiz:oneshot-commands:list']);

        $services->set('wamiz_oneshot_commands.command.run', RunCommand::class)
            ->args([service('wamiz_oneshot_commands.lister'), service('wamiz_oneshot_commands.runner'), service($storeId), $clock])
            ->tag('console.command', ['command' => 'wamiz:oneshot-commands:run']);

        $services->set('wamiz_oneshot_commands.command.reset', ResetCommand::class)
            ->args([service(OneshotCommandPass::REGISTRY_ID), service($storeId)])
            ->tag('console.command', ['command' => 'wamiz:oneshot-commands:reset']);
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new OneshotCommandPass());
    }
}
