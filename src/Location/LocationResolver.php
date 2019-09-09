<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Location;

use Inpsyde\App\EnvConfig;

class LocationResolver
{
    public const URL = 'url';
    public const DIR = 'dir';

    private const CONTENT_LOCATIONS = [
        Locations::MU_PLUGINS => 'mu-plugins/',
        Locations::LANGUAGES => 'languages/',
        Locations::PLUGINS => 'plugins/',
        Locations::THEMES => 'themes/',
    ];

    /**
     * @var array
     */
    private $locations;

    /**
     * @param EnvConfig $config
     * @param array $extendedDefaults
     */
    public function __construct(EnvConfig $config, array $extendedDefaults = [])
    {
        $defaults = [
            self::DIR => [
                Locations::ROOT => trailingslashit(ABSPATH),
                Locations::VENDOR => dirname((string)wp_normalize_path(__DIR__), 4),
                Locations::CONTENT => trailingslashit(WP_CONTENT_DIR),
            ],
            self::URL => [
                Locations::ROOT => network_site_url('/'),
                Locations::VENDOR => null,
                Locations::CONTENT => content_url('/'),
            ],
        ];

        $custom = $this->parseExtendedDefaults($extendedDefaults);
        $byEnv = $this->locationsByEnv($config);

        $merge = [];
        $custom and $merge[] = $custom;
        $byEnv and $merge[] = $byEnv;

        $this->locations = $merge ? array_replace($defaults, ...$merge) : $defaults;
    }

    /**
     * @param string $dirOrUrl
     * @param string $subDir
     * @return string|null
     */
    public function resolveUrl(string $location, ?string $subDir = null): ?string
    {
        return $this->resolve($location, self::URL, $subDir);
    }

    /**
     * @param string $dirOrUrl
     * @param string $subDir
     * @return string|null
     */
    public function resolveDir(string $location, ?string $subDir = null): ?string
    {
        return $this->resolve($location, self::DIR, $subDir);
    }

    /**
     * @param string $dirOrUrl
     * @param string $name
     * @param string|null $subDir
     * @return string|null
     */
    private function resolve(string $location, string $dirOrUrl, ?string $subDir = null): ?string
    {
        $base = $this->locations[$dirOrUrl][$location] ?? null;

        if ($base === null && array_key_exists($location, self::CONTENT_LOCATIONS)) {
            $base = $this->locations[$dirOrUrl][Locations::CONTENT] ?? null;
            if (is_string($base)) {
                $base .= self::CONTENT_LOCATIONS[$location];
            }
        }

        if ($base === null) {
            return null;
        }

        if (!$subDir) {
            return (string)$base;
        }

        ($dirOrUrl === self::DIR) and $subDir = wp_normalize_path($subDir);

        return (string)$base . ltrim((string)$subDir, '\\/');
    }

    /**
     * @param EnvConfig $config
     * @return array<string, array<string, string>>
     */
    private function locationsByEnv(EnvConfig $config): array
    {
        $locations = $config->get('LOCATIONS');
        if (!$locations || !is_array($locations)) {
            return [];
        }

        return $this->parseExtendedDefaults($locations);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function parseExtendedDefaults(array $locations): array
    {
        $custom = [self::DIR => [], self::URL => []];

        $customDirs = $locations[self::DIR] ?? [];
        is_array($customDirs) or $customDirs = [];

        $customUrls = $locations[self::URL] ?? [];
        is_array($customUrls) or $customUrls = [];

        foreach ($customDirs as $key => $customDir) {
            if (!$key || !$customDir || !is_string($key) || !is_string($customDir)) {
                continue;
            }

            $custom[self::DIR][$key] = trailingslashit(wp_normalize_path($customDir));
        }

        foreach ($customUrls as $key => $customUrl) {
            if (!$key || !$customUrl || !is_string($key) || !is_string($customUrl)) {
                continue;
            }

            $custom[self::URL][$key] = trailingslashit($customUrl);
        }

        return array_filter($custom);
    }
}
