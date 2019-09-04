<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

interface Paths
{

    public const MU_PLUGINS_DIR = 'mu-plugins';
    public const LANGUAGES_DIR = 'languages';
    public const PLUGINS_DIR = 'plugins';
    public const THEMES_DIR = 'themes';

    /**
     * @param string $plugin
     *
     * @return string
     */
    public function pluginsDir(string $plugin = ''): string;

    /**
     * @param string $plugin
     *
     * @return string
     */
    public function pluginsUrl(string $plugin = ''): string;

    /**
     * @param string $muPlugin
     *
     * @return string
     */
    public function muPluginsDir(string $muPlugin = ''): string;

    /**
     * @param string $muPlugin
     *
     * @return string
     */
    public function muPluginsUrl(string $muPlugin = ''): string;

    /**
     * @param string $theme
     *
     * @return string
     */
    public function themesDir(string $theme = ''): string;

    /**
     * @param string $theme
     *
     * @return string
     */
    public function themesUrl(string $theme = ''): string;

    /**
     * @return string
     */
    public function languagesDir(): string;

    /**
     * @return string
     */
    public function languagesUrl(): string;

    /**
     * @param string $vendor
     * @param string $package
     *
     * @return string
     */
    public function vendorPackageDir(string $vendor, string $package): string;

    /**
     * @param string $vendor
     * @param string $package
     *
     * @return string|null  if vendor-directory is outside of web-root, null is returned.
     */
    public function vendorPackageUrl(string $vendor, string $package): ?string;
}
