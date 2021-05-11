<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Inpsyde\App\App;
use Inpsyde\App\AppStatus;
use Inpsyde\App\Container;
use Inpsyde\App\EnvConfig;
use Inpsyde\App\Provider\ConfigurableProvider;
use Inpsyde\App\Provider\Package;
use Inpsyde\App\Provider\ServiceProvider;
use Inpsyde\App\Provider\ServiceProviders;
use Psr\Container\NotFoundExceptionInterface;

class AppTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('remove_all_actions')->justReturn();

        Actions\expectDone(App::ACTION_ERROR)
            ->with(\Mockery::type(\Throwable::class))
            ->zeroOrMoreTimes()
            ->whenHappen(static function (\Throwable $throwable) {
                throw $throwable;
            });
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testMakeFailsIfNoAppCreated(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/no valid app/i');

        App::make('foo');
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testMakeFailsIfAppIdle(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/uninitialised/i');

        $container = new Container(new EnvConfig());
        App::new($container);

        App::make('foo');
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testMakeFailsIfNothingInTheContainer(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $container = new Container(new EnvConfig(), $this->factoryContext());
        App::new($container)->boot();

        App::make('foo');
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testMakeGetContainer(): void
    {
        $psr11 = new \Pimple\Psr11\Container(new \Pimple\Container(['foo' => 'bar']));

        $container = new Container(new EnvConfig(), $this->factoryContext(), $psr11);
        App::new($container)->boot();

        static::assertSame('bar', App::make('foo'));
    }

    /**
     * @test
     */
    public function testDebugInfo(): void
    {
        $app = App::new()->enableDebug();
        $app->addProvider(self::factoryProvider('p1'))->addProvider(self::factoryProvider('p2'));

        $info = $app->debugInfo();

        static::assertSame($info['status'], (string)AppStatus::new());
        static::assertIsArray($info['providers']);
        static::assertSame(['p1', 'p2'], array_keys($info['providers']));
    }

    /**
     * @test
     */
    public function testBootFlow(): void
    {
        /** @var callable|null $onPluginsLoaded */
        $onPluginsLoaded = null;
        /** @var callable|null $onAfterSetupTheme */
        $onAfterSetupTheme = null;

        Actions\expectAdded('plugins_loaded')
            ->once()
            ->whenHappen(static function (callable $callable) use (&$onPluginsLoaded) {
                $onPluginsLoaded = $callable;
            });

        Actions\expectAdded('after_setup_theme')
            ->once()
            ->whenHappen(static function (callable $callable) use (&$onAfterSetupTheme) {
                $onAfterSetupTheme = $callable;
            });

        Actions\expectDone(App::ACTION_REGISTERED)->once()->withNoArgs();
        Actions\expectDone(App::ACTION_BOOTED)->once()->with(\Mockery::type(Container::class));

        $early = new ConfigurableProvider(
            'p-early',
            static function (Container $container): bool {
                $container->addService('a', static function (): object {
                    return AppTest::factoryStringObject('A-');
                });
                return true;
            }
        );

        $plugins = new class implements Package {

            public function providers(): ServiceProviders
            {
                return ServiceProviders::new()
                    ->add(
                        new ConfigurableProvider(
                            'p-plugins-1',
                            '__return_true',
                            static function (Container $container): bool {
                                echo
                                    $container->get('a')
                                    . $container->get('b')
                                    . $container->get('c');
                                return true;
                            },
                            ConfigurableProvider::REGISTER_LATER
                        )
                    )
                    ->add(
                        new ConfigurableProvider(
                            'p-plugins-2',
                            static function (Container $container): bool {
                                $container->addService('c', static function (): object {
                                    return AppTest::factoryStringObject('C!');
                                });

                                return true;
                            }
                        )
                    );
            }
        };

        $themes = new ConfigurableProvider(
            'p-themes',
            static function (Container $container): bool {
                $container->addService('b', static function (): object {
                    return AppTest::factoryStringObject('B-');
                });

                return true;
            }
        );

        $count = 0;
        Actions\expectDone(App::ACTION_ADD_PROVIDERS)
            ->times(3)
            ->whenHappen(
                static function (
                    App $app,
                    AppStatus $status
                ) use (
                    $early,
                    $plugins,
                    $themes,
                    &$count
                ): void {
                    $count++;
                    switch ($count) {
                        case 1:
                            static::assertTrue($status->isEarly());
                            $app->addProvider($early);
                            break;
                        case 2:
                            static::assertTrue($status->isPluginsStep());
                            $app->addPackage($plugins);
                            break;
                        case 3:
                            static::assertTrue($status->isThemesStep());
                            $app->addProvider($themes);
                            break;
                    }
                }
            );

        $this->expectOutputString('A-B-C!');

        $container = new Container(null, $this->factoryContext());
        App::new($container)->runLastBootAt('after_setup_theme')->boot();

        $onPluginsLoaded();
        $onAfterSetupTheme();
    }

    /**
     * @test
     */
    public function testNestedAddProvider(): void
    {
        $pro1 = self::factoryProvider('p1', static function (Container $container): bool {
            $container->addService('a', static function (): object {
                return AppTest::factoryStringObject('A-');
            });

            return true;
        });

        $pro2 = self::factoryProvider('p2', static function (Container $container): bool {
            $container->addService('b', static function (): object {
                return AppTest::factoryStringObject('B-');
            });

            return true;
        });

        $pro3 = self::factoryProvider('p3', static function (Container $container): bool {
            $container->addService('c', static function (): object {
                return AppTest::factoryStringObject('C!');
            });

            return true;
        });

        $app = App::new(new Container(null, $this->factoryContext()));

        Actions\expectDone(App::ACTION_ADDED_PROVIDER)
            ->times(3)
            ->with(\Mockery::type('string'), $app)
            ->whenHappen(static function (string $id, App $app) use ($pro2): void {
                if ($id === 'p1') {
                    $app->addProvider($pro2);
                }
            });

        Actions\expectDone(App::ACTION_REGISTERED_PROVIDER)
            ->times(3)
            ->with(\Mockery::type('string'), $app)
            ->whenHappen(static function (string $id, App $app) use ($pro3): void {
                if ($id === 'p2') {
                    $app->addProvider($pro3);
                }
            });

        Actions\expectDone(App::ACTION_BOOTED)
            ->once()
            ->with(\Mockery::type(Container::class))
            ->whenHappen(static function (Container $container): void {
                echo $container->get('a') . $container->get('b') . $container->get('c');
            });

        Actions\expectDone('init')
            ->once()
            ->whenHappen(static function () use ($app, $pro1): void {
                $app->addProvider($pro1)->boot();
            });

        $this->expectOutputString('A-B-C!');

        do_action('plugins_loaded');
        do_action('init');
    }

    /**
     * @test
     */
    public function testCallingBootFromNestedAddProviderFails(): void
    {
        $app = App::new(new Container(null, $this->factoryContext()));

        Actions\expectDone(App::ACTION_ADDED_PROVIDER)
            ->twice()
            ->with(\Mockery::type('string'), $app)
            ->whenHappen(static function (string $id, App $app): void {
                if ($id === 'p1') {
                    $app->addProvider(self::factoryProvider('p2'));
                    $app->boot();
                }
            });

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/already booting/i');

        $app->addProvider(self::factoryProvider('p1'));
    }

    /**
     * @test
     */
    public function testDependantProviderOnLastBootIsBooted(): void
    {
        /** @var callable|null $onPluginsLoaded */
        $onPluginsLoaded = null;

        /** @var callable|null $onInit */
        $onInit = null;

        $dependency = new ConfigurableProvider(
            'dependency',
            '__return_true',
            null,
            ConfigurableProvider::REGISTER_LATER
        );

        $dependant = new ConfigurableProvider(
            'dependant',
            static function (): bool {
                echo "I have been registered!\n";

                return true;
            },
            static function (): bool {
                echo "I have been booted!";

                return true;
            }
        );

        Actions\expectAdded('plugins_loaded')
            ->once()
            ->whenHappen(static function (callable $callable) use (&$onPluginsLoaded) {
                $onPluginsLoaded = $callable;
            });

        Actions\expectAdded('init')
            ->once()
            ->whenHappen(static function (callable $callable) use (&$onInit) {
                $onInit = $callable;
            });

        Actions\expectDone(App::ACTION_REGISTERED_PROVIDER)
            ->with($dependency->id(), \Mockery::type(App::class))
            ->once()
            ->whenHappen(static function (string $providerId, App $app) use ($dependant) {
                static::assertTrue($app->status()->isThemesStep());
                $app->addProvider($dependant);
            });

        Actions\expectDone(App::ACTION_REGISTERED_PROVIDER)
            ->with($dependant->id(), \Mockery::type(App::class))
            ->once();

        Actions\expectDone(App::ACTION_BOOTED_PROVIDER)
            ->with($dependant->id())
            ->once();

        $this->expectOutputString("I have been registered!\nI have been booted!");

        $app = App::new(new Container(null, $this->factoryContext()));
        $app->addProvider($dependency)->boot();

        $onPluginsLoaded();
        $onInit();

        static::assertTrue($app->hasProviders($dependency->id()));
    }

    /**
     * @param string $id
     * @param callable|null $register
     * @return ServiceProvider
     */
    private static function factoryProvider(string $id, callable $register = null): ServiceProvider
    {
        return new ConfigurableProvider($id, $register ?? '__return_true', '__return_true');
    }

    /**
     * @param string $string
     * @return object
     */
    public static function factoryStringObject(string $string): object
    {
        return new class ($string) {

            private $string;

            public function __construct(string $string)
            {
                $this->string = $string;
            }

            public function __toString()
            {
                return $this->string;
            }
        };
    }
}
