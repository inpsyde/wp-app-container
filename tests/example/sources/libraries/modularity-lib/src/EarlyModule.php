<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\ModularityLib;

use Inpsyde\App\Tests\Project\ModularityPlugin\CollectorModule;
use Psr\Container\ContainerInterface;

final class EarlyModule extends BaseModule
{
    /**
     * @param ContainerInterface $container
     * @return bool
     */
    public function run(ContainerInterface $container): bool
    {
        add_action(
            'init',
            static function () use ($container): void {
                do_action(CollectorModule::ACTION_COLLECT, "Lorem Ipsum");
                do_action(CollectorModule::ACTION_COLLECT, "Dolor Sit Amet");
            },
            PHP_INT_MAX
        );

        return parent::run($container);
    }
}
