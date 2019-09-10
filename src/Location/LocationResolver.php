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
     * @var EnvConfig
     */
    private $config;

    /**
     * @param EnvConfig $config
     * @param array $extendedDefaults
     */
    public function __construct(EnvConfig $config, array $extendedDefaults = [])
    {
        $this->config = $config;

        $dirParts = explode('/vendor/inpsyde/', (string)wp_normalize_path(__FILE__), 2);

        // when package is installed as root (e.g. unit tests) vendor folder is inside the package
        $vendorPath = $dirParts && isset($dirParts[1])
            ? $dirParts[0] . '/vendor/'
            : (string)wp_normalize_path(dirname(__DIR__, 2)) . '/vendor/';

        $contentPath = (string)trailingslashit(wp_normalize_path(WP_CONTENT_DIR));
        $contentUrl = (string)content_url('/');

        /** @var string $vendorPath */
        if (strpos($vendorPath, $contentPath) === 0) {
            // If vendor path is inside content path, then we can calculate vendor URL
            $vendorUrl = $contentUrl . (substr($vendorPath, strlen($contentPath)) ?: '');
        }

        $defaults = [
            self::DIR => [
                Locations::ROOT => trailingslashit(ABSPATH),
                Locations::VENDOR => $vendorPath ?: null,
                Locations::CONTENT => $contentPath,
            ],
            self::URL => [
                Locations::ROOT => network_site_url('/'),
                Locations::VENDOR => $vendorUrl ?? null,
                Locations::CONTENT => $contentUrl,
            ],
        ];

        $custom = $extendedDefaults ? $this->parseExtendedDefaults($extendedDefaults) : [];
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
        $base = $this->config->get('WP_APP_' . strtoupper("{$location}_{$dirOrUrl}"))
            ?? $this->locations[$dirOrUrl][$location]
            ?? null;

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
