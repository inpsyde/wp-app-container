<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider\Capabilities;

trait NoRegisterTrait
{
    /**
     * @return bool
     */
    final public function registerLater(): bool
    {
        return false;
    }

    /**
     * @return array<string, callable(\Psr\Container\ContainerInterface):mixed>
     */
    final public function services(): array
    {
        return [];
    }

    /**
     * @return array<string, callable(\Psr\Container\ContainerInterface):mixed>
     */
    final public function factories(): array
    {
        return [];
    }

    /**
     * @return array<string, callable(mixed, \Psr\Container\ContainerInterface):mixed>
     */
    final public function extensions(): array
    {
        return [];
    }
}
