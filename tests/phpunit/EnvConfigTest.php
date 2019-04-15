<?php # -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Inpsyde\App\EnvConfig;

class EnvConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_ENV['VIP_GO_ENV']);
        unset($_ENV['TEST_ME']);
        unset($_SERVER['TEST_ME']);
        unset($_SERVER['HTTP_TEST_ME']);
    }

    public function testEnvFromEnvVar()
    {
        $_ENV['VIP_GO_ENV'] = 'preprod';

        $env = new EnvConfig();

        static::assertSame(EnvConfig::STAGING, $env->env());
        static::assertTrue($env->envIs(EnvConfig::STAGING));
    }

    public function testEnvFromEnvVarFiltered()
    {
        $_ENV['VIP_GO_ENV'] = 'preprod';

        Filters\expectApplied(EnvConfig::FILTER_ENV_NAME)
            ->once()
            ->with(EnvConfig::STAGING)
            ->andReturn('dev');

        $env = new EnvConfig();

        static::assertSame(EnvConfig::DEVELOPMENT, $env->env());
        static::assertTrue($env->envIs(EnvConfig::DEVELOPMENT));
    }

    /**
     * @runInSeparateProcess
     */
    public function testEnvFromConstantFiltered()
    {
        define('VIP_GO_ENV', 'preprod');

        Filters\expectApplied(EnvConfig::FILTER_ENV_NAME)
            ->once()
            ->with(EnvConfig::STAGING)
            ->andReturn('dev');

        $env = new EnvConfig();

        static::assertSame(EnvConfig::DEVELOPMENT, $env->env());
        static::assertTrue($env->envIs(EnvConfig::DEVELOPMENT));
    }

    /**
     * @runInSeparateProcess
     */
    public function testEnvFromWpEngine()
    {
        Functions\when('is_wpe')->justReturn(null);

        $env = new EnvConfig();

        static::assertSame(EnvConfig::STAGING, $env->env());
        static::assertTrue($env->envIs(EnvConfig::STAGING));
    }

    public function testEnvDefaultProduction()
    {
        $env = new EnvConfig();

        static::assertSame(EnvConfig::PRODUCTION, $env->env());
        static::assertTrue($env->envIs(EnvConfig::PRODUCTION));
    }

    /**
     * @runInSeparateProcess
     */
    public function testEnvDefaultFromWpDebug()
    {
        define('WP_DEBUG', true);

        $env = new EnvConfig();

        static::assertSame(EnvConfig::DEVELOPMENT, $env->env());
        static::assertTrue($env->envIs(EnvConfig::DEVELOPMENT));
    }

    public function testEnvDefaultFiltered()
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
     * @runInSeparateProcess
     */
    public function testGetFromNamespacedConstant()
    {
        define(__NAMESPACE__ . '\\' . 'TEST_ME', 'Yes!');
        define('Meh\\' . 'TEST_ME_2', 'Yes (2)');

        $env = new EnvConfig('Meh', __NAMESPACE__);

        static::assertSame('Yes!', $env->get('TEST_ME'));
        static::assertSame('Yes (2)', $env->get('TEST_ME_2'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetFromGlobalConstant()
    {
        define('TEST_ME', 'Yes!!');

        $env = new EnvConfig();

        static::assertSame('Yes!!', $env->get('TEST_ME'));
    }

    public function testGetFromEnv()
    {
        $_ENV['TEST_ME'] = 'Yes!!!';

        $env = new EnvConfig();

        static::assertSame('Yes!!!', $env->get('TEST_ME'));
    }

    public function testGetFromServer()
    {
        $_SERVER['TEST_ME'] = 'Yes!!!!';

        $env = new EnvConfig();

        static::assertSame('Yes!!!!', $env->get('TEST_ME'));
    }

    public function testGetFromServerDoesNotWorkForHeaders()
    {
        $_SERVER['HTTP_TEST_ME'] = 'No';

        $env = new EnvConfig();

        static::assertSame('X', $env->get('TEST_ME', 'X'));
    }
}
