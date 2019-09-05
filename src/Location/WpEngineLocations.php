<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Location;

class WpEngineLocations extends BaseLocations
{

    public const WPEPRIVATE = '_wpeprivate';

    /**
     * {@inheritDoc}
     */
    public function vendorPackageDir(string $vendor, string $package): ?string
    {
        return $this->muPluginsDir("/vendor/{$vendor}/{$package}");
    }

    /**
     * {@inheritDoc}
     */
    public function vendorPackageUrl(string $vendor, string $package): ?string
    {
        return $this->muPluginsUrl("/vendor/{$vendor}/{$package}");
    }

    /**
     * A place were you can store files temporary until you want to put them in their correct location.
     *
     * @return string
     */
    public function wpePrivateDir(): string
    {
        return trailingslashit($this->rootPath.self::WPEPRIVATE);
    }
}
