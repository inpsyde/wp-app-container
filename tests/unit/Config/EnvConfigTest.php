<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Config;

use Inpsyde\App\Config\EnvConfig;
use Inpsyde\App\Tests\TestCase;

class EnvConfigTest extends TestCase
{
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
