<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Location;

use Inpsyde\App\EnvConfig;

class VipLocations implements Locations
{
    public const CLIENT_MU_PLUGINS = 'client-mu-plugins';
    public const VIP_CONFIG = 'vip-config';
    public const IMAGES = 'images';
    public const PRIVATE = 'private';

    use ResolverTrait;

    /**
     * @param string $path
     * @return VipLocations
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
        $baseResolver = new LocationResolver($config);
        $contentUrl = $baseResolver->resolveUrl(self::CONTENT);
        $contentDir = $baseResolver->resolveDir(self::CONTENT);

        $privateDir = defined('WPCOM_VIP_PRIVATE_DIR')
            ? trailingslashit(wp_normalize_path(WPCOM_VIP_PRIVATE_DIR))
            : null;

        $clientMuDir = defined('WPCOM_VIP_CLIENT_MU_PLUGIN_DIR')
            ? trailingslashit(wp_normalize_path(WPCOM_VIP_CLIENT_MU_PLUGIN_DIR))
            : "{$contentDir}client-mu-plugins/";

        $clientMuUrl = "{$contentUrl}client-mu-plugins/";

        $abspath = trailingslashit(wp_normalize_path(ABSPATH));

        $this->injectResolver(
            new LocationResolver(
                $config,
                [
                    LocationResolver::DIR => [
                        self::IMAGES => "{$contentDir}images/",
                        self::CLIENT_MU_PLUGINS => $clientMuDir,
                        self::VENDOR => "{$clientMuDir}vendor/",
                        self::PRIVATE => $privateDir,
                        self::VIP_CONFIG => "{$abspath}vip-config/",
                    ],
                    LocationResolver::URL => [
                        self::IMAGES => "{$contentUrl}images",
                        self::CLIENT_MU_PLUGINS => $clientMuUrl,
                        self::VENDOR => "{$clientMuUrl}vendor/",
                    ],
                ]
            )
        );
    }

    /**
     * Regular MU plugins folder is not usable for custom-developed MU plugins on VIP environments,
     * the "client-mu-plugins" foldr has to be used instead.
     *
     * @param string $path
     * @return string|null
     */
    public function clientMuPluginsDir(string $path = '/'): ?string
    {
        return $this->resolveDir(self::CLIENT_MU_PLUGINS, $path);
    }

    /**
     * Regular MU plugins folder is not usable for custom-developed MU plugins on VIP environments,
     * the "client-mu-plugins" foldr has to be used instead.
     *
     * @param string $path
     * @return string|null
     */
    public function clientMuPluginsUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(self::CLIENT_MU_PLUGINS, $path);
    }

    /**
     * Kind-of-legacy folder. Can still be used to store images relevant site-wide, like favicons
     * or Apple touch icons.
     *
     * @param string $path
     * @return string|null
     */
    public function imagesDir(string $path = '/'): ?string
    {
        return $this->resolveDir(self::IMAGES, $path);
    }

    /**
     * Kind-of-legacy folder. Can still be used to store images relevant site-wide, like favicons
     * or Apple touch icons.
     *
     * @param string $path
     * @return string|null
     */
    public function imagesUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(self::IMAGES, $path);
    }

    /**
     * The "private" folder in VIP Go repo can be used to read files from PHP, without making them
     * web-accessible.
     *
     * @param string $path
     * @return string|null
     */
    public function privateDir(string $path = '/'): ?string
    {
        return $this->resolveDir(self::PRIVATE, $path);
    }

    /**
     * Path where to put `vip-config.php` and optionally `client-sunrise.php`
     *
     * @param string $path
     * @return string|null
     */
    public function vipConfigDir(string $path = '/'): ?string
    {
        return $this->resolveDir(self::VIP_CONFIG, $path);
    }
}
