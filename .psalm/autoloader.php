<?php // phpcs:disable
if (defined('ABSPATH')) {
    return;
}

define('ABSPATH', dirname(__DIR__) . '/vendor/php-stubs/wordpress-stubs/');
const WPINC = 'wp-includes';
const WP_CONTENT_DIR = ABSPATH . 'wp-content';
const WP_PLUGIN_DIR = WP_CONTENT_DIR . '/plugins';
const WPMU_PLUGIN_DIR = WP_CONTENT_DIR . '/mu-plugins';
const WP_LANG_DIR = ABSPATH . WPINC . '/languages';
