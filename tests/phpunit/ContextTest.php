<?php # -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Brain\Monkey;
use Inpsyde\App\Context;

/**
 * @@runTestsInSeparateProcesses
 */
class ContextTest extends TestCase
{
    private $currentPath = '/';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\Functions\expect('add_query_arg')->with([])->andReturnUsing(function (){
            return $this->currentPath;
        });
    }

    protected function tearDown(): void
    {
        $this->currentPath = '/';
        unset($GLOBALS['pagenow']);
        parent::tearDown();
    }

    public function testNotCore()
    {
        $context = Context::create();

        static::assertFalse($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());
        static::assertFalse($context->is(Context::CORE));
    }

    public function testIsLogin()
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(true);

        $context = Context::create();

        static::assertTrue($context->isCore());
        static::assertTrue($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());

        static::assertTrue($context->is(Context::LOGIN));
        static::assertTrue($context->is(Context::LOGIN, Context::REST));
        static::assertFalse($context->is(Context::FRONTOFFICE, Context::REST));
        static::assertTrue($context->is(Context::FRONTOFFICE, Context::REST, Context::CORE));
    }

    public function testIsLoginLate()
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        /** @var callable|null $onLoginInit */
        $onLoginInit = null;
        Monkey\Actions\expectAdded('login_init')
            ->whenHappen(function (callable $callback) use (&$onLoginInit) {
                $onLoginInit = $callback;
            });

        $context = Context::create();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        $onLoginInit();
        static::assertTrue($context->isLogin());
    }

    public function testIsRest()
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(true);
        $this->mockIsLoginRequest(false);

        $context = Context::create();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertTrue($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());

        static::assertTrue($context->is(Context::REST));
        static::assertTrue($context->is(Context::REST, Context::LOGIN));
        static::assertFalse($context->is(Context::FRONTOFFICE, Context::LOGIN));
        static::assertTrue($context->is(Context::FRONTOFFICE, Context::LOGIN, Context::CORE));
    }

    public function testIsRestLate()
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        /** @var callable|null $onRestInit */
        $onRestInit = null;
        Monkey\Actions\expectAdded('rest_api_init')
            ->whenHappen(function (callable $callback) use (&$onRestInit) {
                $onRestInit = $callback;
            });

        $context = Context::create();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isRest());
        $onRestInit();
        static::assertTrue($context->isRest());
    }

    public function testIsCron()
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(true);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        $context = Context::create();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertTrue($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());

        static::assertTrue($context->is(Context::CRON));
        static::assertTrue($context->is(Context::LOGIN, Context::CRON));
        static::assertFalse($context->is(Context::FRONTOFFICE, Context::LOGIN));
        static::assertTrue($context->is(Context::FRONTOFFICE, Context::LOGIN, Context::CORE));
    }

    public function testIsFrontoffice()
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        $context = Context::create();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertTrue($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());

        static::assertTrue($context->is(Context::FRONTOFFICE));
        static::assertTrue($context->is(Context::LOGIN, Context::FRONTOFFICE));
        static::assertFalse($context->is(Context::CRON, Context::LOGIN));
        static::assertTrue($context->is(Context::CRON, Context::LOGIN, Context::CORE));
    }

    public function testIsBackoffice()
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(true);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        $context = Context::create();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertTrue($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());

        static::assertTrue($context->is(Context::BACKOFFICE));
        static::assertTrue($context->is(Context::LOGIN, Context::BACKOFFICE));
        static::assertFalse($context->is(Context::CRON, Context::LOGIN));
        static::assertTrue($context->is(Context::CRON, Context::LOGIN, Context::CORE));
    }

    public function testIsAjax()
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(true);
        Monkey\Functions\when('is_admin')->justReturn(true);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        $context = Context::create();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertTrue($context->isAjax());
        static::assertFalse($context->isWpCli());

        static::assertTrue($context->is(Context::AJAX));
        static::assertTrue($context->is(Context::AJAX, Context::BACKOFFICE));
        static::assertFalse($context->is(Context::CRON, Context::BACKOFFICE));
        static::assertTrue($context->is(Context::CRON, Context::BACKOFFICE, Context::CORE));
    }

    public function testIsCli()
    {
        define('ABSPATH', __DIR__);
        define('WP_CLI', 2);

        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        $context = Context::create();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertTrue($context->isWpCli());

        static::assertTrue($context->is(Context::CLI));
        static::assertTrue($context->is(Context::FRONTOFFICE, Context::CLI));
        static::assertFalse($context->is(Context::FRONTOFFICE, Context::CRON));
        static::assertTrue($context->is(Context::CRON, Context::BACKOFFICE, Context::CORE));
    }

    public function testJsonSerialize()
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(true);

        $context = Context::create();
        $decoded = json_decode(json_encode($context), true);

        static::assertTrue($decoded[Context::CORE]);
        static::assertTrue($decoded[Context::LOGIN]);
        static::assertFalse($decoded[Context::REST]);
        static::assertFalse($decoded[Context::CRON]);
        static::assertFalse($decoded[Context::FRONTOFFICE]);
        static::assertFalse($decoded[Context::BACKOFFICE]);
        static::assertFalse($decoded[Context::AJAX]);
        static::assertFalse($decoded[Context::CLI]);
    }

    /**
     * @param bool $is
     */
    private function mockIsRestRequest(bool $is)
    {
        Monkey\Functions\expect('get_option')->with('permalink_structure')->andReturn(false);
        Monkey\Functions\stubs(['set_url_scheme']);
        Monkey\Functions\when('get_rest_url')->justReturn('https://example.com/wp-json');
        $is and $this->currentPath = '/wp-json/foo';
    }

    /**
     * @param bool $is
     */
    private function mockIsLoginRequest(bool $is)
    {
        $is and $this->currentPath = '/wp-login.php';
        Monkey\Functions\when('wp_login_url')->justReturn('https://example.com/wp-login.php');
        Monkey\Functions\when('home_url')->alias(static function ($path = '') {
            return 'https://example.com/' . ltrim($path, '/');
        });
    }
}
