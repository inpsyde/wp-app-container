<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Location;

use Inpsyde\App\EnvConfig;
use Inpsyde\App\Location\LocationResolver;
use Inpsyde\App\Location\WpEngineLocations;
use Inpsyde\App\Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class WpEngineLocationsTest extends TestCase
{
    /**
     * @test
     */
    public function testWpeLocations(): void
    {
        $libDir = dirname(__DIR__, 3);

        define('ABSPATH', "{$libDir}/");
        define('WP_CONTENT_DIR', "{$libDir}/wp-content/");

        $locations = WpEngineLocations::createFromConfig(new EnvConfig());

        $privateDir = $locations->privateDir();

        static::assertSame(str_replace('\\', '/', $libDir) . '/_wpeprivate/', $privateDir);
    }

    /**
     * @test
     */
    public function testResolveCustomLocations(): void
    {
        $libDir = str_replace('\\', '/', dirname(__DIR__, 3));

        define('ABSPATH', "{$libDir}/");
        define('WP_CONTENT_DIR', $libDir);

        define(
            'LOCATIONS',
            [
                LocationResolver::DIR => [
                    WpEngineLocations::PRIVATE => '/var/www/private/',
                ],
            ]
        );

        $_ENV['WP_APP_CONTENT_URL'] = 'https://static.example.com';

        $locations = WpEngineLocations::createFromConfig(new EnvConfig());

        $privateDir = $locations->privateDir();
        $themeUrl = $locations->themesUrl('twentytwenty');

        static::assertSame('/var/www/private/', $privateDir);
        static::assertSame('https://static.example.com/themes/twentytwenty', $themeUrl);
    }
}
