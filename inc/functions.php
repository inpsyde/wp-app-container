<?php

declare(strict_types=1);

namespace Inpsyde\App;

use Inpsyde\WpContext;
use Psr\Container\ContainerInterface;

/**
 * @param Config\Config|null $config
 * @param ContainerInterface|null $container
 * @param WpContext|null $context
 * @return App
 */
function app(
    ?Config\Config $config = null,
    ?ContainerInterface $container = null,
    ?WpContext $context = null
): App {

    static $app;
    if ($app && ($config || $container || $context)) {
        $err = new \Exception(__FUNCTION__ . ' accepts params only the first time it is called.');
        App::handleThrowable($err);

        return $app;
    }

    $app or $app = App::new($config, $container, $context);

    return $app;
}
