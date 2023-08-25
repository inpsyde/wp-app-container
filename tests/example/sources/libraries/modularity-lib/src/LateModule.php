<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\ModularityLib;

use Inpsyde\App\Tests\Project\ModularityPlugin3\Calc;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class LateModule extends BaseModule
{
    /**
     * @param ContainerInterface $container
     * @return bool
     */
    public function run(ContainerInterface $container): bool
    {
        add_action(
            'shutdown',
            static function () use ($container): void {
                $calc = $container->get(Calc::class);
                $result = $calc->calculate('7', '*', '7');
                $container->get(LoggerInterface::class)->debug("Result is: {$result}");
            },
            PHP_INT_MAX
        );

        return parent::run($container);
    }
}
