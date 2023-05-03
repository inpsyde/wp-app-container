<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\WacLib;

use Inpsyde\App\App;
use Inpsyde\App\Provider\EarlyBooted;
use Inpsyde\App\Tests\Project\ModularityPlugin\Collector;
use Inpsyde\App\Tests\Project\ModularityPlugin3\Calc;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class Provider extends EarlyBooted
{
    public const ID = 'WAC Library';

    /**
     * @return array<string, callable(ContainerInterface $container):mixed>
     */
    public function services(): array
    {
        return [
            LoggerInterface::class => [Logger::class, 'new'],
        ];
    }

    /**
     * @return array<string, callable(ContainerInterface $container):mixed>
     */
    public function factories(): array
    {
        return [
            Logger::class => static function (ContainerInterface $container): Logger {
                return $container->get(LoggerInterface::class);
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
            App::ACTION_ADDED_MODULE,
            function (string $id, App $app) use ($container): void {
                if (!$app->hasModules($id)) {
                    throw new \Error("Weird: module ID '{$id}' not found");
                }
                $this->log($container->get(LoggerInterface::class), "MODULE {$id}");
            },
            10,
            2
        );

        add_action(
            App::ACTION_BOOTED_MODULE,
            function (string $id, App $app) use ($container): void {
                if (!$app->hasModules($id)) {
                    throw new \Error("Weird: module ID '{$id}' not found");
                }
                $this->log($container->get(LoggerInterface::class), "MODULE {$id}");
            },
            10,
            2
        );

        add_action(
            App::ACTION_READY_PACKAGE,
            function (\Inpsyde\Modularity\Package $package) use ($container): void {
                $this->log($container->get(LoggerInterface::class), 'PACKAGE ' . $package->name());
            }
        );

        add_action(
            'template_redirect',
            static function () use ($container): void {
                $lines = (string)$container->get(Collector::class, '');
                $container->get(LoggerInterface::class)->debug("Collected lines:\n{$lines}");
            }
        );

        add_action(
            'init',
            static function () use ($container): void {
                $calc = $container->get(Calc::class);
                $result = $calc->calculate('21', '*', '2');
                $container->get(LoggerInterface::class)->debug("Result is: {$result}");
            }
        );

        return true;
    }

    /**
     * @param Logger $logger
     * @param string $id
     * @return void
     */
    private function log(Logger $logger, string $id)
    {
        $hook = current_action();
        $id
            ? $logger->debug("HOOK {$hook} fired for {$id}")
            : $logger->debug("--- APPLICATION HOOK {$hook} ---");
    }
}
