<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\ModularityPlugin2;

use Inpsyde\App\Tests\Project\ModularityPlugin\Collector as BaseCollector;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ServiceModule;
use Psr\Container\ContainerInterface;

class PluginModule implements ServiceModule, ExecutableModule
{
    /**
     * @return string
     */
    final public function id(): string
    {
        return 'plugin-two-plugin-module';
    }

    /**
     * @return array<string, callable(ContainerInterface $container):mixed>
     */
    public function services(): array
    {
        return [
            Collector::class => static function (ContainerInterface $container): Collector {
                return Collector::new($container->get(BaseCollector::class));
            },
        ];
    }

    /**
     * @param ContainerInterface $container
     * @return bool
     */
    public function run(ContainerInterface $container): bool
    {
        $container->get(Collector::class)->collect('Plugin Two is Good For You');

        return true;
    }
}
