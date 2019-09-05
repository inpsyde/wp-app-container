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
    public function vendorPackageDir(string $vendor, string $package): string
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
     * For custom configuration changes, and additional sunrise.php code.
     *
     * @return string
     */
    public function configDir(): string
    {
        return $this->contentDir(self::VIP_CONFIG);
    }

    /**
     * The "private"-folder in your repo, if used, will provide access to files that are not web accessible, but can be
     * accessed by your theme or plugins.
     *
     * @param string $subDir
     *
     * @return string
     */
    public function privateDir(string $subDir = ''): string
    {
        return $this->contentDir(self::PRIVATE, $subDir);
    }

    /**
     * For favicon.ico and apple-touch-icon*.png images.
     *
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
