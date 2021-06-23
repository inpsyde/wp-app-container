<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider\Capabilities;

trait RegisterLateTrait
{
    use ExtensionsTrait;
    use FactoriesTrait;

    final public function registerLater(): bool
    {
        return true;
    }
}
