<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Location;

use Inpsyde\App\EnvConfig;
use Inpsyde\App\Location\LocationResolver;
use Inpsyde\App\Location\Locations;
use Inpsyde\App\Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class LocationResolverTest extends TestCase
{
    /**
     * @test
     */
    public function testResolveInvalidLocation(): void
    {
        define('ABSPATH', dirname(__DIR__));
        define('WP_CONTENT_DIR', __DIR__);

        $resolver = new LocationResolver(new EnvConfig());

        static::assertNull($resolver->resolveUrl('foo', 'bar'));
        static::assertNull($resolver->resolveDir('foo', 'bar'));
    }

    /**
     * @test
     */
    public function testResolveDefaultLocations(): void
    {
        define('ABSPATH', dirname(__DIR__, 3) . '/');
        define('WP_CONTENT_DIR', str_replace('\\', '/', dirname(__DIR__, 3)));

        $resolver = new LocationResolver(new EnvConfig());

        $vendorDir = $resolver->resolveDir(Locations::VENDOR, 'foo/bar');
        $vendorUrl = $resolver->resolveUrl(Locations::VENDOR);

        $contentDir = $resolver->resolveDir(Locations::CONTENT);
        $contentUrl = $resolver->resolveUrl(Locations::CONTENT);

        $rootDir = $resolver->resolveDir(Locations::ROOT);
        $rootUrl = $resolver->resolveUrl(Locations::ROOT);

        static::assertSame($vendorDir, WP_CONTENT_DIR . '/vendor/foo/bar');
        static::assertSame($vendorUrl, 'https://example.com/wp-content/vendor/');

        static::assertSame($contentDir, WP_CONTENT_DIR . '/');
        static::assertSame($contentUrl, 'https://example.com/wp-content/');

        static::assertSame($rootDir, ABSPATH);
        static::assertSame($rootUrl, 'https://example.com/');
    }

    /**
     * @test
     */
    public function testResolveOverriddenDefaultLocations(): void
    {
        define('ABSPATH', __DIR__ . '/');
        define('WP_CONTENT_DIR', __DIR__ . '/wp-content');

        define(
            'LOCATIONS',
            [
                LocationResolver::URL => [
                    Locations::ROOT => 'https://root.example.com',
                ],
            ]
        );

        $resolver = new LocationResolver(
            new EnvConfig(),
            [
                LocationResolver::URL => [
                    Locations::VENDOR => 'https://example.com/vendor',
                ],
                LocationResolver::DIR => [
                    Locations::VENDOR => __DIR__ . '/vendor',
                ],
            ]
        );

        $_ENV['WP_APP_ROOT_DIR'] = '/var/www/';
        $_ENV['WP_APP_CONTENT_URL'] = 'https://content.example.com';

        $vendorDir = $resolver->resolveDir(Locations::VENDOR, 'foo/bar');
        $vendorUrl = $resolver->resolveUrl(Locations::VENDOR);
        $rootDir = $resolver->resolveDir(Locations::ROOT);
        $rootUrl = $resolver->resolveUrl(Locations::ROOT);
        $contentDir = $resolver->resolveDir(Locations::CONTENT);
        $contentUrl = $resolver->resolveUrl(Locations::CONTENT);
        $pluginUrl = $resolver->resolveUrl(Locations::PLUGINS, 'multilingualpress');

        static::assertSame(str_replace('\\', '/', __DIR__) . '/vendor/foo/bar', $vendorDir);
        static::assertSame('https://example.com/vendor/', $vendorUrl);
        static::assertSame('/var/www/', $rootDir);
        static::assertSame('https://root.example.com/', $rootUrl);
        static::assertSame('https://content.example.com/', $contentUrl);
        static::assertSame(str_replace('\\', '/', __DIR__) . '/wp-content/', $contentDir);
        static::assertSame('https://content.example.com/plugins/multilingualpress', $pluginUrl);
    }

    /**
     * @test
     */
    public function testResolveCustomLocations(): void
    {
        define('ABSPATH', dirname(__DIR__, 3) . '/');
        define('WP_CONTENT_DIR', str_replace('\\', '/', dirname(__DIR__, 3)));

        $resolver = new LocationResolver(
            new EnvConfig(),
            [
                LocationResolver::URL => [
                    'foo' => 'https://example.com/foo',
                ],
                LocationResolver::DIR => [
                    'foo' => __DIR__,
                ],
            ]
        );

        $_ENV['WP_APP_BAR_DIR'] = dirname(__DIR__);
        $_ENV['WP_APP_BAR_URL'] = 'https://example.com/bar';

        $fooDir = $resolver->resolveDir('foo');
        $fooUrl = $resolver->resolveUrl('foo');

        $barDir = $resolver->resolveDir('bar');
        $barUrl = $resolver->resolveUrl('bar');

        static::assertSame($fooDir, str_replace('\\', '/', __DIR__) . '/');
        static::assertSame($fooUrl, 'https://example.com/foo/');

        static::assertSame($barDir, str_replace('\\', '/', dirname(__DIR__)) . '/');
        static::assertSame($barUrl, 'https://example.com/bar/');
    }
}
