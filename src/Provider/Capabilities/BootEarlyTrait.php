<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider\Capabilities;

trait BootEarlyTrait
{
    final public function bootEarly(): bool
    {
        return true;
    }
}
