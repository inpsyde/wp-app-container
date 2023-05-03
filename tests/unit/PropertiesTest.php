<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Inpsyde\App\Config\EnvConfig;
use Inpsyde\App\Config\Locations;
use Inpsyde\App\Properties;

class PropertiesTest extends TestCase
{
    /**
     * @test
     */
    public function testItReadsComposerJsonAndUsesLocations(): void
    {
        $names = [
            Locations::CONTENT,
            Locations::LANGUAGES,
            Locations::MU_PLUGINS,
            Locations::PLUGINS,
            Locations::ROOT,
            Locations::THEME,
            Locations::THEMES,
            Locations::VENDOR,
        ];

        $config = new EnvConfig();
        $baseDir = dirname(__DIR__, 2) . '/';
        $baseUrl = 'https://example.com/';
        foreach ($names as $name) {
            $const = strtoupper($name);
            $config->set("WP_APP_{$const}_DIR", $baseDir . $name);
            $config->set("WP_APP_{$const}_URL", $baseUrl . $name);
        }

        $properties = new Properties(Locations::fromConfig($config), null, false);

        static::assertSame('inpsyde-wp-app', $properties->baseName());
        static::assertSame($baseDir, $properties->basePath());
        static::assertSame(
            'https://example.com/vendor/inpsyde/wp-app-container/',
            $properties->baseUrl()
        );

        static::assertSame('Inpsyde GmbH', $properties->author());
        static::assertSame('https://inpsyde.com/', $properties->authorUri());
        static::assertSame('7.2.5', $properties->requiresPhp());
    }
}
