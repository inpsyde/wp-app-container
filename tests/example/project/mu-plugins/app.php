<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project;

use Inpsyde\App\Config\EnvConfig;
use Inpsyde\WpContext;
use Inpsyde\App\App;

function app(): App
{
    static $app;
    $app or $app = App::new(
        new EnvConfig(__NAMESPACE__),
        null,
        WpContext::new()->force(WpContext::FRONTOFFICE)
    );

    return $app;
}

app()
    ->addModule(new ModularityLib\LateModule())
    ->addEarlyModule(new ModularityLib\EarlyModule())
    ->addProvidersPackage(new WacLib\Package())
    ->boot();

add_action(
    'plugins_loaded',
    static function (): void {
        /*
         * By the time "plugins_loaded" with priority 5 runs,
         * 'plugin' and 'plugin 2' are _not_ booted, but 'plugin 3' is _already_ booted.
         * We can share Modularity packages before and after they are booted.
         * In both cases, we will be able to access their services from WAC's container as soon
         * as they'll boot.
         * However, if shared _after_ boot, we can't access WAC's container services from
         * Modularity packages' container. That's possible when shared _before_ boot.
         */
        app()
            ->sharePackage(ModularityPlugin\plugin())
            ->sharePackage(ModularityPlugin2\plugin())
            ->sharePackage(ModularityPlugin3\plugin());
    },
    5
);
