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

    /**
     * @var array
     */
    protected $locations;

    public function __construct(array $locations = [])
    {
        $this->contentPath = untrailingslashit(WP_CONTENT_DIR);
        $this->contentUrl = content_url('/');
        $this->locations = array_replace($this->defaultLocations(), $locations);
    }

    protected function defaultLocations(): array
    {
        return [
            self::TYPE_DIR => [
                self::ROOT => trailingslashit(ABSPATH),
                self::VENDOR => wp_normalize_path(dirname(__DIR__, 4)),
            ],
            self::TYPE_URL => [
                self::ROOT => site_url('/'),
                self::VENDOR => null,
            ],
        ];
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
        return $this->locations[self::TYPE_DIR][self::VENDOR]."/{$vendor}/{$package}/";
    }

    /**
     * {@inheritDoc}
     */
    public function vendorPackageUrl(string $vendor, string $package): ?string
    {
        return $this->locations[self::TYPE_DIR][self::VENDOR]."/{$vendor}/{$package}/" ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function rootDir(): string
    {
        return $this->locations[self::TYPE_DIR][self::ROOT];
    }

    /**
     * {@inheritDoc}
     */
    public function rootUrl(): string
    {
        return $this->locations[self::TYPE_URL][self::ROOT];
    }

    /**
     * {@inheritDoc}
     */
    public function contentDir(string $which = '', string $subDir = ''): string
    {
        return $this->resolve(self::TYPE_DIR, $which, $subDir);
    }

    /**
     * {@inheritDoc}
     */
    public function contentUrl(string $which = '', string $subDir = ''): string
    {
        return $this->resolve(self::TYPE_URL, $which, $subDir);
    }

    protected function resolve(string $type, string $which = '', string $subDir = ''): ?string
    {
        if ($which === '') {
            $path = $type === self::TYPE_DIR
                ? $this->contentPath
                : $this->contentUrl;

            return $path;
        }

        if (isset($this->locations[$type][$which])) {
            $path = trailingslashit(trailingslashit($this->locations[$type][$which]).ltrim($subDir, '\\/'));

            return $path;
        }

        $path = $type === self::TYPE_DIR
            ? trailingslashit(realpath("{$this->contentPath}/".$which))
            : trailingslashit($this->contentUrl.$which);

        $this->locations[$type][$which] = $path;

        $path = trailingslashit($path.ltrim($subDir, '\\/'));

        return $path;
    }
}
