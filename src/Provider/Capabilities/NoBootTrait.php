<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider\Capabilities;

use Psr\Container\ContainerInterface;

trait NoBootTrait
{
    /**
     * @return bool
     */
    final public function bootEarly(): bool
    {
        return false;
    }

    /**
     * @param ContainerInterface $container
     * @return bool
     */
    final public function run(ContainerInterface $container): bool
    {
        return true;
    }
}
