<?php # -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Brain\Monkey;
use Inpsyde\App\Context;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as FrameworkTestCase;

abstract class TestCase extends FrameworkTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * To be used in separate processes.
     */
    protected function mockContext(?string $case = null, bool $withCli = false): void
    {
        $cases = [
            Context::AJAX,
            Context::BACKOFFICE,
            Context::CRON,
            Context::FRONTOFFICE,
            Context::INSTALLING,
            Context::LOGIN,
            Context::REST,
            Context::XML_RPC,
        ];

        if ($withCli) {
            define('WP_CLI', true);
        }

        $target = $case ? ($cases[$case] ?? null) : null;
        if ($target !== Context::INSTALLING) {
            define('ABSPATH', __DIR__);
        }

        $admin = in_array($target, [Context::AJAX, Context::BACKOFFICE], true);
        Monkey\Functions\when('is_admin')->justReturn($admin);
        Monkey\Functions\when('wp_doing_ajax')->justReturn($target === Context::AJAX);
        define('REST_REQUEST', $target === Context::REST);
        Monkey\Functions\when('wp_doing_cron')->justReturn($target === Context::CRON);
        Monkey\Functions\when('home_url')->justReturn('https://example.com/');
        Monkey\Functions\expect('add_query_arg')->with([])->andReturn('/');
        Monkey\Functions\when('wp_login_url')->justReturn('https://example.com/wp-login.php');
    }
}
