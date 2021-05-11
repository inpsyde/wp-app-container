<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Location;

use Inpsyde\App\EnvConfig;
use Inpsyde\App\Location\GenericLocations;
use Inpsyde\App\Location\LocationResolver;
use Inpsyde\App\Tests\TestCase;
use Brain\Monkey\Functions;

/**
 * @runTestsInSeparateProcesses
 */
class GenericLocationsTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('network_site_url')->alias(static function (string $path = '/'): string {
            return 'http://example.com/' . ltrim($path, '/');
        });

        Functions\when('content_url')->alias(static function (string $path = '/'): string {
            return 'http://example.com/wp-content/' . ltrim($path, '/');
        });

        Functions\when('wp_normalize_path')->alias(static function (string $path): string {
            return str_replace('\\', '/', $path);
        });
    }

    /**
     * @test
     */
    public function testResolveDefaultLocations(): void
    {
        define('ABSPATH', dirname(__DIR__, 3) . '/');
        define('WP_CONTENT_DIR', str_replace('\\', '/', dirname(__DIR__, 3)));

        $locations = GenericLocations::createFromConfig(new EnvConfig());

        $vendorDir = $locations->vendorDir('foo/bar');
        $vendorUrl = $locations->vendorUrl();

        $contentDir = $locations->contentDir();
        $contentUrl = $locations->contentUrl();

        $pluginDir = $locations->pluginsDir('wordpress-seo');
        $pluginUrl = $locations->pluginsUrl('wordpress-seo');
        $themeDir = $locations->themesDir('twentytwenty');
        $themeUrl = $locations->themesUrl('twentytwenty');
        $muPluginDir = $locations->muPluginsDir('foo.php');
        $muPluginUrl = $locations->muPluginsUrl('foo.php');
        $languagesDir = $locations->languagesDir();
        $languagesUrl = $locations->languagesUrl();

        $rootDir = $locations->rootDir();
        $rootUrl = $locations->rootUrl();

        static::assertSame($vendorDir, WP_CONTENT_DIR . '/vendor/foo/bar');
        static::assertSame($vendorUrl, 'http://example.com/wp-content/vendor/');

        static::assertSame($contentDir, WP_CONTENT_DIR . '/');
        static::assertSame($contentUrl, 'http://example.com/wp-content/');

        static::assertSame($pluginDir, WP_CONTENT_DIR . '/plugins/wordpress-seo');
        static::assertSame($pluginUrl, 'http://example.com/wp-content/plugins/wordpress-seo');

        static::assertSame($themeDir, WP_CONTENT_DIR . '/themes/twentytwenty');
        static::assertSame($themeUrl, 'http://example.com/wp-content/themes/twentytwenty');

        static::assertSame($muPluginDir, WP_CONTENT_DIR . '/mu-plugins/foo.php');
        static::assertSame($muPluginUrl, 'http://example.com/wp-content/mu-plugins/foo.php');

        static::assertSame($languagesDir, WP_CONTENT_DIR . '/languages/');
        static::assertSame($languagesUrl, 'http://example.com/wp-content/languages/');

        static::assertSame($rootDir, ABSPATH);
        static::assertSame($rootUrl, 'http://example.com/');
    }

    /**
     * @test
     */
    public function testResolveCustomLocations(): void
    {
        define('ABSPATH', dirname(__DIR__, 3) . '/');
        define('WP_CONTENT_DIR', str_replace('\\', '/', dirname(__DIR__, 3)));

        define(
            'LOCATIONS',
            [
                LocationResolver::URL => [
                    'foo' => 'http://example.com/foo/',
                ],
                LocationResolver::DIR => [
                    'foo' => __DIR__,
                ],
            ]
        );

        $_ENV['WP_APP_BAR_DIR'] = dirname(__DIR__);
        $_ENV['WP_APP_BAR_URL'] = 'http://example.com/bar';

        $locations = GenericLocations::createFromConfig(new EnvConfig());

        $fooDir = $locations->resolveDir('foo');
        $fooUrl = $locations->resolveUrl('foo');

        $barDir = $locations->resolveDir('bar');
        $barUrl = $locations->resolveUrl('bar');

        static::assertSame($fooDir, str_replace('\\', '/', __DIR__) . '/');
        static::assertSame($fooUrl, 'http://example.com/foo/');

        static::assertSame($barDir, str_replace('\\', '/', dirname(__DIR__)) . '/');
        static::assertSame($barUrl, 'http://example.com/bar/');
    }
}
