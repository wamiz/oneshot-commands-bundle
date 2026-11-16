<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\DependencyInjection\Compiler;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Wamiz\OneshotCommands\Attribute\OneshotCommand;
use Wamiz\OneshotCommands\Model\OneshotCommandDefinition;

/**
 * Collects the console commands marked with #[OneshotCommand] into the registry.
 */
final readonly class OneshotCommandPass implements CompilerPassInterface
{
    public const string REGISTRY_ID = 'wamiz_oneshot_commands.registry';
    public const string CLOCK_ID = 'wamiz_oneshot_commands.clock';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::REGISTRY_ID)) {
            return;
        }

        if ($container->has('clock')) {
            $container->setAlias(self::CLOCK_ID, 'clock');
        }

        $definitions = [];
        foreach ($container->findTaggedServiceIds('console.command') as $id => $tags) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass() ?? $id);
            if (!\is_string($class) || null === $reflection = $container->getReflectionClass($class, false)) {
                continue;
            }

            $attribute = ($reflection->getAttributes(OneshotCommand::class)[0] ?? null)?->newInstance();
            if (null === $attribute) {
                continue;
            }

            $name = $this->resolveName($tags, $reflection);
            if (null === $name) {
                throw new LogicException(\sprintf('Cannot find the name of the one-shot command "%s": declare it with #[AsCommand].', $class));
            }

            $definitions[$name] = new Definition(OneshotCommandDefinition::class, [$name, $attribute->args, $attribute->immediate]);
        }

        ksort($definitions);
        $container->getDefinition(self::REGISTRY_ID)->setArgument(0, array_values($definitions));
    }

    /**
     * Mirrors the console component: `name|alias1|alias2`, a leading `|` meaning hidden.
     *
     * @param array<mixed>             $tags
     * @param \ReflectionClass<object> $reflection
     */
    private function resolveName(array $tags, \ReflectionClass $reflection): ?string
    {
        $tag = $tags[0] ?? null;
        $name = \is_array($tag) && isset($tag['command'])
            ? $tag['command']
            : ($reflection->getAttributes(AsCommand::class)[0] ?? null)?->newInstance()->name;
        if (!\is_string($name)) {
            return null;
        }

        $name = explode('|', ltrim($name, '|'))[0];

        return '' === $name ? null : $name;
    }
}
