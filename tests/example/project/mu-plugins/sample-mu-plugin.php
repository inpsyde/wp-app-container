<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\SampleMuPlugin;

use Inpsyde\App\App;
use Inpsyde\App\Provider\BootedOnly;
use Psr\Container\ContainerInterface;

class Provider extends BootedOnly
{
    public function id(): string
    {
        return 'Sample MU-plugin Provider';
    }

    public function run(ContainerInterface $container): bool
    {
        return true;
    }
}

add_action(
    App::ACTION_ADD_PROVIDERS,
    static function (App $app): void {
        $app->addProvider(new Provider());
    }
);
