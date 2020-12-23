<?php

declare(strict_types=1);

namespace Inpsyde\App\Location;

use Inpsyde\App\EnvConfig;

interface Locations
{
    public const CONTENT = 'content';
    public const VENDOR = 'vendor';
    public const ROOT = 'root';
    public const PLUGINS = 'plugins';
    public const THEMES = 'themes';
    public const MU_PLUGINS = 'mu-plugins';
    public const LANGUAGES = 'languages';

    /**
     * @param EnvConfig $config
     * @return Locations
     */
    public static function createFromConfig(EnvConfig $config): Locations;

    /**
     * @param string $name
     * @param string $path
     * @return string|null
     */
    public function resolveDir(string $name, string $path = '/'): ?string;

    /**
     * @param string $name
     * @param string $path
     * @return string|null
     */
    public function resolveUrl(string $name, string $path = '/'): ?string;

    /**
     * @param string $path
     * @return string|null
     */
    public function pluginsDir(string $path = '/'): ?string;

    /**
     * @param string $path
     * @return string|null
     */
    public function pluginsUrl(string $path = '/'): ?string;

    /**
     * @param string $path
     * @return string|null
     */
    public function muPluginsDir(string $path = '/'): ?string;

    /**
     * @param string $path
     * @return string|null
     */
    public function muPluginsUrl(string $path = '/'): ?string;

    /**
     * @param string $path
     * @return string|null
     */
    public function themesDir(string $path = '/'): ?string;

    /**
     * @param string $path
     * @return string|null
     */
    public function themesUrl(string $path = '/'): ?string;

    /**
     * @return string|null
     */
    public function languagesDir(): ?string;

    /**
     * @return string|null
     */
    public function languagesUrl(): ?string;

    /**
     * @param string $path
     * @return string|null
     */
    public function contentDir(string $path = '/'): ?string;

    /**
     * @param string $path
     * @return string|null
     */
    public function contentUrl(string $path = '/'): ?string;

    /**
     * @param string $path
     * @return string|null|null
     */
    public function vendorDir(string $path = '/'): ?string;

    /**
     * @param string $path
     * @return string|null|null It is expected to be null if vendor folder is outside web-root.
     */
    public function vendorUrl(string $path = '/'): ?string;

    /**
     * Returns the website root directory path.
     *
     * @return string|null
     */
    public function rootDir(): ?string;

    /**
     * Returns the website root url.
     *
     * @return string|null
     */
    public function rootUrl(): ?string;
}
