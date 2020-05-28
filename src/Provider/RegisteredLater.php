<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider;

abstract class RegisteredLater implements ServiceProvider
{
    use AutoDiscoverIdTrait;

    final public function registerLater(): bool
    {
        return true;
    }

    final public function bootEarly(): bool
    {
        return false;
    }
}
