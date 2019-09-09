<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Provider;

use Inpsyde\App\Container;

abstract class RegisteredLaterOnly implements ServiceProvider
{
    use AutoDiscoverIdTrait;

    /**
     * @return bool
     */
    final public function registerLater(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    final public function bootEarly(): bool
    {
        return false;
    }

    /**
     * @param Container $container
     * @return bool
     */
    final public function boot(Container $container): bool
    {
        return false;
    }
}
