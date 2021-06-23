<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider\Capabilities;

trait ExtensionsTrait
{
    /**
     * @return array<string, callable(\Psr\Container\ContainerInterface):mixed>
     */
    public function extensions(): array
    {
        return [];
    }
}
