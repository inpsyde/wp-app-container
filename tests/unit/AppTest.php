<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Inpsyde\App\App;
use Inpsyde\App\CompositeContainer;
use Inpsyde\Modularity\Container\ReadOnlyContainer;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Package;
use Inpsyde\WpContext;
use Psr\Container\ContainerInterface;
use Inpsyde\App\Config\Config;
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

    protected function setUp(): void
    {
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
        parent::setUp();
    }

    private function prepareShareToPackageCommon(): array
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
        $app->addModule($appModule);

        $moduleId = 'my-service-module';
        $packageServiceId = 'service-id';

        $module = $this->mockModule($moduleId, ServiceModule::class);
        $module->expects('services')->andReturn($this->stubServices($packageServiceId));

        $package = Package::new($this->mockProperties())->addModule($module);

        return [
            'app' => $app,
            'appModuleId' => $appModuleId,
            'containerServiceId' => $containerServiceId,
            'moduleId' => $moduleId,
            'packageServiceId' => $packageServiceId,
            'package' => $package,
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

    public function testSharePackageToBootShouldAddPackageServicesToContainerIfPackageIsBooted()
    {
        [
            'app' => $app,
            'containerServiceId' => $containerServiceId,
            'packageServiceId' => $packageServiceId,
            'package' => $package
        ] = $this->prepareShareToPackageCommon();

        // When package is booted
        static::assertTrue($package->boot());

        // When we call sharePackageToBoot and pass a booted package
        static::assertInstanceOf(App::class, $app->sharePackageToBoot($package));

        $container = $this->containerProp->getValue($app);

        // The Services from the Package are shared to the App Container
        static::assertTrue($container->has($packageServiceId));

        // But, the services from the App Container are NOT added to the Package since the container is ReadOnly
        static::assertInstanceOf(ReadOnlyContainer::class, $package->container());
        static::assertFalse($package->container()->has($containerServiceId));
    }

    public function testSharePackageToBootShouldAddPackageServicesToContainerAfterBootIfPackageIsNotBooted()
    {

        [
            'app' => $app,
            'containerServiceId' => $containerServiceId,
            'packageServiceId' => $packageServiceId,
            'package' => $package
        ] = $this->prepareShareToPackageCommon();

        Monkey\Functions\when('remove_all_actions')->justReturn();

        // When we call sharePackageToBoot passing a NOT booted package
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

        // if we boot the App container
        $app->boot();
        $container = $this->containerProp->getValue($app);

        // We expect the App Container to have the Package services
        static::assertTrue($container->has($packageServiceId));

        // We expect the Package to be booted
        static::assertTrue($package->statusIs(Package::STATUS_BOOTED));

        // we expect the App Container services to be in the Package
        // sharePackageToBoot is meant to boot the Package
        // TODO: FIX THIS
//        static::assertTrue($package->container()->has($containerServiceId));
    }
}
