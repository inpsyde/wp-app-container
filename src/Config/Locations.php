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
    private $locations;

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
            if (($dir === '') || !is_string($dir)) {
                // if not custom defined, let's use auto-discovered value, if any
                is_array($discovered) or $discovered = self::new()->locations;
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
     *
     * @see Locations::findVendorDir()
     */
    protected static function discoverVendor(Location $root, Location $content): ?Location
    {
        $vendorDir = static::findVendorDir();
        if ($vendorDir === null) {
            return null;
        }

        $vendorDir = untrailingslashit(wp_normalize_path($vendorDir));

        /*
         * If vendor dir is found inside lib root, lib is installed as root package.
         * We know the path, but can't determine the URL.
         */
        $libRoot = untrailingslashit(wp_normalize_path(dirname(__DIR__, 2)));
        if (strpos($vendorDir, "{$libRoot}/") === 0) {
            return Location::new($vendorDir, null);
        }

        $contentUrl = $content->url();
        $candidates = [[$root->dir(), $root->url()], [$content->dir(), $contentUrl]];

        // Support for VIP + Inpsyde Composer VIP plugin
        if (
            defined('WPCOM_VIP_CLIENT_MU_PLUGIN_DIR')
            && is_string(\WPCOM_VIP_CLIENT_MU_PLUGIN_DIR)
        ) {
            /** @psalm-suppress MixedArgument */
            $path = wp_normalize_path(\WPCOM_VIP_CLIENT_MU_PLUGIN_DIR);
            array_unshift(
                $candidates,
                [
                    untrailingslashit($path),
                    ($contentUrl === null) ? null : "{$contentUrl}/client-mu-plugins",
                ]
            );
        }

        foreach ($candidates as [$basePath, $baseUrl]) {
            if ($basePath === $vendorDir) {
                return Location::new($vendorDir, $baseUrl);
            }

            if (strpos($vendorDir, "{$basePath}/") === 0) {
                $vendorUrl = ($baseUrl === null)
                    ? null
                    : "{$baseUrl}/" . (substr($vendorDir, strlen("{$basePath}/")) ?: '');

                return Location::new($vendorDir, $vendorUrl);
            }
        }

        return null;
    }

    /**
     * @return string|null
     */
    protected static function findVendorDir(): ?string
    {
        /*
         * ClassLoader is located in vendor dir. We have 3 options:
         * - vendor dir not found
         * - vendor dir is found inside lib root (lib is installed as root package)
         * - lib root is found inside vendor dir (lib is installed as dependency)
         */
        $ref = class_exists(\Composer\Autoload\ClassLoader::class)
            ? new \ReflectionClass(\Composer\Autoload\ClassLoader::class)
            : null;
        $file = $ref ? $ref->getFileName() : null;
        if (!$file) {
            return null;
        }

        return dirname($file, 2);
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
     * Returns the URL by given path
     *
     * @param string $path
     * @return string|null
     */
    public function resolveUrlByPath(string $path): ?string
    {
        $rawDir = wp_normalize_path($path);
        $dir = untrailingslashit($rawDir);
        $suffix = ($rawDir === $dir) ? '' : '/';

        $found = ['name' => '', 'path' => '', 'length' => -1];
        foreach (self::NAMES as $name) {
            $path = rtrim($this->resolve($name, self::DIR) ?? '', '/');
            if ($path === $dir) {
                return $this->resolve($name, self::URL, $suffix);
            }
            if (strpos($dir, "{$path}/") !== 0) {
                continue;
            }
            $length = strlen($path);
            ($length > $found['length']) and $found = compact('name', 'path', 'length');
        }

        if (!$found['name'] || !$found['path']) {
            return $this->maybeResolveClientMuPluginUrlByPath($dir, $suffix);
        }

        $relative = substr($dir, strlen($found['path'])) ?: '';

        return $this->resolveUrl($found['name'], $relative . $suffix);
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

    /**
     * @param string $dir
     * @param string $suffix
     * @return string|null
     */
    private function maybeResolveClientMuPluginUrlByPath(string $dir, string $suffix): ?string
    {
        $clientMuPlugins = defined('WPCOM_VIP_CLIENT_MU_PLUGIN_DIR')
            ? \WPCOM_VIP_CLIENT_MU_PLUGIN_DIR
            : '';

        if (($clientMuPlugins === '') || !is_string($clientMuPlugins)) {
            return null;
        }

        $baseUrl = $this->resolveUrl(self::CONTENT, '/client-mu-plugins');
        if ($baseUrl === null) {
            return null;
        }

        $clientMuPlugins = rtrim(wp_normalize_path($clientMuPlugins), '/');

        if ($dir === $clientMuPlugins) {
            return $baseUrl . $suffix;
        }

        if (strpos($dir, "{$clientMuPlugins}/") === 0) {
            $relative = substr($dir, strlen($clientMuPlugins)) ?: '';

            return $baseUrl . $relative . $suffix;
        }

        return null;
    }
}
