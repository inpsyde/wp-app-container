<?php # -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Inpsyde\App\App;
use Inpsyde\App\AppStatus;
use Inpsyde\App\Container;
use Inpsyde\App\Context;
use Inpsyde\App\ContextInterface;
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
            ->whenHappen(
                function (\Throwable $throwable) {
                    throw $throwable;
                }
            );
    }

    /**
     * @param $id
     * @return ServiceProvider
     */
    private static function stubProvider($id, callable $register = null): ServiceProvider
    {
        return new ConfigurableProvider($id, $register ?? '__return_true', '__return_true');
    }

    private function mockContext(string $currentContext = Context::CORE): ContextInterface
    {
        $contextMock = $this->createMock(ContextInterface::class);
        $contextMock
            ->method('is')
            ->with($currentContext)
            ->willReturn(true);

        return $contextMock;
    }

    /**
     * @param string $string
     * @return object
     */
    public static function stubStringObject(string $string)
    {
        return new class($string) {
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

    /**
     * @runInSeparateProcess
     */
    public function testMakeFailsIfNoAppCreated()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/no valid app/i');

        App::make('foo');
    }

    /**
     * @runInSeparateProcess
     */
    public function testMakeFailsIfAppIdle()
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/uninitialised/i');

        $container = new Container(new EnvConfig(), Context::create());
        App::new($container);

        App::make('foo');
    }

    /**
     * @runInSeparateProcess
     */
    public function testMakeFailsIfNothingInTheContainer()
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $container = new Container(new EnvConfig(), Context::create());
        App::new($container)->boot();

        App::make('foo');
    }

    /**
     * @runInSeparateProcess
     */
    public function testMakeGetContainer()
    {
        $psr11 = new \Pimple\Psr11\Container(new \Pimple\Container(['foo' => 'bar']));

        $container = new Container(new EnvConfig(), Context::create(), $psr11);
        App::new($container)->boot();

        static::assertSame('bar', App::make('foo'));
    }

    public function testDebugInfo()
    {
        $app = App::new()->enableDebug();
        $app->addProvider(self::stubProvider('p1'))->addProvider(self::stubProvider('p2'));

        $info = $app->debugInfo();

        static::assertSame($info['status'], (string)AppStatus::new());
        static::assertIsArray($info['providers']);
        static::assertSame(['p1', 'p2'], array_keys($info['providers']));
    }

    public function debugProvider(): array
    {
        return [
            'debug disabled' => [false],
            'debug enabled' => [true],
        ];
    }

    /**
     * @dataProvider debugProvider
     * @param bool $isDebug
     */
    public function testBootFlow(bool $isDebug)
    {
        /** @var callable|null $onPluginsLoaded */
        $onPluginsLoaded = null;
        /** @var callable|null $onAfterSetupTheme */
        $onAfterSetupTheme = null;

        Actions\expectAdded('plugins_loaded')
            ->once()
            ->whenHappen(function (callable $callable) use (&$onPluginsLoaded) {
                $onPluginsLoaded = $callable;
            });

        Actions\expectAdded('after_setup_theme')
            ->once()
            ->whenHappen(function (callable $callable) use (&$onAfterSetupTheme) {
                $onAfterSetupTheme = $callable;
            });

        Actions\expectDone(App::ACTION_REGISTERED)->once()->withNoArgs();
        Actions\expectDone(App::ACTION_BOOTED)->once()->with(\Mockery::type(Container::class));

        $early = new ConfigurableProvider(
            'p-early',
            function (Container $c) {
                $c->addService('a', function () {
                    return AppTest::stubStringObject('A-');
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
                            function (Container $c) {
                                echo $c->get('a') . $c->get('b') . $c->get('c');

                                return true;
                            },
                            ConfigurableProvider::REGISTER_LATER
                        )
                    )
                    ->add(
                        new ConfigurableProvider(
                            'p-plugins-2',
                            function (Container $c) {
                                $c->addService('c', function () {
                                    return AppTest::stubStringObject('C!');
                                });

                                return true;
                            }
                        )
                    );
            }
        };

        $themes = new ConfigurableProvider(
            'p-themes',
            function (Container $c) {
                $c->addService('b', function () {
                    return AppTest::stubStringObject('B-');
                });

                return true;
            }
        );

        $count = 0;
        Actions\expectDone(App::ACTION_ADD_PROVIDERS)
            ->times(3)
            ->whenHappen(
                function (App $app, AppStatus $status) use ($early, $plugins, $themes, &$count) {
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

        $app = App::new(new Container(null, $this->mockContext()))->runLastBootAt('after_setup_theme');
        if ($isDebug) {
            $app->enableDebug();
        }
        $app->boot();

        $onPluginsLoaded();
        $onAfterSetupTheme();
    }

    /**
     * @dataProvider debugProvider
     * @param bool $isDebug
     */
    public function testNestedAddProvider(bool $isDebug)
    {
        $p1 = self::stubProvider('p1', function (Container $container) {
            $container->addService('a', function () {
                return AppTest::stubStringObject('A-');
            });

            return true;
        });

        $p2 = self::stubProvider('p2', function (Container $container) {
            $container->addService('b', function () {
                return AppTest::stubStringObject('B-');
            });

            return true;
        });

        $p3 = self::stubProvider('p3', function (Container $container) {
            $container->addService('c', function () {
                return AppTest::stubStringObject('C!');
            });

            return true;
        });

        $app = App::new(new Container(null, $this->mockContext()));
        if ($isDebug) {
            $app->enableDebug();
        }

        Actions\expectDone(App::ACTION_ADDED_PROVIDER)
            ->times(3)
            ->with(\Mockery::type('string'), $app)
            ->whenHappen(function (string $id, App $app) use ($p2) {
                if ($id === 'p1') {
                    $app->addProvider($p2);
                }
            });

        Actions\expectDone(App::ACTION_REGISTERED_PROVIDER)
            ->times(3)
            ->with(\Mockery::type('string'), $app)
            ->whenHappen(function (string $id, App $app) use ($p3) {
                if ($id === 'p2') {
                    $app->addProvider($p3);
                }
            });

        Actions\expectDone(App::ACTION_BOOTED)
            ->once()
            ->with(\Mockery::type(Container::class))
            ->whenHappen(function (Container $container) {
                echo $container->get('a') . $container->get('b') . $container->get('c');
            });

        Actions\expectDone('init')
            ->once()
            ->whenHappen(function () use ($app, $p1) {
                $app->addProvider($p1)->boot();
            });

        $this->expectOutputString('A-B-C!');

        do_action('plugins_loaded');
        do_action('init');
    }

    /**
     * @dataProvider debugProvider
     * @param bool $isDebug
     */
    public function testCallingBootFromNestedAddProviderFails(bool $isDebug)
    {
        $app = App::new(new Container(null, $this->mockContext()));
        if ($isDebug) {
            $app->enableDebug();
        }

        Actions\expectDone(App::ACTION_ADDED_PROVIDER)
            ->twice()
            ->with(\Mockery::type('string'), $app)
            ->whenHappen(function (string $id, App $app) {
                if ($id === 'p1') {
                    $app->addProvider(self::stubProvider('p2'));
                    $app->boot();
                }
            });

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/already booting/i');

        $app->addProvider(self::stubProvider('p1'));
    }

    /**
     * @dataProvider debugProvider
     * @param bool $isDebug
     */
    public function testDependantProviderOnLastBootIsBooted(bool $isDebug)
    {
        /** @var callable|null $onPluginsLoaded */
        $onPluginsLoaded = null;

        /** @var callable|null $onInit */
        $onInit = null;

        $dependency = new ConfigurableProvider(
            'dependency',
            function () {
                return true;
            },
            null,
            ConfigurableProvider::REGISTER_LATER
        );

        $dependant = new ConfigurableProvider(
            'dependant',
            function () {
                echo "I have been registered!\n";

                return true;
            },
            function () {
                echo "I have been booted!";

                return true;
            }
        );

        Actions\expectAdded('plugins_loaded')
            ->once()
            ->whenHappen(function (callable $callable) use (&$onPluginsLoaded) {
                $onPluginsLoaded = $callable;
            });

        Actions\expectAdded('init')
            ->once()
            ->whenHappen(function (callable $callable) use (&$onInit) {
                $onInit = $callable;
            });

        Actions\expectDone(App::ACTION_REGISTERED_PROVIDER)
            ->with($dependency->id(), \Mockery::type(App::class))
            ->once()
            ->whenHappen(function (string $providerId, App $app) use ($dependant, $dependency) {
                static::assertSame($dependency->id(), $providerId);
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

        $app = App::new(new Container(null, $this->mockContext()));
        if ($isDebug) {
            $app->enableDebug();
        }
        $app->addProvider($dependency);
        $app->boot();

        $onPluginsLoaded();
        $onInit();

        static::assertTrue($app->hasProviders($dependency->id()));
    }
}
