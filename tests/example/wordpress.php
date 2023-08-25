<?php

declare(strict_types=1);

const WP_HOME = 'https://example.com/';
const WP_SITEURL = 'https://example.com/';
const WP_DEBUG = true;
const WP_DEFAULT_THEME = 'sample-theme';
const ABSPATH = __DIR__ . '/vendor/roots/wordpress-no-content/';
const WPINC = 'wp-includes';
const WP_CONTENT_DIR = __DIR__ . '/project';
const WP_CONTENT_URL = WP_SITEURL . 'wp-content';
const WP_PLUGIN_DIR = WP_CONTENT_DIR . '/plugins';
const WP_PLUGIN_URL = WP_CONTENT_URL . '/plugins';
const WPMU_PLUGIN_DIR = WP_CONTENT_DIR . '/mu-plugins';
const WPMU_PLUGIN_URL = WP_CONTENT_URL . '/mu-plugins';
const WP_LANG_DIR = ABSPATH . WPINC . '/languages';

global $wp_plugin_paths, $shortcode_tags;
$wp_plugin_paths = [];
$shortcode_tags = [];

require_once ABSPATH . WPINC . '/plugin.php';
require_once ABSPATH . WPINC . '/load.php';
require_once ABSPATH . WPINC . '/functions.php';
require_once ABSPATH . WPINC . '/formatting.php';
require_once ABSPATH . WPINC . '/link-template.php';
require_once ABSPATH . WPINC . '/theme.php';
require_once ABSPATH . WPINC . '/kses.php';
require_once ABSPATH . WPINC . '/pomo/translations.php';
require_once ABSPATH . WPINC . '/l10n.php';
require_once ABSPATH . WPINC . '/default-constants.php';
wp_initial_constants();
wp_templating_constants();
