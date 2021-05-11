<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Inpsyde\App\App;
use Inpsyde\App\AppStatus;
use Inpsyde\App\Container;

class AppStatusTest extends TestCase
{
    /**
     * @test
     */
    public function testChangingLastStepHookFailsIfNotIdle(): void
    {
        $status = AppStatus::new();
        $status->next($this->factoryApp());

        $this->expectException(\DomainException::class);

        $status->lastStepOn('after_setup_theme');
    }

    /**
     * @test
     */
    public function testInitializeFailsIfLastStepHookAlreadyDone(): void
    {
        do_action('init');

        $status = AppStatus::new();

        $this->expectException(\DomainException::class);

        $status->next($this->factoryApp());
    }

    /**
     * @test
     */
    public function testInitializeFailsIfCustomLastStepHookAlreadyDone(): void
    {
        $status = AppStatus::new()->lastStepOn('after_setup_theme');

        do_action('after_setup_theme');

        $this->expectException(\DomainException::class);

        $status->next($this->factoryApp());
    }

    /**
     * @test
     */
    public function testInitializeEarlyAddsTwoMoreBootActions(): void
    {
        $app = $this->factoryApp();

        Actions\expectAdded('plugins_loaded')->once()->with([$app, 'boot'], \Mockery::type('int'));
        Actions\expectAdded('init')->once()->with([$app, 'boot'], \Mockery::type('int'));

        $status = AppStatus::new()->next($app);

        static::assertTrue($status->isEarly());
        static::assertTrue($status->isRegistering());
    }

    /**
     * @test
     */
    public function testInitializeEarlyAddsTwoMoreBootActionsLastCustomized(): void
    {
        $app = $this->factoryApp();

        $status = AppStatus::new()->lastStepOn('foo');

        Actions\expectAdded('plugins_loaded')->once()->with([$app, 'boot'], \Mockery::type('int'));
        Actions\expectAdded('foo')->once()->with([$app, 'boot'], \Mockery::type('int'));

        $status->next($app);

        static::assertTrue($status->isEarly());
        static::assertTrue($status->isRegistering());
    }

    /**
     * @test
     */
    public function testInitializeAfterPluginsLoadedAddsOneMoreBootAction(): void
    {
        $app = $this->factoryApp();

        do_action('plugins_loaded');

        Actions\expectAdded('init')->once()->with([$app, 'boot'], \Mockery::type('int'));

        $status = AppStatus::new()->next($app);

        static::assertTrue($status->isPluginsStep());
        static::assertTrue($status->isRegistering());
    }

    /**
     * @return void
     */
    public function testInitializeDuringPluginsLoadedAddsOneMoreBootAction(): void
    {
        $app = $this->factoryApp();

        Actions\expectDone('plugins_loaded')->whenHappen(static function () use ($app): void {

            Actions\expectAdded('init')->once()->with([$app, 'boot'], \Mockery::type('int'));

            $status = AppStatus::new()->next($app);

            static::assertTrue($status->isPluginsStep());
            static::assertTrue($status->isRegistering());
        });

        do_action('plugins_loaded');
    }

    /**
     * @test
     */
    public function testInitializeDuringInitAddsNoMoreBootAction(): void
    {
        $app = $this->factoryApp();

        Functions\expect('doing_action')->atLeast()->once()->with('init')->andReturn(true);
        Functions\expect('did_action')->atLeast()->once()->with('init')->andReturn(true);
        Functions\expect('did_action')->atLeast()->once()->with('plugins_loaded')->andReturn(true);

        Actions\expectAdded('plugins_loaded')->never();
        Actions\expectAdded('init')->never();

        $status = AppStatus::new()->next($app);

        static::assertTrue($status->isThemesStep());
        static::assertTrue($status->isRegistering());
    }

    /**
     * @test
     */
    public function testInitializeDuringCustomLastStepAddsNoMoreBootAction(): void
    {
        $app = $this->factoryApp();

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

    /**
     * @test
     */
    public function testIsAnyOf(): void
    {
        $app = $this->factoryApp();
        $status = $app->status();

        static::assertTrue($status->isAnyOf(AppStatus::IDLE));
        static::assertTrue($status->isAnyOf(AppStatus::BOOTED_EARLY, AppStatus::IDLE));
        static::assertFalse($status->isAnyOf(AppStatus::BOOTED_EARLY, AppStatus::BOOTED_THEMES));
    }

    /**
     * @test
     */
    public function testFlowStartingEarly(): void
    {
        $app = $this->factoryApp();
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

    /**
     * @test
     */
    public function testFlowStartingAfterPluginsLoaded(): void
    {
        $app = $this->factoryApp();
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

    /**
     * @test
     */
    public function testFlowStartingOnInit(): void
    {
        $app = $this->factoryApp();
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
    private function checkAllIssers(AppStatus $status, string ...$true): void
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

    /**
     * @return App
     */
    private function factoryApp(): App
    {
        return App::new(new Container(null, $this->factoryContext()));
    }
}
