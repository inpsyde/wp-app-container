<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider;

abstract class EarlyBooted implements ServiceProvider
{
    use AutoDiscoverIdTrait;

    final public function registerLater(): bool
    {
        return false;
    }

    final public function bootEarly(): bool
    {
        return true;
    }
}
