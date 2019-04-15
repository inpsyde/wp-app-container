<?php # -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Inpsyde\App\App;
use Inpsyde\App\AppStatus;

/**
 * @runTestsInSeparateProcesses
 */
class AppStatusTest extends TestCase
{
    public function testChangingLastStepHookFailsIfNotIdle()
    {
        $status = AppStatus::new();
        $status->next(App::new());

        $this->expectException(\DomainException::class);

        $status->lastStepOn('after_setup_theme');
    }

    public function testInitializeFailsIfLastStepHookAlreadyDone()
    {
        do_action('init');

        $status = AppStatus::new();

        $this->expectException(\DomainException::class);

        $status->next(App::new());
    }

    public function testInitializeFailsIfCustomLastStepHookAlreadyDone()
    {
        $status = AppStatus::new()->lastStepOn('after_setup_theme');

        do_action('after_setup_theme');

        $this->expectException(\DomainException::class);

        $status->next(App::new());
    }

    public function testInitializeEarlyAddsTwoMoreBootActions()
    {
        $app = App::new();

        Actions\expectAdded('plugins_loaded')->once()->with([$app, 'boot'], \Mockery::type('int'));
        Actions\expectAdded('init')->once()->with([$app, 'boot'], \Mockery::type('int'));

        $status = AppStatus::new()->next($app);

        static::assertTrue($status->isEarly());
        static::assertTrue($status->isRegistering());
    }

    public function testInitializeEarlyAddsTwoMoreBootActionsLastCustomized()
    {
        $app = App::new();

        $status = $status = AppStatus::new()->lastStepOn('foo');

        Actions\expectAdded('plugins_loaded')->once()->with([$app, 'boot'], \Mockery::type('int'));
        Actions\expectAdded('foo')->once()->with([$app, 'boot'], \Mockery::type('int'));

        $status->next($app);

        static::assertTrue($status->isEarly());
        static::assertTrue($status->isRegistering());
    }

    public function testInitializeAfterPluginsLoadedAddsOneMoreBootAction()
    {
        $app = App::new();

        do_action('plugins_loaded');

        Actions\expectAdded('init')->once()->with([$app, 'boot'], \Mockery::type('int'));

        $status = AppStatus::new()->next($app);

        static::assertTrue($status->isPluginsStep());
        static::assertTrue($status->isRegistering());
    }

    public function testInitializeDuringPluginsLoadedAddsOneMoreBootAction()
    {
        $app = App::new();

        Actions\expectDone('plugins_loaded')->whenHappen(function () use ($app) {

            Actions\expectAdded('init')->once()->with([$app, 'boot'], \Mockery::type('int'));

            $status = AppStatus::new()->next($app);

            static::assertTrue($status->isPluginsStep());
            static::assertTrue($status->isRegistering());
        });

        do_action('plugins_loaded');
    }

    public function testInitializeDuringInitAddsNoMoreBootAction()
    {
        $app = App::new();

        Functions\expect('doing_action')->atLeast()->once()->with('init')->andReturn(true);
        Functions\expect('did_action')->atLeast()->once()->with('init')->andReturn(true);
        Functions\expect('did_action')->atLeast()->once()->with('plugins_loaded')->andReturn(true);

        Actions\expectAdded('plugins_loaded')->never();
        Actions\expectAdded('init')->never();

        $status = AppStatus::new()->next($app);

        static::assertTrue($status->isThemesStep());
        static::assertTrue($status->isRegistering());
    }

    public function testInitializeDuringCustomLastStepAddsNoMoreBootAction()
    {
        $app = App::new();

        Functions\expect('doing_action')
            ->atLeast()
            ->once()
            ->with('after_setup_theme')
            ->andReturn(true);

        Functions\expect('did_action')
            ->atLeast()
            ->once()
            ->with('after_setup_theme')
            ->andReturn(true);

        Functions\expect('did_action')
            ->atLeast()
            ->once()
            ->with('plugins_loaded')
            ->andReturn(true);

        Actions\expectAdded('plugins_loaded')->never();
        Actions\expectAdded('after_setup_theme')->never();
        Actions\expectAdded('init')->never();

        $status = AppStatus::new()->lastStepOn('after_setup_theme')->next($app);

        static::assertTrue($status->isThemesStep());
        static::assertTrue($status->isRegistering());
    }

    public function testIsAnyOf()
    {
        $app = App::new();
        $status = $app->status();

        static::assertTrue($status->isAnyOf(AppStatus::IDLE));
        static::assertTrue($status->isAnyOf(AppStatus::BOOTED_EARLY, AppStatus::IDLE));
        static::assertFalse($status->isAnyOf(AppStatus::BOOTED_EARLY, AppStatus::BOOTED_THEMES));
    }

    public function testFlowStartingEarly()
    {
        $app = App::new();
        $status = $app->status();

        $this->checkAllIssers($status, 'isIdle');
        $status->next($app);
        $this->checkAllIssers($status, 'isEarly', 'isRegistering');
        $status->next($app);
        $this->checkAllIssers($status, 'isEarly', 'isBooting');
        $status->next($app);
        $this->checkAllIssers($status, 'isEarly', 'isBooted');
        $status->next($app);
        $this->checkAllIssers($status, 'isPluginsStep', 'isRegistering');
        $status->next($app);
        $this->checkAllIssers($status, 'isPluginsStep', 'isBooting');
        $status->next($app);
        $this->checkAllIssers($status, 'isPluginsStep', 'isBooted');
        $status->next($app);
        $this->checkAllIssers($status, 'isThemesStep', 'isRegistering');
        $status->next($app);
        $this->checkAllIssers($status, 'isThemesStep', 'isBooting');
        $status->next($app);
        $this->checkAllIssers($status, 'isThemesStep', 'isBooted', 'isDone');

        $this->expectException(\DomainException::class);

        $status->next($app);
    }

    public function testFlowStartingAfterPluginsLoaded()
    {
        $app = App::new();
        $status = $app->status();

        do_action('plugins_loaded');

        $this->checkAllIssers($status, 'isIdle');
        $status->next($app);
        $this->checkAllIssers($status, 'isPluginsStep', 'isRegistering');
        $status->next($app);
        $this->checkAllIssers($status, 'isPluginsStep', 'isBooting');
        $status->next($app);
        $this->checkAllIssers($status, 'isPluginsStep', 'isBooted');
        $status->next($app);
        $this->checkAllIssers($status, 'isThemesStep', 'isRegistering');
        $status->next($app);
        $this->checkAllIssers($status, 'isThemesStep', 'isBooting');
        $status->next($app);
        $this->checkAllIssers($status, 'isThemesStep', 'isBooted', 'isDone');

        $this->expectException(\DomainException::class);

        $status->next($app);
    }

    public function testFlowStartingOnInit()
    {
        $app = App::new();
        $status = $app->status();

        Functions\expect('doing_action')->atLeast()->once()->with('init')->andReturn(true);
        Functions\expect('did_action')->atLeast()->once()->with('init')->andReturn(true);
        Functions\expect('did_action')->atLeast()->once()->with('plugins_loaded')->andReturn(true);

        $this->checkAllIssers($status, 'isIdle');
        $status->next($app);
        $this->checkAllIssers($status, 'isThemesStep', 'isRegistering');
        $status->next($app);
        $this->checkAllIssers($status, 'isThemesStep', 'isBooting');
        $status->next($app);
        $this->checkAllIssers($status, 'isThemesStep', 'isBooted', 'isDone');

        $this->expectException(\DomainException::class);

        $status->next($app);
    }

    /**
     * @param AppStatus $status
     * @param string ...$true
     */
    private function checkAllIssers(AppStatus $status, string ...$true)
    {
        $methods = [
            'isIdle',
            'isEarly',
            'isPluginsStep',
            'isThemesStep',
            'isRegistering',
            'isBooting',
            'isBooted',
            'isDone',
        ];

        foreach ($methods as $method) {
            in_array($method, $true, true)
                ? static::assertTrue($status->{$method}())
                : static::assertFalse($status->{$method}());
        }
    }
}
