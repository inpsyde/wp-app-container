<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\ModularityPlugin;

use Inpsyde\App\Tests\Project\WacLib\Logger;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ServiceModule;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use function Inpsyde\App\Tests\Project\app;

class CollectorModule implements ServiceModule, ExecutableModule
{
    public const ACTION_COLLECT = 'collect-line';

    /**
     * @return string
     */
    final public function id(): string
    {
        return 'CollectorModule';
    }

    /**
     * @return array<string, callable(ContainerInterface $container):mixed>
     */
    public function services(): array
    {
        return [
            Collector::class => static function (ContainerInterface $container): Collector {
                return Collector::new($container->get(LoggerInterface::class));
            },
        ];
    }

    /**
     * @param ContainerInterface $container
     * @return bool
     */
    public function run(ContainerInterface $container): bool
    {
        add_action(
            self::ACTION_COLLECT,
            static function ($line = null) use ($container) {
                is_string($line) and $container->get(Collector::class)->collect(rtrim($line));
            }
        );

        return true;
    }
}
