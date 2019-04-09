<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

use Inpsyde\App\Provider\Package;
use Inpsyde\App\Provider\ServiceProvider;
use Inpsyde\App\Provider\ServiceProviders;
use Psr\Container\ContainerInterface;

final class App
{
    public const ACTION_ADD_PROVIDERS = 'app.add-providers';
    public const ACTION_REGISTERED = 'app.providers-registered';
    public const ACTION_REGISTERED_PROVIDER = 'app.providers-provider-registered';
    public const ACTION_BOOTSTRAPPING = 'app.providers-bootstrapping-providers';
    public const ACTION_BOOTSTRAPPED = 'app.providers-bootstrapped';
    public const ACTION_ERROR = 'app.error';

    private const STATUS_IDLE = 0;
    private const STATUS_WAITING_EARLY = 1;
    private const STATUS_REGISTERED_EARLY = 2;
    private const STATUS_BOOTSTRAPPED_EARLY = 3;
    private const STATUS_WAITING = 4;
    private const STATUS_REGISTERED = 5;
    private const STATUS_BOOTSTRAPPED = 6;

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
     * @var int
     */
    private $status = self::STATUS_IDLE;

    /**
     * @var string
     */
    private $namespace;

    /**
     * @param string $namespace
     * @return App
     */
    public static function createAndBoot(string $namespace): App
    {
        if (did_action('plugins_loaded') && !doing_action('plugins_loaded')) {
            static::handleThrowable(new \Exception('It is too late to boot the app.'));

            return App::new($namespace);
        }

        if (self::$app) {
            static::handleThrowable(new \Exception('App already bootstrapped.'));

            return self::$app;
        }

        self::$app = App::new($namespace);
        add_action('plugins_loaded', [self::$app, 'boot'], PHP_INT_MAX);

        return self::$app;
    }

    /**
     * @param string $namespace
     * @param ContainerInterface ...$containers
     * @return App
     */
    public static function new(string $namespace, ContainerInterface ...$containers): App
    {
        return new static($namespace, ...$containers);
    }

    /**
     * @param string $namespace
     * @return App
     */
    public static function newWithContainer(Container $container): App
    {
        $app = new static('');
        $app->container = $container;

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
            if (!$theApp) {
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
        $this->namespace = $namespace;
        $this->wrappedContainers = $containers;
    }

    /**
     * - Core is before "init" ("plugins_loaded" if using `App::createAndBoot()`)
     *      1) Register not-deferred providers already added.
     *      2) Register deferred providers already added, only if they are early-booted
     *      3) Boot early-booted providers (not-deferred + deferred)
     * - Core is at "init"
     *      4) Register not-deferred providers added after point 3)
     *      5) Register added deferred providers not already registered at point 2)
     *      6) Fires "ACTION_REGISTERED"
     *      7) Boot all providers not already registered at point 3)
     *      8) Fires "ACTION_BOOTSTRAPPED"
     *
     * @return void
     */
    public function boot(): void
    {
        if (did_action('init') && !doing_action('init')) {
            self::handleThrowable(new \Exception('It is too late to initialize the app.'));

            return;
        }

        $late = (bool)did_action('init');

        $targetStatus = $late ? self::STATUS_WAITING : self::STATUS_WAITING_EARLY;

        if ($this->status >= $targetStatus) {
            self::handleThrowable(new \Exception('App is already initialized.'));

            return;
        }

        $this->status = $targetStatus;

        /**
         * Allow registration of providers via App::addProvider() when using using `App::create()`
         */
        do_action(self::ACTION_ADD_PROVIDERS, $this, $late);

        $this->registerAndBootProviders($late);

        remove_all_actions(self::ACTION_ADD_PROVIDERS);

        if (!$late) {
            add_action('init', [$this, 'boot'], PHP_INT_MAX);
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
                return $this;
            }

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
        add_action(
            self::ACTION_ADD_PROVIDERS,
            function (App $app) use ($package) {
                $package->providers()->provideTo($app);
            }
        );

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
     * @param bool $late
     * @return void
     */
    private function registerAndBootProviders(bool $late): void
    {
        $this->initializeContainer();
        $late or $this->container[Container::REGISTERED_PROVIDERS] = $this->registered;

        try {
            $this->registerDeferredProviders($late);

            $this->container[Container::REGISTERED_PROVIDERS] = $this->registered;

            if ($late) {
                $this->status = self::STATUS_REGISTERED;
                $this->container[Container::APP_REGISTERED] = true;
                do_action(self::ACTION_REGISTERED);
                $this->registered = [];
            }

            $this->bootProviders($late);
            //
        } catch (\Throwable $throwable) {
            static::handleThrowable($throwable);
        }

        if ($late) {
            $this->container[Container::APP_BOOTSTRAPPED] = true;
            do_action(self::ACTION_BOOTSTRAPPED, $this->container);
        }
    }

    /**
     * @param bool $late
     * @return void
     */
    private function registerDeferredProviders(bool $late): void
    {
        $toRegisterLater = $late ? null : new \SplQueue();

        while ($this->delayed->count()) {
            /** @var ServiceProvider $delayed */
            $delayed = $this->delayed->dequeue();
            $toRegisterNow = $late || $delayed->bootEarly();
            $toRegisterNow and $this->registerProvider($delayed);

            if ($toRegisterLater && !$toRegisterNow) {
                $toRegisterLater->enqueue($delayed);
            }
        }

        $this->delayed = $toRegisterLater;
        $this->status = $late ? self::STATUS_REGISTERED : self::STATUS_REGISTERED_EARLY;
    }

    /**
     * @param bool $late
     */
    private function bootProviders(bool $late): void
    {
        $toBootLater = $late ? null : new \SplQueue();

        while ($this->bootable->count()) {
            if (!did_action(self::ACTION_BOOTSTRAPPING)) {
                do_action(self::ACTION_BOOTSTRAPPING);
            }

            /** @var ServiceProvider $bootable */
            $bootable = $this->bootable->dequeue();

            /** @var bool $toBootNow */
            $toBootNow = $late || $bootable->bootEarly();
            $toBootNow and $bootable->boot($this->container);

            if ($toBootLater && !$toBootNow) {
                $toBootLater->enqueue($bootable);
            }
        }

        $this->bootable = $toBootLater;
        $this->status = $late ? self::STATUS_BOOTSTRAPPED : self::STATUS_BOOTSTRAPPED_EARLY;
    }

    /**
     * @param ServiceProvider $provider
     */
    private function registerProvider(ServiceProvider $provider)
    {
        $provider->register($this->container);
        $this->registered[$provider->id()] = true;

        do_action(self::ACTION_REGISTERED_PROVIDER, $provider->id(), $this);
    }
}
