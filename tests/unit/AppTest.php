<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Inpsyde\App\App;
use Inpsyde\App\AppStatus;
use Inpsyde\App\CompositeContainer;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Package;
use Inpsyde\WpContext;
use Brain\Monkey;

class AppTest extends TestCase
{
    /** @var \ReflectionProperty */
    private $containerProp;
    /** @var \ReflectionProperty */
    private $mapProp;
    /** @var \ReflectionProperty */
    private $appStatusProp;
    /** @var \ReflectionProperty */
    private $appBootQueueProp;
    /** @var \ReflectionMethod  */
    private $appHandleModularityBoot;
    /** @var \ReflectionMethod  */
    private $appSyncModularityStatus;
    /** @var \ReflectionClass  */
    private $appStatusReflection;

    protected function setUp(): void
    {
        Monkey\Functions\when('remove_all_actions')->justReturn();
        $reflectedApp = new \ReflectionClass(App::class);
        $this->containerProp = $reflectedApp->getProperty('container');
        $this->containerProp->setAccessible(true);
        $this->appStatusProp = $reflectedApp->getProperty('status');
        $this->appStatusProp->setAccessible(true);
        $reflectedContainer = new \ReflectionClass(CompositeContainer::class);
        $this->mapProp = $reflectedContainer->getProperty('map');
        $this->mapProp->setAccessible(true);
        $this->appBootQueueProp = $reflectedApp->getProperty('bootQueue');
        $this->appBootQueueProp->setAccessible(true);
        $this->appHandleModularityBoot = $reflectedApp->getMethod('handleModularityBoot');
        $this->appSyncModularityStatus = $reflectedApp->getMethod('syncModularityStatus');

        $this->appStatusReflection = new \ReflectionClass(AppStatus::class);

        parent::setUp();
    }

    private function prepareSharePackageCommon(): array
    {
        \Brain\Monkey\Functions\stubs([
            'plugins_url' => static function (): string {
                return content_url() . '/plugins/fake';
            },
        ]);

        $context = WpContext::new()->force(WpContext::CORE);
        $app = App::new(null, null, $context);
        static::assertInstanceOf(
            CompositeContainer::class,
            $this->containerProp->getValue($app)
        );
        $appModuleId = 'app-module-id';
        $containerServiceId = 'cont-service-id';

        $appModule = $this->mockModule($appModuleId, ServiceModule::class);
        $appModule->expects('services')->andReturn($this->stubServices($containerServiceId));
        $app->addEarlyModule($appModule);

        $moduleId = 'my-service-module';
        $packageServiceId = 'service-id';

        $module = $this->mockModule($moduleId, ServiceModule::class);
        $expectedReturnFromService = $this->stubServices($packageServiceId);
        $module->expects('services')->andReturn($expectedReturnFromService);

        $package = Package::new($this->mockProperties())->addModule($module);

        return [
            'app' => $app,
            'appModuleId' => $appModuleId,
            'containerServiceId' => $containerServiceId,
            'moduleId' => $moduleId,
            'packageServiceId' => $packageServiceId,
            'package' => $package,
            'expectedClassFromPackageService' => \ArrayObject::class,
        ];
    }

    public function testNewWithNoContainer()
    {

        $context = WpContext::new()->force(WpContext::CORE);
        $app = App::new(null, null, $context);
        static::assertInstanceOf(
            CompositeContainer::class,
            $this->containerProp->getValue($app)
        );
        static::assertTrue($this->appStatusProp->getValue($app)->isIdle());
    }

    public function testAddEarlyModule()
    {
        $context = WpContext::new()->force(WpContext::CORE);
        $app = App::new(null, null, $context);
        $moduleId = 'my-early-service-module';
        $moduleServiceId = 'early-service-id';
        $module = $this->mockModule($moduleId, ServiceModule::class);
        $module->expects('services')->andReturn($this->stubServices($moduleServiceId));
        $app->addEarlyModule($module);
        // We expect the service is not resolvable if the App Container is not booted
        static::assertEquals(null, $app->resolve($moduleServiceId));
        $app->boot();
        static::assertInstanceOf(\ArrayObject::class, $app->resolve($moduleServiceId));
    }

    /**
     *
     * @return void
     * @throws \ReflectionException
     */
    public function testAddModule()
    {
        $context = WpContext::new()->force(WpContext::CORE);
        $app = App::new(null, null, $context);
        $moduleId = 'my-early-service-module';
        $moduleServiceId = 'early-service-id';
        $module = $this->mockModule($moduleId, ServiceModule::class);
        $module->expects('services')->andReturn($this->stubServices($moduleServiceId));
        $app->addModule($module);
        // We expect the service is not resolvable if the App Container is not booted
        static::assertEquals(null, $app->resolve($moduleServiceId));

        // we have to force the internal status of the AppStatus
        // we need $lastRun to be true when calling isThemeStep inside boot
        $appStatusInternalStatusProp = $this->appStatusReflection->getProperty('status');
        $appStatusInternalStatusProp->setValue(
            $this->appStatusProp->getValue($app),
            AppStatus::REGISTERING_THEMES
        );

        $app->boot();
        static::assertInstanceOf(\ArrayObject::class, $app->resolve($moduleServiceId));
    }

    /**
     * Scenario
     *      Package is booted
     * Expectations
     *      Package services are added to the App Container
     *      Package does NOT receive any definitions from the WP App Container
     *
     * @group sharePackageToBoot
     * @return void
     */
    public function testSharePackageToBootWhenPackageIsBooted()
    {
        [
            'app' => $app,
            'containerServiceId' => $containerServiceId,
            'packageServiceId' => $packageServiceId,
            'package' => $package,
            'expectedClassFromPackageService' => $expectedClassFromPackageService,
        ] = $this->prepareSharePackageCommon();

        // When package is booted
        static::assertTrue($package->boot());

        // When we call sharePackageToBoot and pass a booted package
        static::assertInstanceOf(App::class, $app->sharePackageToBoot($package));

        // The Services from the Package are shared to the App Container
        static::assertInstanceOf($expectedClassFromPackageService, $app->resolve($packageServiceId));

        // But, the services from the App Container are NOT added to the Package since the Package Container is ReadOnly
        static::assertFalse($package->container()->has($containerServiceId));
    }

    /**
     * Scenario
     *      Package is not booted
     *      The Services added to the App Container are added via addEarlyModule
     *      App boot is called after
     * Expectations
     *      After App Booting
     *          App Container can resolve Package services
     *          Package can resolve App Container Services
     * @group sharePackageToBoot
     * @return void
     */
    public function testSharePackageToBootWhenPackageIsNotBooted(): void
    {

        [
            'app' => $app,
            'containerServiceId' => $containerServiceId,
            'packageServiceId' => $packageServiceId,
            'package' => $package,
        ] = $this->prepareSharePackageCommon();

        // When we call sharePackageToBoot passing a NOT booted package
        // Notice that the WP App container is not booted at this point neither
        static::assertInstanceOf(App::class, $app->sharePackageToBoot($package));

        // we don't expect the services from the Package to be in the App Container before booting the App Container
        $container = $this->containerProp->getValue($app);
        static::assertFalse($container->has($packageServiceId));

        // we don't expect the package to have container to be accesible because is not booted nor built.
        static::assertFalse($package->statusIs(Package::STATUS_BOOTED));
        try {
            $package->container();
        } catch (\Exception $exception) {
            static::assertStringContainsString(
                'Can\'t obtain the container',
                $exception->getMessage()
            );
        }

        // It is only connected with a new Package that is booted already
        static::assertEquals([ "inpsyde-wp-app" => true ], $package->connectedPackages());

        // We expect the Package to be added to the boot queue
        /** @var \SplObjectStorage $currentQueue */
        $currentQueue = $this->appBootQueueProp->getValue($app);
        static::assertTrue($currentQueue->contains($package));

        // It seems the App Container does not have the service in the container if it is not booted
        $container = $this->containerProp->getValue($app);
        static::assertFalse($container->has($containerServiceId));

        // if we boot the App container
        $app->boot();

        // We expect the App Container to have the Package services
        static::assertInstanceOf(\ArrayObject::class, $app->resolve($packageServiceId));

        // We expect the Package to be booted
        static::assertTrue($package->statusIs(Package::STATUS_BOOTED));

        // Note: we can retrieve the service from the App Container because we used App::addEarlyModule
        static::assertTrue($package->container()->has($containerServiceId));
        static::assertInstanceOf(\ArrayObject::class, $app->resolve($containerServiceId));
    }

    /**
     * @return void
     * @group sharePackage
     */
    public function testSharePackageWhenPackageIsBooted()
    {
        /**
         * @var App $app
         */
        [
            'app' => $app,
            'containerServiceId' => $containerServiceId,
            'packageServiceId' => $packageServiceId,
            'package' => $package,
            'expectedClassFromPackageService' => $expectedClassFromPackageService,
        ] = $this->prepareSharePackageCommon();

        // When package is booted
        static::assertTrue($package->boot());

        // When we call sharePackage and pass a booted package
        static::assertInstanceOf(App::class, $app->sharePackage($package));

        // The Services from the Package are shared to the App Container
        static::assertInstanceOf($expectedClassFromPackageService, $app->resolve($packageServiceId));

        // But, the services from the App Container are NOT added to the Package since the Package Container is ReadOnly
        static::assertFalse($package->container()->has($containerServiceId));
    }

    /**
     * Scenario
     *      Package is not booted
     *      The Services added to the App Container are added via addEarlyModule
     *      App boot is called after
     * Expectations
     *      After App Booting
     *          App Container can resolve Package services
     *          Package can resolve App Container Services
     * @group sharePackage
     * @return void
     */
    public function testSharePackageWhenPackageIsNotBooted(): void
    {

        [
            'app' => $app,
            'containerServiceId' => $containerServiceId,
            'packageServiceId' => $packageServiceId,
            'package' => $package,
        ] = $this->prepareSharePackageCommon();

        // When we call sharePackageToBoot passing a NOT booted package
        // Notice that the WP App container is not booted at this point neither
        static::assertInstanceOf(App::class, $app->sharePackage($package));

        // we don't expect the services from the Package to be in the App Container before booting the App Container
        $container = $this->containerProp->getValue($app);
        static::assertFalse($container->has($packageServiceId));

        // we don't expect the package to have container to be accessible because is not booted nor built.
        static::assertFalse($package->statusIs(Package::STATUS_BOOTED));
        try {
            $package->container();
        } catch (\Exception $exception) {
            static::assertStringContainsString(
                'Can\'t obtain the container',
                $exception->getMessage()
            );
        }

        // It is only connected with a new Package that is booted already
        static::assertEquals([ "inpsyde-wp-app" => true ], $package->connectedPackages());

        // we manually boot the package in here since sharePackage enqueues a callback waiting for this
        $package->boot();

        // we have to mimic the internals of the waitForPackageBoot
        // (we are hardcoding a callback there)
        $this->appSyncModularityStatus->invoke(
            $app,
            $package,
            Package::MODULE_ADDED
        );

        $this->appHandleModularityBoot->invoke(
            $app,
            $package,
            true
        );

        // package can't see the service from app container if the Wp App Container is not booted
        static::assertFalse($package->container()->has($containerServiceId));

        // if we boot the App container
        $app->boot();

        // We expect the Package to have container services
        static::assertTrue($package->container()->has($containerServiceId));

        // We expect the App Container to have the Package services
        static::assertInstanceOf(\ArrayObject::class, $app->resolve($packageServiceId));

        // Note: we can retrieve the service from the App Container because we used App::addEarlyModule
        static::assertInstanceOf(\ArrayObject::class, $app->resolve($containerServiceId));
    }
}
