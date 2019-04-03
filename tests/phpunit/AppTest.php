<?php # -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Brain\Monkey;
use Inpsyde\App\App;
use Inpsyde\App\Container;
use Inpsyde\App\Context;
use Inpsyde\App\Provider\ServiceProvider;
use Inpsyde\App\Provider;

class AppTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testCreatAndBootThrowsWhenPluginsAlreadyLoadedAndDebugIsTrue()
    {
        define('WP_DEBUG', true);
        do_action('plugins_loaded');

        $this->expectExceptionMessageRegExp('/too late/');
        App::createAndBoot(__NAMESPACE__);
    }

    public function testCreateAndBootReturnsWhenPluginsAlreadyLoadedAndDebugIsFalse()
    {
        do_action('plugins_loaded');

        Monkey\Actions\expectDone(App::ACTION_ERROR)
            ->once()
            ->with(\Mockery::type(\Throwable::class));

        $app = App::createAndBoot(__NAMESPACE__);

        static::assertInstanceOf(App::class, $app);
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateAndBootThrowsWhenAlreadyBootedAndDebugIsTrue()
    {
        define('WP_DEBUG', true);

        Monkey\Actions\expectAdded('plugins_loaded')->once();
        App::createAndBoot(__NAMESPACE__);

        $this->expectExceptionMessageRegExp('/already/');
        App::createAndBoot(__NAMESPACE__);
    }

    /**
     * @runInSeparateProcess
     */
    public function testMakeFailsIfNoCreatedAndBootedAppAndDebugIsTrue()
    {
        define('WP_DEBUG', true);
        $this->expectExceptionMessageRegExp('/found/');

        App::make('x');
    }

    public function testMakeReturnsNullIfNoCreatedAndBootedAppAndDebugIsFalse()
    {
        Monkey\Actions\expectDone(App::ACTION_ERROR)->once();

        static::assertNull(App::make('x'));
    }

    public function testMakeWithCreatedAndBootedApp()
    {
        Monkey\Functions\when('remove_all_actions')->justReturn();

        $provider = new class extends Provider\RegisteredOnly {
            public function register(Container $container): void
            {
                $container['it works'] = true;
            }
        };

        Monkey\Actions\expectDone(App::ACTION_ADD_PROVIDERS)
            ->once()
            ->whenHappen(function (App $app) use ($provider) {
                $app->addProvider($provider);
            });

        Monkey\Actions\expectAdded('plugins_loaded')->whenHappen(function(callable $boot) {
            $boot();
        });

        App::createAndBoot(__NAMESPACE__);

        static::assertTrue(App::make('it works'));
    }

    public function testMakeWithGivenApp()
    {
        $psr11 = new \Pimple\Psr11\Container(new \Pimple\Container(['it works' => true]));
        $app = App::new(__NAMESPACE__, $psr11);

        static::assertTrue(App::make('it works', $app));
    }

    /**
     * @runInSeparateProcess
     */
    public function testBootThrowsWhenInitFiredAndDebugIsTrue()
    {
        define('WP_DEBUG', true);
        do_action('init');

        $this->expectExceptionMessageRegExp('/too late/');

        App::new(__NAMESPACE__)->boot();
    }

    /**
     * @runInSeparateProcess
     */
    public function testBootThrowsWhenCalledMultipleTimesOnSameInstance()
    {
        define('WP_DEBUG', true);
        Monkey\Functions\when('remove_all_actions')->justReturn();

        $app = App::new(__NAMESPACE__);
        $app->boot();

        $this->expectExceptionMessageRegExp('/already/');

        $app->boot();
    }

    public function testBootNoProviders()
    {
        Monkey\Functions\when('remove_all_actions')->justReturn();

        $app = App::new(__NAMESPACE__);

        Monkey\Actions\expectDone(App::ACTION_ADD_PROVIDERS)
            ->once()
            ->with($app, false);

        Monkey\Actions\expectDone(App::ACTION_ADD_PROVIDERS)
            ->once()
            ->with($app, true);

        Monkey\Actions\expectDone(App::ACTION_REGISTERED)
            ->once()
            ->withNoArgs();

        Monkey\Actions\expectDone(App::ACTION_BOOTSTRAPPED)
            ->once()
            ->with(\Mockery::type(Container::class));

        $this->bootApp($app);
    }

    public function testBootWithImmediateProviders()
    {
        Monkey\Functions\when('remove_all_actions')->justReturn();

        Monkey\Actions\expectDone(App::ACTION_BOOTSTRAPPED)
            ->once()
            ->with(\Mockery::type(Container::class))
            ->whenHappen(function (Container $container) {
                static::assertTrue($container['Does']['the']['app']['works']['?']);
            });

        $app = App::new(__NAMESPACE__);
        foreach ($this->stubProviders() as $provider) {
            $app = $app->addProvider($provider);
        }

        $this->expectOutputString('ABCD');

        $this->bootApp($app);
    }

    public function testBootWithImmediateAndAddedProviders()
    {
        Monkey\Functions\when('remove_all_actions')->justReturn();

        $app = App::new(__NAMESPACE__);

        [$default, $deferred, $earlyNotDeferred, $earlyDeferred] = $this->stubProviders();

        $app->addProvider($earlyNotDeferred)->addProvider($earlyDeferred);

        Monkey\Actions\expectDone(App::ACTION_ADD_PROVIDERS)
            ->once()
            ->with($app, true)
            ->whenHappen(function (App $app) use ($default, $deferred) {
                $app->addProvider($default)->addProvider($deferred);
            });

        Monkey\Actions\expectDone(App::ACTION_BOOTSTRAPPED)
            ->once()
            ->with(\Mockery::type(Container::class))
            ->whenHappen(function (Container $container) {
                static::assertTrue($container['Does']['the']['app']['works']['?']);
            });

        $this->expectOutputString('ABCD');

        $this->bootApp($app);
    }

    public function testProvidersAreNotAddedIfNotProperContext()
    {
        $default = new class extends Provider\RegisteredOnly {
            public function register(Container $container): void
            {
                $container['x'] = function (): \ArrayObject {
                    return new \ArrayObject();
                };
            }
        };

        Monkey\Functions\when('remove_all_actions')->justReturn();

        $app = App::new(__NAMESPACE__)->addProvider($default, Context::AJAX);

        Monkey\Actions\expectDone(App::ACTION_BOOTSTRAPPED)
            ->once()
            ->with(\Mockery::type(Container::class))
            ->whenHappen(function (Container $container) {
                static::assertFalse($container->has('x'));
            });

        $this->bootApp($app);
    }

    /**
     * @runInSeparateProcess
     */
    public function testProvidersAreAddedIfProperContext()
    {
        Monkey\Functions\when('remove_all_actions')->justReturn();
        $this->mockAjaxContext();

        $provider = new class extends Provider\RegisteredOnly {
            public function register(Container $container): void
            {
                $container['it'] = function (): \ArrayObject {
                    return new \ArrayObject(['works' => true]);
                };
            }
        };

        $app = App::new(__NAMESPACE__)->addProvider($provider, Context::CRON, Context::AJAX);

        Monkey\Actions\expectDone(App::ACTION_BOOTSTRAPPED)
            ->once()
            ->with(\Mockery::type(Container::class))
            ->whenHappen(function (Container $container) {
                static::assertTrue($container['it']['works']);
            });

        $this->bootApp($app);
    }

    public function testCustomContainers()
    {
        Monkey\Functions\when('remove_all_actions')->justReturn();

        $c1 = new \Pimple\Psr11\Container(new \Pimple\Container(['it works' => true]));
        $c2 = new \Pimple\Psr11\Container(new \Pimple\Container(['it really works' => true]));

        $app = App::new(__NAMESPACE__, $c1, $c2);

        Monkey\Actions\expectDone(App::ACTION_BOOTSTRAPPED)
            ->once()
            ->with(\Mockery::type(Container::class))
            ->whenHappen(function (Container $container) {
                static::assertTrue($container['it works']);
                static::assertTrue($container['it really works']);
            });

        $this->bootApp($app);
    }

    /**
     * @param App $app
     */
    private function bootApp(App $app)
    {
        /** @var callable|null $onInit */
        $onInit = null;
        Monkey\Actions\expectAdded('init')
            ->once()
            ->whenHappen(function (callable $addedOnInit) use (&$onInit) {
                $onInit = $addedOnInit;
            });

        Monkey\Actions\expectDone('init')
            ->once()
            ->whenHappen(function () use (&$onInit) {
                $onInit();
            });

        $app->boot();

        do_action('init');
    }

    /**
     * @return ServiceProvider[]
     */
    private function stubProviders(): array
    {
        $earlyNotDeferred = new class extends Provider\EarlyBooted {

            public function register(Container $container): void
            {
                $container['works'] = function (): \ArrayObject {
                    return new \ArrayObject(['?' => true]);
                };
            }

            public function boot(Container $container): void
            {
                echo 'A';
            }
        };

        $earlyDeferred = new class extends Provider\RegisteredLaterEarlyBooted {

            public function register(Container $container): void
            {
                $container['app'] = function (Container $container): \ArrayObject {
                    return new \ArrayObject(['works' => $container['works']]);
                };
            }

            public function boot(Container $container): void
            {
                echo 'B';
            }
        };

        $default = new class extends Provider\Booted {

            public function register(Container $container): void
            {
                $container['the'] = function (Container $container): \ArrayObject {
                    return new \ArrayObject(['app' => $container['app']]);
                };
            }

            public function boot(Container $container): void
            {
                echo 'C';
            }
        };

        $deferred = new class extends Provider\RegisteredLater {

            public function register(Container $container): void
            {
                $container['Does'] = function (Container $container): \ArrayObject {
                    return new \ArrayObject(['the' => $container['the']]);
                };
            }

            public function boot(Container $container): void
            {
                echo 'D';
            }
        };

        return [$default, $deferred, $earlyNotDeferred, $earlyDeferred];
    }

    /**
     * @return void
     */
    private function mockAjaxContext(): void
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(true);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        Monkey\Functions\when('get_option')->justReturn(false);
        Monkey\Functions\when('set_url_scheme')->returnArg();
        Monkey\Functions\when('get_rest_url')->justReturn('https://example.com/wp-json');
        Monkey\Functions\when('add_query_arg')->justReturn('https://example.com');
    }
}
