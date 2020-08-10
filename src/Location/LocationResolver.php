<?php

declare(strict_types=1);

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

        $vendorPath = $this->discoverVendorPath();
        $contentPath = (string)trailingslashit(wp_normalize_path(WP_CONTENT_DIR));
        $contentUrl = (string)content_url('/');

        /** @var string|null $vendorPath */
        if ($vendorPath && strpos((string)$vendorPath, $contentPath) === 0) {
            // If vendor path is inside content path, then we can calculate vendor URL
            $subFolder = substr($vendorPath, strlen($contentPath));
            $vendorUrl = $contentUrl . (string)$subFolder;
        }

        $locations = [
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
        if ($custom[self::DIR] ?? null) {
            $locations[self::DIR] = array_merge($locations[self::DIR], $custom[self::DIR]);
        }
        if ($custom[self::URL] ?? null) {
            $locations[self::URL] = array_merge($locations[self::URL], $custom[self::URL]);
        }

        $byConfig = $this->locationsByConfig($config);
        if ($byConfig[self::DIR] ?? null) {
            $locations[self::DIR] = array_merge($locations[self::DIR], $byConfig[self::DIR]);
        }
        if ($byConfig[self::URL] ?? null) {
            $locations[self::URL] = array_merge($locations[self::URL], $byConfig[self::URL]);
        }

        $this->locations = $locations;
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
     * @return string|null
     */
    private function discoverVendorPath(): ?string
    {
        $baseDir = (string)wp_normalize_path(dirname(__DIR__, 2));
        $dependency = 'psr/container/composer.json';

        $dirParts = explode('/', $baseDir);
        $countParts = count($dirParts);
        $vendorName = $countParts > 3 ? array_slice($dirParts, -3, 1)[0] : '';
        $vendorPath = trim($vendorName, '/')
            ? implode('/', array_slice($dirParts, 0, $countParts - 3)) . "/{$vendorName}"
            : null;

        // if vendor dir is found, but our dependency in it, then what's found is wrong or Composer
        // dependencies not installed. In both cases we want to disable vendor path.
        if ($vendorPath && !is_file("{$vendorPath}/{$dependency}")) {
            $vendorPath = null;
        }

        // if no vendor dir found, but our dependency inside base dir, package is installed as root,
        // e.g. during unit tests, so we can calculate vendor
        if (!$vendorPath && is_file("{$baseDir}/vendor/{$dependency}")) {
            $vendorPath = "{$baseDir}/vendor/";
        }

        return $vendorPath ?: null;
    }

    /**
     * @param string $location
     * @param string $dirOrUrl
     * @param string|null $subDir
     * @return string|null
     */
    private function resolve(string $location, string $dirOrUrl, ?string $subDir = null): ?string
    {
        $envBase = (string)$this->config->get('WP_APP_' . strtoupper("{$location}_{$dirOrUrl}"));

        $base = $envBase
            ? ($dirOrUrl === self::DIR ? wp_normalize_path($envBase) : $envBase)
            : ($this->locations[$dirOrUrl][$location] ?? null);

        if ($base === null && array_key_exists($location, self::CONTENT_LOCATIONS)) {
            $contentBase = $this->resolve(Locations::CONTENT, $dirOrUrl);
            $contentBase and $base = $contentBase . self::CONTENT_LOCATIONS[$location];
        }

        if ($base === null) {
            return null;
        }

        $base = (string)trailingslashit($base);

        if (!$subDir) {
            return $base;
        }

        ($dirOrUrl === self::DIR) and $subDir = wp_normalize_path($subDir);

        return $base . ltrim((string)$subDir, '\\/');
    }

    /**
     * @param EnvConfig $config
     * @return array<string, array<string, string>>
     */
    private function locationsByConfig(EnvConfig $config): array
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
            if ($key && $customDir && is_string($key) && is_string($customDir)) {
                $custom[self::DIR][$key] = trailingslashit(wp_normalize_path($customDir));
            }
        }

        foreach ($customUrls as $key => $customUrl) {
            if ($key && $customUrl && is_string($key) && is_string($customUrl)) {
                $custom[self::URL][$key] = trailingslashit($customUrl);
            }
        }

        /** @var  array<string, array<string, string>> $custom */
        $custom = array_filter($custom);

        return $custom;
    }
}
