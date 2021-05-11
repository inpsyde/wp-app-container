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
    private static function newProvider($id): ServiceProvider
    {
        return new ConfigurableProvider($id, '__return_true');
    }

    /**
     * @runInSeparateProcess
     */
    public function testProvideApp()
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
            ->add(self::newProvider('p1'))
            ->add(self::newProvider('p2'))
            ->add(self::newProvider('p3'), WpContext::REST);

        $providers->provideTo($app);
    }
}
