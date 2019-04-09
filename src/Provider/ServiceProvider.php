<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Provider;

use Inpsyde\App\Container;

interface ServiceProvider
{
    /**
     * @return string
     */
    public function id(): string;

    /**
     * @return bool
     */
    public function registerLater(): bool;

    /**
     * @param Container $container
     * @return bool
     */
    public function register(Container $container): bool;

    /**
     * @return bool
     */
    public function bootEarly(): bool;

    /**
     * @param Container $container
     * @return bool
     */
    public function boot(Container $container): bool;
}
