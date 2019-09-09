<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Location;

use Inpsyde\App\EnvConfig;

class WpEngineLocations implements Locations
{
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
        $muDir = wp_normalize_path(trailingslashit(WP_CONTENT_DIR) . 'mu-plugins');

        $this->injectResolver(
            new LocationResolver(
                $config,
                [
                    LocationResolver::DIR => [
                        self::PRIVATE => wp_normalize_path(ABSPATH) . '_wpeprivate/',
                        self::VENDOR => "{$muDir}/vendor/",
                    ],
                    LocationResolver::URL => [
                        self::VENDOR => content_url('/mu-plugins/vendor/'),
                    ],
                ]
            )
        );
    }

    /**
     * Temporary writable folder.
     *
     * @param string $path
     * @return string
     */
    public function privateDir(string $path = '/'): string
    {
        return $this->resolver()->resolveDir(self::PRIVATE, $path) ?? '';
    }
}
