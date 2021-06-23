<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider\Capabilities;

trait FactoriesTrait
{
    /**
     * @return array<string, callable(\Psr\Container\ContainerInterface):mixed>
     */
    public function factories(): array
    {
        return [];
    }
}
