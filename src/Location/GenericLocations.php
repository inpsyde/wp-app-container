<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Location;

use Inpsyde\App\EnvConfig;

class GenericLocations implements Locations
{
    use ResolverTrait;

    /**
     * @param string $path
     * @return string
     */
    public static function createFromConfig(EnvConfig $config): Locations
    {
        return new static($config);
    }

    /**
     * @param EnvConfig $config
     */
    private function __construct(EnvConfig $config)
    {
        $this->injectResolver(new LocationResolver($config));
    }
}
