<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\ModularityLib;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ServiceModule;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class BaseModule implements ServiceModule, ExecutableModule
{
    public const HOOKS = [
        'muplugins_loaded',
        'plugins_loaded',
        'setup_theme',
        'after_setup_theme',
        'init',
        'template_redirect',
        'shutdown',
    ];

    /**
     * @return string
     */
    public function id(): string
    {
        $parts = explode('\\', static::class);

        return str_replace('Module', ' Module', array_pop($parts));
    }

    /**
     * @return array<string, callable(ContainerInterface $container):mixed>
     */
    public function services(): array
    {
        return [
            $this->id() . 'Logger' => function (ContainerInterface $container): HookLogger {
                return HookLogger::new($this->id(), $container->get(LoggerInterface::class));
            },
        ];
    }

    /**
     * @param ContainerInterface $container
     * @return bool
     */
    public function run(ContainerInterface $container): bool
    {
        foreach (self::HOOKS as $hook) {
            add_action($hook, [$container->get($this->id() . 'Logger'), 'logHook'], 99999);
        }

        return true;
    }
}
