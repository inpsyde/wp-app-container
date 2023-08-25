<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    public static $options = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        static::$options = [
            'siteurl' => WP_SITEURL,
            'home' => WP_HOME,
            'template' => 'sample-theme',
            'stylesheet' => 'sample-theme',
        ];
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        static::$options = [];
        \Patchwork\restoreAll();
    }

    /**
     * @return void
     */
    protected function runWordPress(): void
    {
        $muPlugins = glob(WPMU_PLUGIN_DIR . '/*.php');
        shuffle($muPlugins);
        foreach ($muPlugins as $muPlugin) {
            wp_register_plugin_realpath(realpath($muPlugin));
            require_once $muPlugin;
        }

        do_action('muplugins_loaded');

        $this->onBeforePlugins();

        $plugins = glob(WP_PLUGIN_DIR . '/*/index.php');
        shuffle($plugins);
        foreach ($plugins as $plugin) {
            wp_register_plugin_realpath(realpath($plugin));
            require_once $plugin;
        }

        do_action('plugins_loaded');

        $this->onAfterPlugins();

        do_action('setup_theme');

        $themes = glob(WP_CONTENT_DIR . '/themes/*/functions.php');
        $theme = reset($themes);
        require_once $theme;

        do_action('after_setup_theme');

        $this->onAfterTheme();

        do_action('init');

        $this->onAfterInit();

        do_action('template_redirect');

        $this->onBeforeShutdown();

        do_action('shutdown');
    }

    abstract protected function onBeforePlugins(): void;

    abstract protected function onAfterPlugins(): void;

    abstract protected function onAfterTheme(): void;

    abstract protected function onAfterInit(): void;

    abstract protected function onBeforeShutdown(): void;
}
