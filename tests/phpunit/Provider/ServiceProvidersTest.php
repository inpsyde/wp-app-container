<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Provider;

use Brain\Monkey\Actions;
use Inpsyde\App\App;
use Inpsyde\App\Container;
use Inpsyde\App\Provider\ConfigurableProvider;
use Inpsyde\App\Provider\ServiceProvider;
use Inpsyde\App\Provider\ServiceProviders;
use Inpsyde\App\Tests\TestCase;
use Inpsyde\WpContext;

class ServiceProvidersTest extends TestCase
{
    /**
     * @test
     */
    public function testProvideApp(): void
    {
        $app = App::new(new Container(null, $this->factoryContext()));

        Actions\expectDone(App::ACTION_REGISTERED_PROVIDER)
            ->once()
            ->with('p1', $app);

        Actions\expectDone(App::ACTION_REGISTERED_PROVIDER)
            ->once()
            ->with('p2', $app);

        Actions\expectDone(App::ACTION_REGISTERED_PROVIDER)
            ->never()
            ->with('p3', $app);

        $providers = ServiceProviders::new()
            ->add(self::factoryProvider('p1'))
            ->add(self::factoryProvider('p2'))
            ->add(self::factoryProvider('p3'), WpContext::REST);

        $providers->provideTo($app);
    }

    /**
     * @param string $id
     * @return ServiceProvider
     */
    private static function factoryProvider(string $id): ServiceProvider
    {
        return new ConfigurableProvider($id, '__return_true');
    }
}
