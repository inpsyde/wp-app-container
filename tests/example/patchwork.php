<?php

declare(strict_types=1);

error_reporting(E_ALL);

use Inpsyde\App\Tests\Project\TestCase;

require_once __DIR__ . '/vendor/antecedent/patchwork/Patchwork.php';

Patchwork\redefine('get_option', static function (string $name, $default = false) {
    return TestCase::$options[$name] ?? $default;
});

Patchwork\redefine('get_transient', static function (string $name) {
    return TestCase::$options["transient_{$name}"] ?? false;
});

Patchwork\redefine('get_site_transient', static function (string $name) {
    return TestCase::$options["site_transient_{$name}"] ?? false;
});
