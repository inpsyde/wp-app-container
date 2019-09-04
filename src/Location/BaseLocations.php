<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Location;

class BaseLocations implements Locations
{

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var string
     */
    protected $vendorRootDir;

    public function __construct()
    {
        $this->basePath = untrailingslashit(WP_CONTENT_DIR);
        $this->baseUrl = content_url('/');
        $this->vendorRootDir = wp_normalize_path(dirname(__DIR__, 4));
    }

    /**
     * {@inheritDoc}
     */
    public function pluginsDir(string $plugin = ''): string
    {
        return $this->dir(self::PLUGINS_DIR, $plugin);
    }

    /**
     * {@inheritDoc}
     */
    public function pluginsUrl(string $plugin = ''): string
    {
        return $this->url(self::PLUGINS_DIR, $plugin);
    }

    /**
     * {@inheritDoc}
     */
    public function muPluginsDir(string $muPlugin = ''): string
    {
        return $this->dir(self::MU_PLUGINS_DIR, $muPlugin);
    }

    /**
     * {@inheritDoc}
     */
    public function muPluginsUrl(string $muPlugin = ''): string
    {
        return $this->url(self::MU_PLUGINS_DIR, $muPlugin);
    }

    /**
     * {@inheritDoc}
     */
    public function themesDir(string $theme = ''): string
    {
        return $this->dir(self::THEMES_DIR, $theme);
    }

    /**
     * {@inheritDoc}
     */
    public function themesUrl(string $theme = ''): string
    {
        return $this->url(self::THEMES_DIR, $theme);
    }

    /**
     * {@inheritDoc}
     */
    public function languagesDir(): string
    {
        return $this->dir(self::LANGUAGES_DIR);
    }

    /**
     * {@inheritDoc}
     */
    public function languagesUrl(): string
    {
        return $this->url(self::THEMES_DIR);
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
     * @param string $which
     * @param string $subDir
     *
     * @return string
     */
    protected function dir(string $which, string $subDir = ''): string
    {
        $path = realpath("{$this->basePath}/".$which);

        return trailingslashit(trailingslashit($path).ltrim($subDir, '\\/'));
    }

    /**
     * @param string $which
     * @param string $subDir
     *
     * @return string
     */
    protected function url(string $which, string $subDir = ''): string
    {
        return trailingslashit(trailingslashit($this->baseUrl.$which).ltrim($subDir, '/'));
    }
}
