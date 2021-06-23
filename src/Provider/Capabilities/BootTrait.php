<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider\Capabilities;

trait BootTrait
{
    final public function bootEarly(): bool
    {
        return false;
    }
}
