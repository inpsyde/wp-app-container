<?php

declare(strict_types=1);

namespace Inpsyde\App\Location;

use Inpsyde\App\EnvConfig;

/**
 * @psalm-type Location-Type = array<string, string|null>
 * @psalm-type Location-Types = array{"url": Location-Type, "dir": Location-Type}
 */
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

    /** @var Location-Types */
    private array $locations;
    private EnvConfig $config;

    /**
     * @param EnvConfig $config
     * @param array $extendedDefaults
     *
     * phpcs:disable Generic.Metrics.CyclomaticComplexity
     */
    public function __construct(EnvConfig $config, array $extendedDefaults = [])
    {
        // phpcs:enable Generic.Metrics.CyclomaticComplexity

        $this->config = $config;
        $vendorPath = $this->discoverVendorPath() ?? '';
        $contentPath = trailingslashit(wp_normalize_path((string) WP_CONTENT_DIR));
        $contentUrl = content_url('/');

        if (($vendorPath !== '') && (strpos($vendorPath, $contentPath) === 0)) {
            // If vendor path is inside content path, then we can calculate vendor URL
            $vendorUrl = $contentUrl . (string) substr($vendorPath, strlen($contentPath));
        }

        $locations = [
            self::DIR => [
                Locations::ROOT => trailingslashit((string) ABSPATH),
                Locations::VENDOR => ($vendorPath === '') ? null : $vendorPath,
                Locations::CONTENT => $contentPath,
            ],
            self::URL => [
                Locations::ROOT => network_site_url('/'),
                Locations::VENDOR => $vendorUrl ?? null,
                Locations::CONTENT => $contentUrl,
            ],
        ];

        $custom = $this->parseExtendedDefaults($extendedDefaults);
        if ($custom[self::DIR] !== []) {
            $locations[self::DIR] = array_merge($locations[self::DIR], $custom[self::DIR]);
        }
        if ($custom[self::URL] !== []) {
            $locations[self::URL] = array_merge($locations[self::URL], $custom[self::URL]);
        }

        $byConfig = $this->locationsByConfig($config);
        if ($byConfig[self::DIR] !== []) {
            $locations[self::DIR] = array_merge($locations[self::DIR], $byConfig[self::DIR]);
        }
        if ($byConfig[self::URL] !== []) {
            $locations[self::URL] = array_merge($locations[self::URL], $byConfig[self::URL]);
        }
        /** @var Location-Types $locations */
        $this->locations = $locations;
    }

    /**
     * @param string $location
     * @param string|null $subDir
     * @return string|null
     */
    public function resolveUrl(string $location, ?string $subDir = null): ?string
    {
        return $this->resolve($location, self::URL, $subDir);
    }

    /**
     * @param string $location
     * @param string|null $subDir
     * @return string|null
     */
    public function resolveDir(string $location, ?string $subDir = null): ?string
    {
        return $this->resolve($location, self::DIR, $subDir);
    }

    /**
     * @return string|null
     */
    private function discoverVendorPath(): ?string
    {
        $baseDir = (string) wp_normalize_path(dirname(__DIR__, 2));
        $dependency = 'psr/container/composer.json';

        $dirParts = explode('/', $baseDir);
        $countParts = count($dirParts);
        $vendorName = ($countParts > 3) ? array_slice($dirParts, -3, 1)[0] : '';
        $vendorPath = trim((string) $vendorName, '/') !== ''
            ? implode('/', array_slice($dirParts, 0, $countParts - 3)) . "/{$vendorName}"
            : null;

        // if vendor dir is found, but our dependency in it, then what's found is wrong or Composer
        // dependencies not installed. In both cases we want to disable vendor path.
        if (
            ($vendorPath !== null)
            && !is_file("{$vendorPath}/{$dependency}")
        ) {
            $vendorPath = null;
        }

        // if no vendor dir found, but our dependency inside base dir, package is installed as root,
        // e.g. during unit tests, so we can calculate vendor
        if (($vendorPath === null) && is_file("{$baseDir}/vendor/{$dependency}")) {
            $vendorPath = "{$baseDir}/vendor/";
        }

        return $vendorPath;
    }

    /**
     * @param string $location
     * @param string $dirOrUrl
     * @param string|null $subDir
     * @return string|null
     */
    private function resolve(string $location, string $dirOrUrl, ?string $subDir = null): ?string
    {
        $envBase = (string) $this->config->get('WP_APP_' . strtoupper("{$location}_{$dirOrUrl}"));

        $base = $envBase
            ? ($dirOrUrl === self::DIR ? wp_normalize_path($envBase) : $envBase)
            : ($this->locations[$dirOrUrl][$location] ?? null);

        if (($base === null) && array_key_exists($location, self::CONTENT_LOCATIONS)) {
            $contentBase = $this->resolve(Locations::CONTENT, $dirOrUrl);
            if (($contentBase !== '') && ($contentBase !== null)) {
                $base = $contentBase . self::CONTENT_LOCATIONS[$location];
            }
        }

        if ($base === null) {
            return null;
        }

        $base = trailingslashit((string) $base);

        if (($subDir === '') || ($subDir === null)) {
            return $base;
        }

        ($dirOrUrl === self::DIR) and $subDir = wp_normalize_path($subDir);

        return $base . ltrim((string) $subDir, '\\/');
    }

    /**
     * @param EnvConfig $config
     * @return Location-Types
     */
    private function locationsByConfig(EnvConfig $config): array
    {
        $locations = $config->get('LOCATIONS');

        return $this->parseExtendedDefaults(is_array($locations) ? $locations : []);
    }

    /**
     * @param array $locations
     * @return Location-Types
     */
    private function parseExtendedDefaults(array $locations): array
    {
        /** @var Location-Types $custom */
        $custom = [self::DIR => [], self::URL => []];
        if ($locations === []) {
            return $custom;
        }

        $customDirs = $locations[self::DIR] ?? [];
        is_array($customDirs) or $customDirs = [];

        $customUrls = $locations[self::URL] ?? [];
        is_array($customUrls) or $customUrls = [];

        foreach ($customDirs as $key => $customDir) {
            if (($key !== '') && ($customDir !== '') && is_string($key) && is_string($customDir)) {
                $custom[self::DIR][$key] = trailingslashit(wp_normalize_path($customDir));
            }
        }

        foreach ($customUrls as $key => $customUrl) {
            if (($key !== '') && ($customUrl !== '') && is_string($key) && is_string($customUrl)) {
                $custom[self::URL][$key] = trailingslashit($customUrl);
            }
        }

        /** @var Location-Types */
        return $custom;
    }
}
