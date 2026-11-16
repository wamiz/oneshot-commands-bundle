<?php

declare(strict_types=1);

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Wamiz\OneshotCommands\Attribute\OneshotCommand;
use Wamiz\OneshotCommands\DependencyInjection\Compiler\OneshotCommandPass;
use Wamiz\OneshotCommands\Model\OneshotCommandDefinition;
use Wamiz\OneshotCommands\Registry\OneshotCommandRegistry;

#[OneshotCommand(args: ['--dry-run'], immediate: false)]
final class UnnamedOneshotCommand extends Command
{
}

#[AsCommand('|')]
#[OneshotCommand]
final class EmptyNamedOneshotCommand extends Command
{
}

function containerWithRegistry(): ContainerBuilder
{
    $container = new ContainerBuilder();
    $container->register(OneshotCommandPass::REGISTRY_ID, OneshotCommandRegistry::class)->setArgument(0, []);

    return $container;
}

it('does nothing without the registry', function (): void {
    $container = new ContainerBuilder();
    new OneshotCommandPass()->process($container);

    expect($container->hasDefinition(OneshotCommandPass::REGISTRY_ID))->toBeFalse();
});

it('takes the name of the command from its tag', function (): void {
    $container = containerWithRegistry();
    $container->register('unnamed', UnnamedOneshotCommand::class)->addTag('console.command', ['command' => 'app:tagged|app:alias']);
    $container->register('unknown_class', 'App\\DoesNotExist')->addTag('console.command');
    $container->register(Command::class)->addTag('console.command', ['command' => 'app:regular']);

    new OneshotCommandPass()->process($container);

    $definitions = $container->getDefinition(OneshotCommandPass::REGISTRY_ID)->getArgument(0);
    expect($definitions)->toBeArray()->toHaveCount(1);
    assert(is_array($definitions) && $definitions[0] instanceof Definition);
    expect($definitions[0]->getClass())->toBe(OneshotCommandDefinition::class)
        ->and($definitions[0]->getArguments())->toBe(['app:tagged', ['--dry-run'], false]);
});

it('fails on a one-shot command without a name', function (string $class): void {
    $container = containerWithRegistry();
    $container->register('command', $class)->addTag('console.command');

    new OneshotCommandPass()->process($container);
})->with([UnnamedOneshotCommand::class, EmptyNamedOneshotCommand::class])->throws(LogicException::class, 'Cannot find the name of the one-shot command');
