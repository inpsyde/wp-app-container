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
        return $this->contentDir(self::PLUGINS_DIR, $plugin);
    }

    /**
     * {@inheritDoc}
     */
    public function pluginsUrl(string $plugin = ''): string
    {
        return $this->contentUrl(self::PLUGINS_DIR, $plugin);
    }

    /**
     * {@inheritDoc}
     */
    public function muPluginsDir(string $muPlugin = ''): string
    {
        return $this->contentDir(self::MU_PLUGINS_DIR, $muPlugin);
    }

    /**
     * {@inheritDoc}
     */
    public function muPluginsUrl(string $muPlugin = ''): string
    {
        return $this->contentUrl(self::MU_PLUGINS_DIR, $muPlugin);
    }

    /**
     * {@inheritDoc}
     */
    public function themesDir(string $theme = ''): string
    {
        return $this->contentDir(self::THEMES_DIR, $theme);
    }

    /**
     * {@inheritDoc}
     */
    public function themesUrl(string $theme = ''): string
    {
        return $this->contentUrl(self::THEMES_DIR, $theme);
    }

    /**
     * {@inheritDoc}
     */
    public function languagesDir(): string
    {
        return $this->contentDir(self::LANGUAGES_DIR);
    }

    /**
     * {@inheritDoc}
     */
    public function languagesUrl(): string
    {
        return $this->contentUrl(self::THEMES_DIR);
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
    protected function contentDir(string $which, string $subDir = ''): string
    {
        $path = realpath("{$this->contentPath}/".$which);

        return trailingslashit(trailingslashit($path).ltrim($subDir, '\\/'));
    }

    /**
     * @param string $which
     * @param string $subDir
     *
     * @return string
     */
    protected function contentUrl(string $which, string $subDir = ''): string
    {
        return trailingslashit(trailingslashit($this->contentUrl.$which).ltrim($subDir, '/'));
    }
}
