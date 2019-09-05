<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Location;

class BaseLocations implements Locations
{

    /**
     * @var string
     */
    protected $contentPath;

    /**
     * @var string
     */
    protected $contentUrl;

    /**
     * @var string
     */
    protected $vendorRootDir;

    /**
     * @var string
     */
    protected $rootPath;

    public function __construct()
    {
        $this->rootPath = trailingslashit(ABSPATH);
        $this->contentPath = untrailingslashit(WP_CONTENT_DIR);
        $this->contentUrl = content_url('/');
        $this->vendorRootDir = wp_normalize_path(dirname(__DIR__, 4));
    }

    /**
     * {@inheritDoc}
     */
    public function pluginsDir(string $plugin = ''): string
    {
        return $this->contentDir(self::PLUGINS, $plugin);
    }

    /**
     * {@inheritDoc}
     */
    public function pluginsUrl(string $plugin = ''): string
    {
        return $this->contentUrl(self::PLUGINS, $plugin);
    }

    /**
     * {@inheritDoc}
     */
    public function muPluginsDir(string $muPlugin = ''): string
    {
        return $this->contentDir(self::MU_PLUGINS, $muPlugin);
    }

    /**
     * {@inheritDoc}
     */
    public function muPluginsUrl(string $muPlugin = ''): string
    {
        return $this->contentUrl(self::MU_PLUGINS, $muPlugin);
    }

    /**
     * {@inheritDoc}
     */
    public function themesDir(string $theme = ''): string
    {
        return $this->contentDir(self::THEMES, $theme);
    }

    /**
     * {@inheritDoc}
     */
    public function themesUrl(string $theme = ''): string
    {
        return $this->contentUrl(self::THEMES, $theme);
    }

    /**
     * {@inheritDoc}
     */
    public function languagesDir(): string
    {
        return $this->contentDir(self::LANGUAGES);
    }

    /**
     * {@inheritDoc}
     */
    public function languagesUrl(): string
    {
        return $this->contentUrl(self::LANGUAGES);
    }

    /**
     * {@inheritDoc}
     */
    public function vendorPackageDir(string $vendor, string $package): string
    {
        return "{$this->vendorRootDir}/{$vendor}/{$package}";
    }

    /**
     * {@inheritDoc}
     */
    public function vendorPackageUrl(string $vendor, string $package): ?string
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function rootDir(): string
    {
        return $this->rootPath;
    }

    /**
     * {@inheritDoc}
     */
    public function contentDir(string $which = '', string $subDir = ''): string
    {
        if ($which === '') {
            return $this->contentPath;
        }

        $key = "{$which}:{$subDir}";
        $path = wp_cache_get($key, __METHOD__);

        if (! $path) {
            $path = realpath("{$this->contentPath}/".$which);
            $path = trailingslashit(trailingslashit($path).ltrim($subDir, '\\/'));
            wp_cache_set($key, $path, __METHOD__);
        }

        return $path;
    }

    /**
     * {@inheritDoc}
     */
    public function contentUrl(string $which = '', string $subDir = ''): string
    {
        if ($which === '') {
            return $this->contentUrl;
        }

        $key = "{$which}:{$subDir}";
        $path = wp_cache_get($key, __METHOD__);
        if (! $path) {
            $path = trailingslashit(trailingslashit($this->contentUrl.$which).ltrim($subDir, '/'));
            wp_cache_set($key, $path, __METHOD__);
        }

        return $path;
    }
}
