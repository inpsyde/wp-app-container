<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Location;

class VipLocations extends BaseLocations
{

    public const MU_PLUGINS = 'client-mu-plugins';
    public const VIP_CONFIG = 'vip-config';
    public const IMAGES = 'images';
    public const PRIVATE = 'private';

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
     * @return string
     */
    public function configDir(): string
    {
        return $this->contentDir(self::VIP_CONFIG);
    }

    /**
     * @param string $subDir
     *
     * @return string
     */
    public function privateDir(string $subDir = ''): string
    {
        return $this->contentDir(self::PRIVATE, $subDir);
    }

    /**
     * @return string
     */
    public function imagesDir(): string
    {
        return $this->contentDir(self::IMAGES);
    }

    /**
     * @return string
     */
    public function imagesUrl(): string
    {
        return $this->contentUrl(self::IMAGES);
    }
}
