<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Provider;

use Inpsyde\App\App;

class ServiceProviders
{
    /**
     * @var \SplObjectStorage|null
     */
    private $providers;

    /**
     * @var bool
     */
    private $done = false;

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
        if ($this->done) {
            $this->handleThrowable(
                new \Exception('Providers already added to app, can\'t add more providers.')
            );

            return $this;
        }

        $this->providers or $this->providers = new \SplObjectStorage();
        $this->providers->attach($provider, $contexts);

        return $this;
    }

    /**
     * @param App $app
     */
    public function provideTo(App $app): void
    {
        if ($this->done) {
            $this->handleThrowable(
                new \Exception('Providers already added to app, can\'t provide again.')
            );

            return;
        }

        $this->done = true;
        $this->providers->rewind();
        while ($this->providers->valid()) {
            /** @var ServiceProvider $provider */
            $provider = $this->providers->current();
            /** @var string[] $contexts */
            $contexts = $this->providers->getInfo();

            $app->addProvider($provider, ...$contexts);

            $this->providers->next();
        }

        $this->providers = null;
    }

    /**
     * @param \Throwable $throwable
     */
    private function handleThrowable(\Throwable $throwable): void
    {
        do_action(App::ACTION_ERROR, $throwable);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            throw $throwable;
        }
    }
}
