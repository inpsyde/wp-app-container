<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Config;

use Brain\Monkey;
use Inpsyde\App\Config\EnvConfig;
use Inpsyde\App\Config\Locations;
use Inpsyde\App\Tests\TestCase;
use Inpsyde\App\Tests\TestLocations;

/**
 * @runTestsInSeparateProcesses
 */
class LocationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Monkey\Functions\stubs([
            'plugin_basename' => static function (string $file): string {
                $file = wp_normalize_path($file);
                if (preg_match('~/plugins/([^/]+/[^/]+\.php)$~', $file, $matches)) {
                    return $matches[1];
                }
                if (preg_match('~/plugins/([^/]+\.php)$~', $file, $matches)) {
                    return $matches[1];
                }
                return trim($file, '/');
            },
            'plugins_url' => static function (string $path = '', string $plugin = ''): string {
                $plugin = wp_normalize_path($plugin);
                $mupluginDir = wp_normalize_path(WPMU_PLUGIN_DIR);
                $url = (($plugin !== '') && (strpos($plugin, $mupluginDir) === 0))
                    ? WPMU_PLUGIN_URL
                    : WP_PLUGIN_URL;
                if ($plugin !== '') {
                    $folder = dirname(plugin_basename($plugin));
                    if ($folder !== '') {
                        $url .= '/' . ltrim($folder, '/');
                    }
                }
                if ($path !== '') {
                    $url .= '/' . ltrim($path, '/');
                }
                return $url;
            },
        ]);
    }

    /**
     * @test
     */
    public function testAutoLocations(): void
    {
        $location = Locations::new();

        static::assertSame(ABSPATH, $location->rootDir());
        static::assertSame('https://example.com/foo', $location->rootUrl('/foo'));

        static::assertSame(getenv('VENDOR_PATH'), $location->vendorDir(''));
        static::assertSame(null, $location->vendorUrl());
    }

    /**
     * @test
     */
    public function testLocationsFromConfig(): void
    {
        define(__NAMESPACE__ . '\\WP_APP_VENDOR_DIR', __DIR__);
        define(__NAMESPACE__ . '\\WP_APP_VENDOR_URL', 'http://vendor.example.com');

        $location = Locations::fromConfig(new EnvConfig(__NAMESPACE__));

        static::assertSame(ABSPATH, $location->rootDir());
        static::assertSame('https://example.com/foo', $location->rootUrl('/foo'));

        static::assertSame(wp_normalize_path(__DIR__), $location->vendorDir(''));
        static::assertSame('https://vendor.example.com/xyz/', $location->vendorUrl('/xyz/'));
    }

    /**
     * @test
     */
    public function testLocationsVipConstant(): void
    {
        define('WPCOM_VIP_CLIENT_MU_PLUGIN_DIR', dirname(TestLocations::VENDOR_DIR));

        $locations = TestLocations::new();

        static::assertSame(TestLocations::VENDOR_DIR, $locations->vendorDir(''));
        static::assertSame(WP_CONTENT_URL . '/client-mu-plugins/vendor/', $locations->vendorUrl());
    }
}
