<?php # -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Inpsyde\App\Tests\Location;

use Inpsyde\App\EnvConfig;
use Inpsyde\App\Location\LocationResolver;
use Inpsyde\App\Location\VipLocations;
use Inpsyde\App\Tests\TestCase;
use Brain\Monkey\Functions;

class VipLocationsTest extends TestCase
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
    public function testVipLocations()
    {
        $libDir = dirname(__DIR__, 3);

        define('ABSPATH', "{$libDir}/");
        define('WP_CONTENT_DIR', $libDir);
        define('WPCOM_VIP_CLIENT_MU_PLUGIN_DIR', "{$libDir}/client-mu-plugins");
        define('WPCOM_VIP_PRIVATE_DIR', "{$libDir}/private");

        $contentDir = str_replace('\\', '/', $libDir);

        $locations = VipLocations::createFromConfig(new EnvConfig());

        $imagesDir = $locations->imagesDir();
        $imagesUrl = $locations->imagesUrl();
        $clientMuDir = $locations->clientMuPluginsDir();
        $clientMuUrl = $locations->clientMuPluginsUrl();
        $vendorDir = $locations->vendorDir();
        $vendorUrl = $locations->vendorUrl();
        $privateDir = $locations->privateDir();
        $configDir = $locations->vipConfigDir('vip-config.php');

        static::assertSame("{$contentDir}/images/", $imagesDir);
        static::assertSame('http://example.com/wp-content/images/', $imagesUrl);
        static::assertSame("{$contentDir}/client-mu-plugins/", $clientMuDir);
        static::assertSame('http://example.com/wp-content/client-mu-plugins/', $clientMuUrl);
        static::assertSame("{$contentDir}/client-mu-plugins/vendor/", $vendorDir);
        static::assertSame('http://example.com/wp-content/client-mu-plugins/vendor/', $vendorUrl);
        static::assertSame("{$contentDir}/private/", $privateDir);
        static::assertSame("{$contentDir}/vip-config/vip-config.php", $configDir);
    }

    /**
     * @runInSeparateProcess
     */
    public function testResolveCustomLocations()
    {
        $libDir = dirname(__DIR__, 3);

        define('ABSPATH', "{$libDir}/");
        define('WP_CONTENT_DIR', $libDir);
        define('WPCOM_VIP_PRIVATE_DIR', "{$libDir}/private");

        $contentDir = str_replace('\\', '/', $libDir);

        define(
            'LOCATIONS',
            [
                LocationResolver::URL => [
                    VipLocations::CONTENT => 'http://static.example.com'
                ],
                LocationResolver::DIR => [
                    VipLocations::PRIVATE => '/var/www/private/'
                ]
            ]
        );

        $_ENV['WP_APP_IMAGES_DIR'] = '/var/www/images/';

        $locations = VipLocations::createFromConfig(new EnvConfig());

        $imagesDir = $locations->imagesDir();
        $imagesUrl = $locations->imagesUrl();
        $clientMuDir = $locations->clientMuPluginsDir();
        $clientMuUrl = $locations->clientMuPluginsUrl();
        $vendorDir = $locations->vendorDir();
        $vendorUrl = $locations->vendorUrl();
        $privateDir = $locations->privateDir();
        $themeUrl = $locations->themesUrl('twentytwenty');

        static::assertSame('/var/www/images/', $imagesDir);
        static::assertSame('http://static.example.com/images/', $imagesUrl);
        static::assertSame("{$contentDir}/client-mu-plugins/", $clientMuDir);
        static::assertSame('http://static.example.com/client-mu-plugins/', $clientMuUrl);
        static::assertSame("{$contentDir}/client-mu-plugins/vendor/", $vendorDir);
        static::assertSame('http://static.example.com/client-mu-plugins/vendor/', $vendorUrl);
        static::assertSame('/var/www/private/', $privateDir);
        static::assertSame('http://static.example.com/themes/twentytwenty', $themeUrl);
    }
}
