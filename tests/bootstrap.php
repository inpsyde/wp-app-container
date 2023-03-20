<?php

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions
// phpcs:disable WordPress.PHP.DevelopmentFunctions

declare(strict_types=1);

$testsDir = str_replace('\\', '/', __DIR__);
$libDir = dirname($testsDir);
$vendorDir = "{$libDir}/vendor";
$autoload = "{$vendorDir}/autoload.php";

if (!is_file($autoload)) {
    die('Please install via Composer before running tests.');
}

putenv('TESTS_BASE_PATH=' . $testsDir);
putenv('LIBRARY_PATH=' . $libDir);
putenv('VENDOR_PATH=' . $vendorDir);

error_reporting(E_ALL);

if (!defined('ABSPATH')) {
    define('ABSPATH', "{$vendorDir}/roots/wordpress-no-content/");
}
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}
if (!defined('WP_CONTENT_URL')) {
    define('WP_CONTENT_URL', 'https://example.com/wp-content');
}
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}
if (!defined('WP_PLUGIN_URL')) {
    define('WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins');
}
if (!defined('WPMU_PLUGIN_DIR')) {
    define('WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins');
}
if (!defined('WPMU_PLUGIN_URL')) {
    define('WPMU_PLUGIN_URL', WP_CONTENT_URL . '/mu-plugins');
}
if (!defined('WP_LANG_DIR')) {
    define('WP_LANG_DIR', ABSPATH . WPINC . '/languages');
}

require_once "{$vendorDir}/antecedent/patchwork/Patchwork.php";

if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', $autoload);
    require_once $autoload;
}

unset($libDir, $testsDir, $vendorDir, $autoload);
