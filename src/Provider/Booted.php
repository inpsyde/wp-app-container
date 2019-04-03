<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Provider;

abstract class Booted implements ServiceProvider
{
    use AutoDiscoverIdTrait;

    final public function registerLater(): bool
    {
        return false;
    }

    final public function bootEarly(): bool
    {
        return false;
    }
}
