<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\ModularityPlugin3;

use Inpsyde\Modularity\Module\ServiceModule;
use Psr\Container\ContainerInterface;

class PluginModule implements ServiceModule
{
    /**
     * @return string
     */
    final public function id(): string
    {
        return 'plugin-three-plugin-module';
    }

    /**
     * @return array<string, callable(ContainerInterface $container):mixed>
     */
    public function services(): array
    {
        return [
            Calc::class => [Calc::class, 'new'],
        ];
    }
}
