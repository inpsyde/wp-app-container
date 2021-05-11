<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Inpsyde\App\EnvConfig;
use Inpsyde\App\Location\GenericLocations;
use Inpsyde\App\Location\VipLocations;
use Inpsyde\App\Location\WpEngineLocations;

class EnvConfigTest extends TestCase
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
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_ENV['VIP_GO_ENV']);
        unset($_ENV['TEST_ME']);
        unset($_SERVER['TEST_ME']);
        unset($_SERVER['HTTP_TEST_ME']);
    }

    /**
     * @test
     */
    public function testEnvFromEnvVar(): void
    {
        $_ENV['VIP_GO_ENV'] = 'preprod';

        $env = new EnvConfig();

        static::assertSame(EnvConfig::STAGING, $env->env());
        static::assertTrue($env->envIs(EnvConfig::STAGING));
    }

    /**
     * @test
     */
    public function testEnvFromEnvVarIsNotFiltered(): void
    {
        $_ENV['VIP_GO_ENV'] = 'preprod';

        Filters\expectApplied(EnvConfig::FILTER_ENV_NAME)->never();

        $env = new EnvConfig();

        static::assertSame(EnvConfig::STAGING, $env->env());
        static::assertTrue($env->envIs(EnvConfig::STAGING));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testEnvFromConstantInNotFiltered(): void
    {
        define('WP_ENVIRONMENT_TYPE', 'test');

        Filters\expectApplied(EnvConfig::FILTER_ENV_NAME)->never();

        $env = new EnvConfig();

        static::assertSame(EnvConfig::STAGING, $env->env());
        static::assertTrue($env->envIs(EnvConfig::STAGING));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testEnvFromWpEngine(): void
    {
        Functions\when('is_wpe')->justReturn(null);

        $env = new EnvConfig();

        static::assertSame(EnvConfig::STAGING, $env->env());
        static::assertTrue($env->envIs(EnvConfig::STAGING));
    }

    /**
     * @test
     */
    public function testEnvDefaultProduction(): void
    {
        $env = new EnvConfig();

        static::assertSame(EnvConfig::PRODUCTION, $env->env());
        static::assertTrue($env->envIs(EnvConfig::PRODUCTION));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testEnvDefaultFromWpDebug(): void
    {
        define('WP_DEBUG', true);

        $env = new EnvConfig();

        static::assertSame(EnvConfig::DEVELOPMENT, $env->env());
        static::assertTrue($env->envIs(EnvConfig::DEVELOPMENT));
    }

    /**
     * @test
     */
    public function testEnvDefaultFiltered(): void
    {
        $env = new EnvConfig();

        Filters\expectApplied(EnvConfig::FILTER_ENV_NAME)
            ->once()
            ->with(EnvConfig::PRODUCTION)
            ->andReturn('pre-prod');

        static::assertSame(EnvConfig::STAGING, $env->env());
        static::assertTrue($env->envIs(EnvConfig::STAGING));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testHostingOther(): void
    {
        define('ABSPATH', __DIR__);
        define('WP_CONTENT_DIR', __DIR__);

        $env = new EnvConfig();

        static::assertSame(EnvConfig::HOSTING_OTHER, $env->hosting());
        static::assertTrue($env->hostingIs(EnvConfig::HOSTING_OTHER));
        static::assertInstanceOf(GenericLocations::class, $env->locations());
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testHostingVip(): void
    {
        define('ABSPATH', __DIR__);
        define('WP_CONTENT_DIR', __DIR__);
        define('VIP_GO_ENV', 'prod');

        $env = new EnvConfig();

        static::assertSame(EnvConfig::HOSTING_VIP, $env->hosting());
        static::assertTrue($env->hostingIs(EnvConfig::HOSTING_VIP));
        static::assertInstanceOf(VipLocations::class, $env->locations());
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testHostingWpe(): void
    {
        define('ABSPATH', __DIR__);
        define('WP_CONTENT_DIR', __DIR__);
        define('HOSTING', EnvConfig::HOSTING_WPE);

        $env = new EnvConfig();

        static::assertSame(EnvConfig::HOSTING_WPE, $env->hosting());
        static::assertTrue($env->hostingIs(EnvConfig::HOSTING_WPE));
        static::assertInstanceOf(WpEngineLocations::class, $env->locations());
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testHostingSpaces(): void
    {
        define('ABSPATH', __DIR__);
        define('WP_CONTENT_DIR', __DIR__);
        define('SPACES_SPACE_ID', '123456789');

        $env = new EnvConfig();

        static::assertSame(EnvConfig::HOSTING_SPACES, $env->hosting());
        static::assertTrue($env->hostingIs(EnvConfig::HOSTING_SPACES));
        static::assertInstanceOf(GenericLocations::class, $env->locations());
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testGetFromNamespacedConstant(): void
    {
        define(__NAMESPACE__ . '\\' . 'TEST_ME', 'Yes!');
        define('Meh\\' . 'TEST_ME_2', 'Yes (2)');

        $env = new EnvConfig('Meh', __NAMESPACE__);

        static::assertSame('Yes!', $env->get('TEST_ME'));
        static::assertSame('Yes (2)', $env->get('TEST_ME_2'));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testGetFromGlobalConstant(): void
    {
        define('TEST_ME', 'Yes!!');

        $env = new EnvConfig();

        static::assertSame('Yes!!', $env->get('TEST_ME'));
    }

    /**
     * @test
     */
    public function testGetFromEnv(): void
    {
        $_ENV['TEST_ME'] = 'Yes!!!';

        $env = new EnvConfig();

        static::assertSame('Yes!!!', $env->get('TEST_ME'));
    }

    /**
     * @test
     */
    public function testGetFromServer(): void
    {
        $_SERVER['TEST_ME'] = 'Yes!!!!';

        $env = new EnvConfig();

        static::assertSame('Yes!!!!', $env->get('TEST_ME'));
    }

    /**
     * @test
     */
    public function testGetFromServerDoesNotWorkForHeaders(): void
    {
        $_SERVER['HTTP_TEST_ME'] = 'No';

        $env = new EnvConfig();

        static::assertSame('X', $env->get('TEST_ME', 'X'));
    }
}
