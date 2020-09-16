<?php

declare(strict_types=1);

namespace Inpsyde\App\Location;

use Inpsyde\App\EnvConfig;

class GenericLocations implements Locations
{
    use ResolverTrait;

    /**
     * @param EnvConfig $config
     * @return Locations
     */
    public static function createFromConfig(EnvConfig $config): Locations
    {
        return new self($config);
    }

    /**
     * @param EnvConfig $config
     */
    private function __construct(EnvConfig $config)
    {
        $this->injectResolver(new LocationResolver($config));
    }
}
