<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Provider;

use Inpsyde\App\Container;

abstract class BootedOnly implements ServiceProvider
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
        return false;
    }

    /**
     * @param Container $container
     * @return bool
     *
     * @suppress PhanUnusedPublicFinalMethodParameter
     */
    final public function register(Container $container): bool
    {
        return false;
    }
}
