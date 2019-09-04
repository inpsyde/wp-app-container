<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Location;

class VipLocations extends BaseLocations
{

    public const MU_PLUGINS_DIR = 'client-mu-plugins';
    public const CONFIG_DIR = 'vip-config';
    public const IMAGES_DIR = 'images';
    public const PRIVATE_DIR = 'private';
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
        return $this->dir(self::CONFIG_DIR);
    }

    /**
     * @param string $subDir
     *
     * @return string
     */
    public function privateDir(string $subDir = ''): string
    {
        return $this->dir(self::PRIVATE_DIR, $subDir);
    }

    /**
     * @return string
     */
    public function imagesDir(): string
    {
        return $this->dir(self::IMAGES_DIR);
    }

    /**
     * @return string
     */
    public function imagesUrl(): string
    {
        return $this->url(self::IMAGES_DIR);
    }
}
