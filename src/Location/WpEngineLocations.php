<?php

declare(strict_types=1);

namespace Inpsyde\App\Location;

use Inpsyde\App\EnvConfig;

class WpEngineLocations implements Locations
{
    use ResolverTrait;

    public const PRIVATE = 'private';

    /**
     * @param string $path
     * @return WpEngineLocations
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
        $muDir = wp_normalize_path((string)trailingslashit(WP_CONTENT_DIR) . 'mu-plugins');

        $this->injectResolver(
            new LocationResolver(
                $config,
                [
                    LocationResolver::DIR => [
                        self::PRIVATE => (string)wp_normalize_path(ABSPATH) . '_wpeprivate/',
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
     * @return string|null
     */
    public function privateDir(string $path = '/'): ?string
    {
        return $this->resolveDir(self::PRIVATE, $path);
    }
}
