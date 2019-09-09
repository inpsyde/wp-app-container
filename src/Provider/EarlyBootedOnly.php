<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Provider;

use Inpsyde\App\Container;

abstract class EarlyBootedOnly implements ServiceProvider
{
    use AutoDiscoverIdTrait;

    /**
     * @return bool
     */
    final public function registerLater(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    final public function bootEarly(): bool
    {
        return true;
    }

    /**
     * @param Container $container
     * @return bool
     */
    final public function register(Container $container): bool
    {
        return false;
    }
}
