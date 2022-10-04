<?php

declare(strict_types=1);

namespace Inpsyde\App\Config;

class Locations
{
    public const CONTENT = 'content';
    public const LANGUAGES = 'languages';
    public const MU_PLUGINS = 'mu-plugins';
    public const PLUGINS = 'plugins';
    public const ROOT = 'root';
    public const THEME = 'theme';
    public const THEMES = 'themes';
    public const VENDOR = 'vendor';

    private const NAMES = [
        self::CONTENT,
        self::LANGUAGES,
        self::MU_PLUGINS,
        self::PLUGINS,
        self::ROOT,
        self::THEME,
        self::THEMES,
        self::VENDOR,
    ];

    private const DIR = 'dir';
    private const URL = 'url';

    /**
     * @var array<string, Location|null>
     */
    private $locations = [];

    /**
     * @param Config $config
     * @return Locations
     */
    public static function fromConfig(Config $config): Locations
    {
        $instance = new self();
        $discovered = null;

        foreach (self::NAMES as $name) {
            $dir = $config->get('WP_APP_' . strtoupper("{$name}_DIR"));
            if (!$dir || !is_string($dir) || !is_dir($dir)) {
                // if not custom defined, let's use auto-discovered value, if any
                is_array($discovered) or $discovered = (new self())->locations;
                $location = $discovered[$name] ?? null;
                $location and $instance->addLocation($name, $location);
                continue;
            }

            $url = $config->get('WP_APP_' . strtoupper("{$name}_URL"));
            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                $instance->addLocation($name, Location::new($dir, (string)$url));
                continue;
            }

            $instance->addLocation($name, Location::new($dir, null));
        }

        return $instance;
    }

    /**
     * @param Location|null $root
     * @param Location|null $content
     * @param Location|null $vendor
     * @param Location|null $plugins
     * @param Location|null $themes
     * @param Location|null $theme
     * @param Location|null $muPlugins
     * @param Location|null $languages
     * @return Locations
     *
     * phpcs:disable Generic.Metrics.CyclomaticComplexity
     */
    public static function new(
        ?Location $root = null,
        ?Location $content = null,
        ?Location $vendor = null,
        ?Location $plugins = null,
        ?Location $themes = null,
        ?Location $theme = null,
        ?Location $muPlugins = null,
        ?Location $languages = null
    ): Locations {
        // phpcs:enable Generic.Metrics.CyclomaticComplexity

        $muPluginsDir = defined('WPMU_PLUGIN_DIR') ? (string)WPMU_PLUGIN_DIR : '';
        $pluginsDir = defined('WP_PLUGIN_DIR') ? (string)WP_PLUGIN_DIR : '';

        $root = $root ?? Location::new(ABSPATH, site_url(''));
        $content = $content ?? Location::new(WP_CONTENT_DIR, content_url(''));

        return new self(
            $root,
            $content,
            $vendor ?? static::discoverVendor($root, $content),
            $plugins ?? Location::new($pluginsDir, plugins_url()),
            $themes ?? Location::new(get_theme_root(), get_theme_root_uri()),
            $theme ?? Location::new(get_stylesheet_directory(), get_stylesheet_directory_uri()),
            $muPlugins ?? Location::new($muPluginsDir, plugins_url('', $muPluginsDir)),
            $languages ?? static::discoverLanguages($root, $content)
        );
    }

    /**
     * @param Location $root
     * @param Location $content
     * @return Location|null
     */
    private static function discoverVendor(Location $root, Location $content): ?Location
    {
        $contentUrl = $content->url();
        $candidates = [
            [$root->dir(), $root->url()],
            [$content->dir(), $contentUrl],
        ];

        // Support for VIP + Inpsyde Composer VIP plugin
        if (defined('WPCOM_VIP_CLIENT_MU_PLUGIN_DIR')) {
            $candidates[] = [WPCOM_VIP_CLIENT_MU_PLUGIN_DIR, "{$contentUrl}/client-mu-plugins"];
        }

        foreach ($candidates as [$basePath, $baseUrl]) {
            if (!$basePath || !is_string($basePath)) {
                continue;
            }
            $basePath = untrailingslashit(wp_normalize_path($basePath));
            if (is_dir("{$basePath}/vendor/inpsyde/wp-app-container/src")) {
                return Location::new("{$basePath}/vendor", $baseUrl ? "{$baseUrl}/vendor" : null);
            }
        }

        $vendorDir = wp_normalize_path(dirname(__DIR__, 4));
        if (is_dir($vendorDir) && is_dir("{$vendorDir}/psr/container")) {
            return Location::new($vendorDir, null);
        }

        return null;
    }

    /**
     * @param Location $root
     * @param Location $content
     * @return Location
     */
    private static function discoverLanguages(Location $root, Location $content): Location
    {
        if (!defined('WP_LANG_DIR')) {
            wp_set_lang_dir();
        }

        if (WP_LANG_DIR === (WP_CONTENT_DIR . '/languages')) {
            return Location::compose($content, 'languages');
        }

        return Location::new((string)WP_LANG_DIR, ($root->url() ?? '') . '/wp-includes/languages');
    }

    /**
     * @param Location|null $root
     * @param Location|null $content
     * @param Location|null $vendor
     * @param Location|null $plugins
     * @param Location|null $themes
     * @param Location|null $theme
     * @param Location|null $muPlugins
     * @param Location|null $languages
     */
    private function __construct(
        ?Location $root = null,
        ?Location $content = null,
        ?Location $vendor = null,
        ?Location $plugins = null,
        ?Location $themes = null,
        ?Location $theme = null,
        ?Location $muPlugins = null,
        ?Location $languages = null
    ) {

        $this->locations = [
            self::ROOT => $root,
            self::CONTENT => $content,
            self::VENDOR => $vendor,
            self::PLUGINS => $plugins,
            self::THEMES => $themes,
            self::THEME => $theme,
            self::MU_PLUGINS => $muPlugins,
            self::LANGUAGES => $languages,
        ];
    }

    /**
     * @param string $name
     * @param Location $location
     * @return static
     */
    public function addLocation(string $name, Location $location): Locations
    {
        $this->locations[$name] = $location;

        return $this;
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
     * @param string $path
     * @return string|null
     */
    public function pluginsDir(string $path = '/'): ?string
    {
        return $this->resolveDir(self::PLUGINS, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function pluginsUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(self::PLUGINS, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function muPluginsDir(string $path = '/'): ?string
    {
        return $this->resolveDir(self::MU_PLUGINS, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function muPluginsUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(self::MU_PLUGINS, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function themeDir(string $path = '/'): ?string
    {
        return $this->resolveDir(self::THEME, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function themeUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(self::THEME, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function themesDir(string $path = '/'): ?string
    {
        return $this->resolveDir(self::THEMES, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function themesUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(self::THEMES, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function languagesDir(string $path = '/'): ?string
    {
        return $this->resolveDir(self::LANGUAGES, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function languagesUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(self::LANGUAGES, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function contentDir(string $path = '/'): ?string
    {
        return $this->resolveDir(self::CONTENT, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function contentUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(self::CONTENT, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function vendorDir(string $path = '/'): ?string
    {
        return $this->resolveDir(self::VENDOR, $path);
    }

    /**
     * @param string $path
     * @return string|null It is expected to be null if vendor folder is outside web-root.
     */
    public function vendorUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(self::VENDOR, $path);
    }

    /**
     * Returns the website root directory path.
     *
     * @param string $path
     * @return string|null
     */
    public function rootDir(string $path = '/'): ?string
    {
        return $this->resolveDir(self::ROOT, $path);
    }

    /**
     * Returns the website root url.
     *
     * @param string $path
     * @return string|null
     */
    public function rootUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(self::ROOT, $path);
    }

    /**
     * @param string $name
     * @param string $dirOrUrl
     * @param string|null $path
     * @return string|null
     */
    private function resolve(string $name, string $dirOrUrl, ?string $path = null): ?string
    {
        $location = $this->locations[$name] ?? null;
        if (!$location) {
            return null;
        }

        $isDir =  $dirOrUrl === self::DIR;

        $base = $isDir ? $location->dir() : $location->url();
        if (!$path || ($base === null)) {
            return $base;
        }

        $path = $isDir ? wp_normalize_path($path) : filter_var($path, FILTER_SANITIZE_URL);

        return (string)trailingslashit($base) . ltrim((string)$path, '\\/');
    }
}
