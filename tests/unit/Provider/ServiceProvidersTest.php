<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Provider;

use Brain\Monkey\Actions;
use Inpsyde\App\App;
use Inpsyde\App\Config\EnvConfig;
use Inpsyde\App\Config\Locations;
use Inpsyde\App\Provider\Booted;
use Inpsyde\App\Provider\ConfigurableProvider;
use Inpsyde\App\Provider\ServiceProvider;
use Inpsyde\App\Provider\ServiceProviders;
use Inpsyde\App\Tests\TestCase;
use Inpsyde\WpContext;
use Psr\Container\ContainerInterface;

class ServiceProvidersTest extends TestCase
{
    /**
     * @test
     */
    public function testProvideApp(): void
    {
        $locations = \Mockery::mock(Locations::class);
        $locations->allows('vendorUrl')->andReturn(null);
        $config = new EnvConfig();

        $app = App::new($config->withLocations($locations), null, $this->factoryContext());

        Actions\expectDone(App::ACTION_ADDED_MODULE)
            ->once()
            ->with('p1', $app);

        Actions\expectDone(App::ACTION_ADDED_MODULE)
            ->once()
            ->with('p2', $app);

        Actions\expectDone(App::ACTION_ADDED_MODULE)
            ->never()
            ->with('p3', $app);

        $providers = ServiceProviders::new()
            ->add(self::factoryProvider('p1'))
            ->add(self::factoryProvider('p2'))
            ->add(self::factoryProvider('p3'), WpContext::REST);

        $providers->provideTo($app);

        $events = $app->debugInfo()['events'];
        static::assertContains('Module p3 not added (wrong context).', $events);

        static::assertTrue($app->hasModules('p1', 'p2'));
        static::assertFalse($app->hasModules('p1', 'p3'));
    }

    /**
     * @param string $id
     * @return ServiceProvider
     */
    private static function factoryProvider(string $id): ServiceProvider
    {
        return new class ($id) extends Booted
        {
            public $id;

            public function __construct(string $id)
            {
                $this->id = $id;
            }

            public function services(): array
            {
                return [];
            }

            public function run(ContainerInterface $container): bool
            {
                return true;
            }
        };
    }
}
