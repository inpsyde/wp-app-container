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
    public const ACTION_BOOTSTRAPPING = 'app.providers-bootstrapping-providers';
    public const ACTION_BOOTSTRAPPED = 'app.providers-bootstrapped';
    public const ACTION_ERROR = 'app.error';

    public const REGISTER_EARLY = 'early';
    public const REGISTER_VERY_EARLY = 'very-early';
    public const REGISTER_REGULAR = 'regulars';

    private const STATUS_IDLE = 0;
    private const STATUS_VERY_EARLY = 1;
    private const STATUS_WAITING_EARLY = 2;
    private const STATUS_REGISTERED_EARLY = 3;
    private const STATUS_BOOTSTRAPPED_EARLY = 4;
    private const STATUS_WAITING = 5;
    private const STATUS_REGISTERED = 6;
    private const STATUS_BOOTSTRAPPED = 7;

    private const REGISTER_TYPE_MAP = [
        self::STATUS_VERY_EARLY => self::REGISTER_VERY_EARLY,
        self::STATUS_WAITING_EARLY => self::REGISTER_EARLY,
        self::STATUS_WAITING => self::REGISTER_REGULAR,
    ];

    private const PACKAGE_ADDED = 'Added';
    private const PACKAGE_BOOTED = 'Booted';
    private const PACKAGE_BOOTED_EARLY = 'Booted (early)';
    private const PACKAGE_REGISTERED = 'Registered';
    private const PACKAGE_REGISTERED_DELAYED = 'Registered (delayed)';
    private const PACKAGE_REGISTERED_EARLY = 'Registered (early)';
    private const PACKAGE_REGISTERED_EARLY_DELAYED = 'Registered (early, delayed)';
    private const PACKAGE_SKIPPED = 'Skipped';

    private const _NAMESPACE = 'namespace';
    private const _DEBUG_ENABLED = 'debug-enabled';
    private const _PROVIDERS_STATUS = 'providers-status';
    private const _STATUS = 'status';

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
     * @var \stdClass
     */
    private $settings;

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
        $this->wrappedContainers = $containers;

        $this->settings = [
            self::_NAMESPACE => $namespace,
            self::_DEBUG_ENABLED => defined('WP_DEBUG') && WP_DEBUG,
            self::_PROVIDERS_STATUS => null,
            self::_STATUS => self::STATUS_IDLE,
        ];
    }

    /**
     * @return App
     */
    public function enableDebug(): App
    {
        $this->settings[self::_DEBUG_ENABLED] = true;

        return $this;
    }

    /**
     * @return App
     */
    public function disableDebug(): App
    {
        $this->settings[self::_DEBUG_ENABLED] = false;

        return $this;
    }

    /**
     * @return array|null
     */
    public function debugInfo(): ?array
    {
        if (!$this->settings[self::_DEBUG_ENABLED]) {
            return null;
        }

        return [
            'namespace' => $this->settings[self::_NAMESPACE],
            'services' => $this->settings[self::_PROVIDERS_STATUS],
            'status' => $this->settings[self::_STATUS],
        ];
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
        static $booting;

        if ($booting) {
            self::handleThrowable(
                new \Exception('It is not possible to call App::boot() when booting().')
            );

            return;
        }

        if (did_action('init') && !doing_action('init')) {
            self::handleThrowable(new \Exception('It is too late to initialize the app.'));

            return;
        }

        $late = (bool)did_action('init');
        $veryEarly = !$late && !did_action('plugins_loaded');

        $targetStatus = self::STATUS_WAITING_EARLY;
        if ($late || $veryEarly) {
            $targetStatus = $late ? self::STATUS_WAITING : self::STATUS_VERY_EARLY;
        }

        $this->updateAppStatus($targetStatus);

        // We set to true to prevent anything listening self::ACTION_ADD_PROVIDERS to call boot().
        $booting = true;

        /**
         * Allow registration of providers via App::addProvider() when using using `App::create()`.
         */
        do_action(self::ACTION_ADD_PROVIDERS, $this, self::REGISTER_TYPE_MAP[$targetStatus]);

        $booting = false;

        $this->registerAndBootProviders($late, $veryEarly);

        // Remove the actions so that when the method runs again, there's no duplicate registration.
        remove_all_actions(self::ACTION_ADD_PROVIDERS);

        // If "boot" is ran manually very early, we run it again on plugins loaded.
        if ($veryEarly) {
            add_action('plugins_loaded', [$this, 'boot'], PHP_INT_MAX);
        }

        // If "boot" is ran before "init" run it again on "init".
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
                $this->updateProviderStatus($provider, self::PACKAGE_SKIPPED);

                return $this;
            }

            $this->updateProviderStatus($provider, self::PACKAGE_ADDED);

            $later = $provider->registerLater();
            $later and $this->delayed->enqueue($provider);

            if (!$later && $this->registerProvider($provider)) {
                $late = did_action('init');
                $status = $late ? self::PACKAGE_REGISTERED : self::PACKAGE_REGISTERED_EARLY;
                $this->updateProviderStatus($provider, $status);
            }

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
                new EnvConfig($this->settings[self::_NAMESPACE]),
                Context::create(),
                ...$this->wrappedContainers
            );
            $this->delayed = new \SplQueue();
            $this->bootable = new \SplQueue();
        }
    }

    /**
     * @param bool $late
     * @param bool $veryEarly
     */
    private function registerAndBootProviders(bool $late, bool $veryEarly): void
    {
        $this->initializeContainer();
        $late or $this->container[Container::REGISTERED_PROVIDERS] = $this->registered;

        try {
            $this->registerDeferredProviders($late, $veryEarly);

            $this->container[Container::REGISTERED_PROVIDERS] = $this->registered;

            if ($late) {
                $this->updateAppStatus(self::STATUS_REGISTERED);
                $this->container[Container::APP_REGISTERED] = true;
                do_action(self::ACTION_REGISTERED);
                $this->registered = [];
            }

            $this->bootProviders($late, $veryEarly);
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
     * @param bool $veryEarly
     */
    private function registerDeferredProviders(bool $late, bool $veryEarly): void
    {
        $toRegisterLater = $late ? null : new \SplQueue();

        while ($this->delayed->count()) {
            /** @var ServiceProvider $delayed */
            $delayed = $this->delayed->dequeue();
            $toRegisterNow = $late || $delayed->bootEarly();
            $registered = $toRegisterNow ? $this->registerProvider($delayed) : false;

            if ($registered && !$veryEarly) {
                $status = $late
                    ? self::PACKAGE_REGISTERED_DELAYED
                    : self::PACKAGE_REGISTERED_EARLY_DELAYED;
                $this->updateProviderStatus($delayed, $status);
            }

            if ($toRegisterLater && !$toRegisterNow) {
                $toRegisterLater->enqueue($delayed);
            }
        }

        $this->delayed = $toRegisterLater;

        if (!$veryEarly) {
            $this->updateAppStatus($late ? self::STATUS_REGISTERED : self::STATUS_REGISTERED_EARLY);
        }
    }

    /**
     * @param bool $late
     * @param bool $veryEarly
     */
    private function bootProviders(bool $late, bool $veryEarly): void
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

            $booted = $toBootNow ? $bootable->boot($this->container) : false;

            if ($booted && !$veryEarly) {
                $status = $late ? self::PACKAGE_BOOTED : self::PACKAGE_BOOTED_EARLY;
                $this->updateProviderStatus($bootable, $status);
            }

            if ($toBootLater && !$toBootNow) {
                $toBootLater->enqueue($bootable);
            }
        }

        $this->bootable = $toBootLater;

        if (!$veryEarly) {
            $status = $late ? self::STATUS_BOOTSTRAPPED : self::STATUS_BOOTSTRAPPED_EARLY;
            $this->updateAppStatus($status);
        }
    }

    /**
     * @param ServiceProvider $provider
     * @return bool
     */
    private function registerProvider(ServiceProvider $provider): bool
    {
        $registered = $provider->register($this->container);
        $this->registered[$provider->id()] = true;

        do_action(self::ACTION_REGISTERED_PROVIDER, $provider->id(), $this, $registered);

        return $registered;
    }

    /**
     * @param int $status
     */
    private function updateAppStatus(int $status): void
    {
        if ($this->settings[self::_DEBUG_ENABLED]) {
            $this->settings[self::_STATUS] = $status;
        }
    }

    /**
     * @param ServiceProvider $provider
     * @param string $status
     */
    private function updateProviderStatus(ServiceProvider $provider, string $status): void
    {
        if (!$this->settings[self::_DEBUG_ENABLED]) {
            return;
        }

        isset($this->settings[self::_PROVIDERS_STATUS]) or $this->settings[self::_PROVIDERS_STATUS] = [];
        $this->settings[self::_PROVIDERS_STATUS][$provider->id()] = $status;
    }
}
