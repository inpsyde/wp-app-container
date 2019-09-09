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
        $privateDir = defined('WPCOM_VIP_PRIVATE_DIR')
            ? trailingslashit(wp_normalize_path(WPCOM_VIP_PRIVATE_DIR))
            : null;

        $muDir = defined('WPCOM_VIP_CLIENT_MU_PLUGIN_DIR')
            ? trailingslashit(wp_normalize_path(WPCOM_VIP_CLIENT_MU_PLUGIN_DIR))
            : null;

        /**
         * TODO: verify if we can use "private" folder assuming there's a way to "publish" assets
         *      that are inside vendor.
         *      Right now, "client-mu-plugins" is the only folder that allow us to access assets
         *      when stored in vendor.
         */

        $this->injectResolver(
            new LocationResolver(
                $config,
                [
                    LocationResolver::DIR => [
                        self::CLIENT_MU_PLUGINS => $muDir,
                        self::IMAGES => trailingslashit(WP_CONTENT_DIR) . '/images/',
                        self::PRIVATE => $privateDir,
                        self::VIP_CONFIG => null,
                        self::VENDOR => $muDir ? "{$muDir}/client-mu-plugins/vendor/" : null,
                    ],
                    LocationResolver::URL => [
                        self::CLIENT_MU_PLUGINS => content_url('/client-mu-plugins/'),
                        self::IMAGES => content_url('/images/'),
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
     * @return string
     */
    public function clientMuPluginsDir(string $path = '/'): string
    {
        return $this->resolver()->resolveDir(self::CLIENT_MU_PLUGINS, $path) ?? '';
    }

    /**
     * Regular MU plugins folder is not usable for custom-developed MU plugins on VIP environments,
     * the "client-mu-plugins" foldr has to be used instead.
     *
     * @param string $path
     * @return string
     */
    public function clientMuPluginsUrl(string $path = '/'): string
    {
        return $this->resolver()->resolveUrl(self::CLIENT_MU_PLUGINS, $path) ?? '';
    }

    /**
     * Kind-of-legacy folder. Can still be used to store images relevant site-wide, like favicons
     * or Apple touch icons.
     *
     * @param string $path
     * @return string
     */
    public function imagesDir(string $path = '/'): string
    {
        return $this->resolver()->resolveDir(self::IMAGES, $path) ?? '';
    }

    /**
     * Kind-of-legacy folder. Can still be used to store images relevant site-wide, like favicons
     * or Apple touch icons.
     *
     * @param string $path
     * @return string
     */
    public function imagesUrl(string $path = '/'): string
    {
        return $this->resolver()->resolveUrl(self::IMAGES, $path) ?? '';
    }

    /**
     * The "private" folder in VIP Go repo can be used to read files from PHP, without making them
     * web-accessible.
     *
     * @param string $path
     * @return string
     */
    public function privateDir(string $path = '/'): string
    {
        return $this->resolver()->resolveDir(self::PRIVATE, $path) ?? '';
    }
}
