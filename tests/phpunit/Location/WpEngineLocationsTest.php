<?php # -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Inpsyde\App\Tests\Location;

use Inpsyde\App\EnvConfig;
use Inpsyde\App\Location\LocationResolver;
use Inpsyde\App\Location\WpEngineLocations;
use Inpsyde\App\Tests\TestCase;
use Brain\Monkey\Functions;

class WpEngineLocationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('network_site_url')->alias(function (string $path = '/'): string {
            return 'http://example.com/' . ltrim($path, '/');
        });

        Functions\when('content_url')->alias(function (string $path = '/'): string {
            return 'http://example.com/wp-content/' . ltrim($path, '/');
        });

        Functions\when('wp_normalize_path')->alias(function (string $path): string {
            return str_replace('\\', '/', $path);
        });
    }

    /**
     * @runInSeparateProcess
     */
    public function testWpeLocations()
    {
        $libDir = dirname(__DIR__, 3);

        define('ABSPATH', "{$libDir}/");
        define('WP_CONTENT_DIR', "{$libDir}/wp-content/");

        $locations = WpEngineLocations::createFromConfig(new EnvConfig());

        $privateDir = $locations->privateDir();

        static::assertSame(str_replace('\\', '/', $libDir) . '/_wpeprivate/', $privateDir);
    }

    /**
     * @runInSeparateProcess
     */
    public function testResolveCustomLocations()
    {
        $libDir = str_replace('\\', '/', dirname(__DIR__, 3));

        define('ABSPATH', "{$libDir}/");
        define('WP_CONTENT_DIR', $libDir);

        define(
            'LOCATIONS',
            [
                LocationResolver::DIR => [
                    WpEngineLocations::PRIVATE => '/var/www/private/'
                ]
            ]
        );

        $_ENV['WP_APP_CONTENT_URL'] = 'http://static.example.com';

        $locations = WpEngineLocations::createFromConfig(new EnvConfig());

        $privateDir = $locations->privateDir();
        $themeUrl = $locations->themesUrl('twentytwenty');

        static::assertSame('/var/www/private/', $privateDir);
        static::assertSame('http://static.example.com/themes/twentytwenty', $themeUrl);
    }
}
