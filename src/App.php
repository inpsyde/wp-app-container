<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

use Inpsyde\App\Provider\Package;
use Inpsyde\App\Provider\ServiceProvider;
use Psr\Container\ContainerInterface;

final class App
{
    public const ACTION_ADD_PROVIDERS = 'app.add-providers';
    public const ACTION_REGISTERED = 'app.providers-registered';
    public const ACTION_REGISTERED_PROVIDER = 'app.providers-provider-registered';
    public const ACTION_BOOTSTRAPPED = 'app.providers-bootstrapped';
    public const ACTION_ERROR = 'app.error';

    /**
     * @var App
     */
    private static $app;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var ContainerInterface[]
     */
    private $wrappedContainers;

    /**
     * @var \SplQueue
     */
    private $bootable;

    /**
     * @var \SplQueue
     */
    private $delayed;

    /**
     * @var array<string,bool>
     */
    private $registered = [];

    /**
     * @var AppInfo
     */
    private $appInfo;

    /**
     * @var AppStatus
     */
    private $status;

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var bool
     */
    private $booting = false;

    /**
     * @param string $namespace
     * @param ContainerInterface ...$containers
     * @return App
     */
    public static function new(string $namespace, ContainerInterface ...$containers): App
    {
        $app = new static($namespace, ...$containers);
        self::$app or self::$app = $app;

        return $app;
    }

    /**
     * @param string $namespace
     * @return App
     */
    public static function newWithContainer(Container $container): App
    {
        $app = new static('');
        $app->container = $container;
        self::$app or self::$app = $app;

        return $app;
    }

    /**
     * @param string $id
     * @param App|null $app
     * @return mixed
     *
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     */
    public static function make(string $id, App $app = null)
    {
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration

        $value = null;
        $theApp = $app ?? self::$app;

        try {
            if (!$theApp || $theApp->status->isIdle()) {
                throw new \Exception('No valid app found, not any app object given.');
            }

            $theApp->initializeContainer();
            $value = $theApp->container->get($id);
        } catch (\Throwable $throwable) {
            static::handleThrowable($throwable);
        }

        return $value;
    }

    /**
     * @param \Throwable $throwable
     */
    private static function handleThrowable(\Throwable $throwable): void
    {
        do_action(self::ACTION_ERROR, $throwable);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            throw $throwable;
        }
    }

    /**
     * @param string $namespace
     * @param ContainerInterface ...$containers
     */
    private function __construct(string $namespace, ContainerInterface ...$containers)
    {
        $this->status = AppStatus::new();
        $this->appInfo = AppInfo::new();
        $this->namespace = $namespace;
        $this->wrappedContainers = $containers;
    }

    /**
     * @return App
     */
    public function enableDebug(): App
    {
        $this->appInfo->enableDebug();

        return $this;
    }

    /**
     * @return App
     */
    public function disableDebug(): App
    {
        $this->appInfo->disableDebug();

        return $this;
    }

    /**
     * @return App
     */
    public function runLastBootAt(string $hook): App
    {
        try {
            $this->status = $this->status->lastStepOn($hook);
        } catch (\Throwable $throwable) {
            static::handleThrowable($throwable);
        }

        return $this;
    }

    /**
     * @return array|null
     */
    public function debugInfo(): ?array
    {
        $providers = $this->appInfo->providersStatus();
        if ($providers === null) {
            return null;
        }

        return [
            'namespace' => $this->namespace,
            'status' => (string)$this->status,
            'providers' => $providers,
        ];
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        try {
            if ($this->booting) {
                throw new \Exception('Can\'t call App::boot() when already booting.');
            }

            $this->status = $this->status->next($this);

            // We set to true to prevent anything listening self::ACTION_ADD_PROVIDERS to call boot().
            $this->booting = true;

            /**
             * Allow registration of providers via App::addProvider() when using using `App::create()`.
             */
            do_action(self::ACTION_ADD_PROVIDERS, $this, $this->status);

            $this->booting = false;

            $this->registerAndBootProviders();

            // Remove the actions so that when the method runs again, there's no duplicate registration.
            remove_all_actions(self::ACTION_ADD_PROVIDERS);
        } catch (\Throwable $exception) {
            self::handleThrowable($exception);
        }
    }

    /**
     * @param ServiceProvider $provider
     * @param string ...$contexts
     * @return App
     */
    public function addProvider(ServiceProvider $provider, string ...$contexts): App
    {
        try {
            $this->initializeContainer();

            if ($contexts && !$this->container->context()->is(...$contexts)) {
                $this->appInfo->providerSkipped($provider, $this->status);

                return $this;
            }

            $this->appInfo->providerAdded($provider, $this->status);

            $provider->registerLater()
                ? $this->delayed->enqueue($provider)
                : $this->registerProvider($provider);

            $this->bootable->enqueue($provider);
        } catch (\Throwable $throwable) {
            static::handleThrowable($throwable);
        }

        return $this;
    }

    /**
     * @param Package $package
     * @return App
     */
    public function addPackage(Package $package): App
    {
        try {
            $package->providers()->provideTo($this);
        } catch (\Throwable $throwable) {
            static::handleThrowable($throwable);
        }

        return $this;
    }

    /**
     * @param string $id
     * @return mixed
     *
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     */
    public function resolve(string $id)
    {
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration

        return static::make($id, $this);
    }

    /**
     * @return void
     */
    private function initializeContainer(): void
    {
        if (!$this->container) {
            $this->container = new Container(
                new EnvConfig($this->namespace),
                Context::create(),
                ...$this->wrappedContainers
            );
            $this->delayed = new \SplQueue();
            $this->bootable = new \SplQueue();
        }
    }

    /**
     * @return void
     */
    private function registerAndBootProviders(): void
    {
        $this->initializeContainer();
        $lastRun = $this->status->isThemesStep();

        $lastRun or $this->container[Container::REGISTERED_PROVIDERS] = $this->registered;

        try {
            $this->registerDeferredProviders();

            $this->container[Container::REGISTERED_PROVIDERS] = $this->registered;

            if ($lastRun) {
                $this->container[Container::APP_REGISTERED] = true;
                do_action(self::ACTION_REGISTERED);
                $this->registered = [];
            }

            $this->bootProviders();
            //
        } catch (\Throwable $throwable) {
            static::handleThrowable($throwable);
        }

        if ($lastRun) {
            $this->container[Container::APP_BOOTSTRAPPED] = true;
            do_action(self::ACTION_BOOTSTRAPPED, $this->container);
        }
    }

    /**
     * @return void
     */
    private function registerDeferredProviders(): void
    {
        $lastRun = $this->status->isThemesStep();
        $toRegisterLater = $lastRun ? null : new \SplQueue();

        while ($this->delayed->count()) {
            /** @var ServiceProvider $delayed */
            $delayed = $this->delayed->dequeue();
            $toRegisterNow = $lastRun || $delayed->bootEarly();
            $toRegisterNow and $this->registerProvider($delayed);

            if ($toRegisterLater && !$toRegisterNow) {
                $toRegisterLater->enqueue($delayed);
            }
        }

        $this->delayed = $toRegisterLater;
        $this->status->next($this);
    }

    /**
     * @return void
     */
    private function bootProviders(): void
    {
        $lastRun = $this->status->isThemesStep();
        $toBootLater = $lastRun ? null : new \SplQueue();

        while ($this->bootable->count()) {
            /** @var ServiceProvider $bootable */
            $bootable = $this->bootable->dequeue();

            /** @var bool $toBootNow */
            $toBootNow = $lastRun || $bootable->bootEarly();

            if ($toBootNow && $bootable->boot($this->container)) {
                $this->appInfo->providerBooted($bootable, $this->status);
            }

            if ($toBootLater && !$toBootNow) {
                $toBootLater->enqueue($bootable);
            }
        }

        $this->bootable = $toBootLater;

        $this->status->next($this);
    }

    /**
     * @param ServiceProvider $provider
     * @return void
     */
    private function registerProvider(ServiceProvider $provider): void
    {
        $registered = $provider->register($this->container);
        $this->registered[$provider->id()] = true;

        try {
            if ($registered) {
                $this->booting = true;
                do_action(self::ACTION_REGISTERED_PROVIDER, $provider->id(), $this, $registered);
                $this->booting = false;

                $this->appInfo->providerRegistered($provider, $this->status);
            }
        } catch (\Throwable $exception) {
            self::handleThrowable($exception);
        }
    }
}
