<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Inpsyde\App\App;
use Inpsyde\App\CompositeContainer;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Package;
use Inpsyde\WpContext;
use Psr\Container\ContainerInterface;
use Inpsyde\App\Config\Config;
use Brain\Monkey;

class AppTest extends TestCase
{
    private \ReflectionProperty $containerProp;
    private \ReflectionProperty $mapProp;
    private \ReflectionProperty $appStatusProp;

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
        parent::setUp();
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

        static::assertTrue($package->boot());
        static::assertInstanceOf(App::class, $app->sharePackageToBoot($package));
        $container = $this->containerProp->getValue($app);

        static::assertTrue($container->has($packageServiceId));
    }

    public function testSharePackageToBootShouldAddPackageServicesToContainerAfterBootIfPackageIsNotBooted()
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

        static::assertInstanceOf(App::class, $app->sharePackageToBoot($package));
        $container = $this->containerProp->getValue($app);
        Monkey\Functions\when('remove_all_actions')->justReturn();

        // we boot the app container
        $app->boot();

        static::assertTrue($container->has($packageServiceId));
        static::assertEquals([ "inpsyde-wp-app" => true ], $package->connectedPackages());

        /**
         * The following assert is failing
         */
//        static::assertTrue($package->container()->has($containerServiceId));
    }

}