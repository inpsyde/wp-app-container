<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider;

use Inpsyde\App\App;

class ServiceProviders
{
    /**
     * @var \SplObjectStorage|null
     */
    private $providers;

    /**
     * @return ServiceProviders
     */
    public static function new(): ServiceProviders
    {
        return new static();
    }

    /**
     * @param ServiceProvider $provider
     * @param string ...$contexts
     * @return ServiceProviders
     */
    public function add(ServiceProvider $provider, string ...$contexts): ServiceProviders
    {
        if (!$this->providers) {
            $this->providers = new \SplObjectStorage();
        }

        $this->providers->attach($provider, $contexts);

        return $this;
    }

    /**
     * @param App $app
     */
    public function provideTo(App $app): void
    {
        if (!$this->providers) {
            return;
        }

        $this->addProvidersToApp($app, $this->providers);
        $this->providers = null;
    }

    /**
     * @param App $app
     * @param \SplObjectStorage $providers
     */
    private function addProvidersToApp(App $app, \SplObjectStorage $providers): void
    {
        $providers->rewind();

        while ($providers->valid()) {
            /** @var ServiceProvider $provider */
            $provider = $providers->current();
            /** @var string[] $contexts */
            $contexts = $providers->getInfo();

            $app->addProvider($provider, ...$contexts);

            $providers->next();
        }
    }
}
