<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

use Inpsyde\App\Provider\Package;
use Inpsyde\App\Provider\ServiceProvider;

final class App
{
    public const ACTION_ADD_PROVIDERS = 'wp-app.add-providers';
    public const ACTION_REGISTERED = 'wp-app.all-providers-registered';
    public const ACTION_BOOTED = 'wp-app.all-providers-booted';
    public const ACTION_REGISTERED_PROVIDER = 'wp-app.provider-registered';
    public const ACTION_ADDED_PROVIDER = 'wp-app.provider-added';
    public const ACTION_BOOTED_PROVIDER = 'wp-app.provider-booted';
    public const ACTION_ERROR = 'wp-app.error';

    /**
     * @var App
     */
    private static $app;

    /**
     * @var Container|null
     */
    private $container;

    /**
     * @var AppLogger
     */
    private $logger;

    /**
     * @var AppStatus
     */
    private $status;

    /**
     * @var \SplQueue|null
     */
    private $bootable;

    /**
     * @var \SplQueue|null
     */
    private $delayed;

    /**
     * @var bool
     */
    private $booting = false;

    /**
     * @var array<string,bool>
     */
    private $providers = [];

    /**
     * @param Container|null $container
     * @return App
     */
    public static function new(Container $container = null): App
    {
        $app = new App($container);
        self::$app or self::$app = $app;

        return $app;
    }

    /**
     * @param string $id
     * @return mixed
     *
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     */
    public static function make(string $id)
    {
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration

        if (!self::$app) {
            static::handleThrowable(new \Exception('No valid app found.'));

            return null;
        }

        return self::$app->resolve($id);
    }

    /**
     * @param \Throwable $throwable
     */
    public static function handleThrowable(\Throwable $throwable): void
    {
        do_action(self::ACTION_ERROR, $throwable);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            throw $throwable;
        }
    }

    /**
     * @param Container|null $container
     */
    private function __construct(Container $container = null)
    {
        $this->status = AppStatus::new();
        $this->logger = AppLogger::new();
        $this->container = $container;
    }

    /**
     * @return App
     */
    public function enableDebug(): App
    {
        $this->logger->enableDebug();

        return $this;
    }

    /**
     * @return App
     */
    public function disableDebug(): App
    {
        $this->logger->disableDebug();

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
        $providers = $this->logger->dump();
        if ($providers === null) {
            return null;
        }

        return [
            'status' => (string)$this->status,
            'providers' => $providers,
        ];
    }

    /**
     * @return AppStatus
     */
    public function status(): AppStatus
    {
        return clone $this->status;
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        try {
            if ($this->booting) {
                throw new \DomainException('Can\'t call App::boot() when already booting.');
            }

            $this->status = $this->status->next($this); // registering

            // Prevent anything listening self::ACTION_ADD_PROVIDERS to call boot().
            $this->booting = true;

            /**
             * Allows registration of providers via `App::addProvider()`
             */
            do_action(self::ACTION_ADD_PROVIDERS, $this, $this->status);

            $this->booting = false;

            $this->registerAndBootProviders();

            // Remove the actions to prevent duplicate registration.
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

            $providerId = $provider->id();

            if (isset($this->providers[$providerId])) {
                return $this;
            }

            // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
            if ($contexts && !$this->container->context()->is(...$contexts)) {
                $this->logger->providerSkipped($provider, $this->status);

                return $this;
            }

            $this->providers[$providerId] = true;
            $this->logger->providerAdded($provider, $this->status);
            $this->fireBootingHook(self::ACTION_ADDED_PROVIDER, $providerId);

            $provider->registerLater()
                // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
                ? $this->delayed->enqueue($provider)
                : $this->registerProvider($provider);

            // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
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
        $package->providers()->provideTo($this);

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

        $value = null;

        try {
            if ($this->status->isIdle()) {
                throw new \DomainException('Can\'t resolve from an uninitialised application.');
            }

            $this->initializeContainer();

            // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
            $value = $this->container->get($id);
            //
        } catch (\Throwable $throwable) {
            static::handleThrowable($throwable);
        }

        return $value;
    }

    /**
     * @return void
     */
    private function initializeContainer(): void
    {
        $this->container or $this->container = new Container();
        $this->delayed or $this->delayed = new \SplQueue();
        $this->bootable or $this->bootable = new \SplQueue();
    }

    /**
     * @return void
     */
    private function registerAndBootProviders(): void
    {
        $this->initializeContainer();
        $lastRun = $this->status->isThemesStep();

        try {
            $this->registerDeferredProviders();

            $this->status = $this->status->next($this); // booting

            $lastRun and do_action(self::ACTION_REGISTERED);

            $this->bootProviders();

            $this->status = $this->status->next($this); // booted

            $lastRun and do_action(self::ACTION_BOOTED, $this->container);
        } catch (\Throwable $throwable) {
            static::handleThrowable($throwable);
        } finally {
            if ($lastRun) {
                $this->providers = [];

                return;
            }
            // If exception has been caught, ensure status is booted, so next `boot()` will not fail
            if ($this->status->isRegistering()) {
                $this->status = $this->status->next($this);
            }
            if ($this->status->isBooting()) {
                $this->status = $this->status->next($this);
            }
        }
    }

    /**
     * @return void
     */
    private function registerDeferredProviders(): void
    {
        if (!$this->delayed) {
            return;
        }

        $lastRun = $this->status->isThemesStep();
        $toRegisterLater = $lastRun ? null : new \SplQueue();

        // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
        while ($this->delayed->count()) {
            /** @var ServiceProvider $delayed */
            // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
            $delayed = $this->delayed->dequeue();
            $toRegisterNow = $lastRun || $delayed->bootEarly();
            $toRegisterNow and $this->registerProvider($delayed);

            if ($toRegisterLater && !$toRegisterNow) {
                $toRegisterLater->enqueue($delayed);
            }
        }

        $this->delayed = $toRegisterLater;
    }

    /**
     * @return void
     */
    private function bootProviders(): void
    {
        if (!$this->bootable) {
            return;
        }

        $lastRun = $this->status->isThemesStep();

        $toBootLater = $lastRun ? null : new \SplQueue();

        // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
        while ($this->bootable->count()) {
            /** @var ServiceProvider $bootable */
            // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
            $bootable = $this->bootable->dequeue();

            if ($lastRun || $bootable->bootEarly()) {
                $this->bootProvider($bootable);
                continue;
            }

            if ($toBootLater) {
                $toBootLater->enqueue($bootable);
            }
        }

        $this->bootable = $toBootLater;
    }

    /**
     * @param ServiceProvider $provider
     * @return void
     */
    private function registerProvider(ServiceProvider $provider): void
    {
        try {
            $this->initializeContainer();

            // @phan-suppress-next-line PhanPossiblyNullTypeArgument
            if ($provider->register($this->container)) {
                $this->fireBootingHook(self::ACTION_REGISTERED_PROVIDER, $provider->id());
                $this->logger->providerRegistered($provider, $this->status);
            }
        } catch (\Throwable $exception) {
            self::handleThrowable($exception);
        }
    }

    /**
     * @param ServiceProvider $provider
     * @return void
     */
    private function bootProvider(ServiceProvider $provider): void
    {
        $this->initializeContainer();

        // @phan-suppress-next-line PhanPossiblyNullTypeArgument
        if (!$provider->boot($this->container)) {
            return;
        }

        $this->logger->providerBooted($provider, $this->status);

        do_action(self::ACTION_BOOTED_PROVIDER, $provider->id());
    }

    /**
     * @param string $hook
     * @param string $providerId
     */
    private function fireBootingHook(string $hook, string $providerId): void
    {
        if ($this->booting) {
            do_action($hook, $providerId, $this);

            return;
        }

        $this->booting = true;
        do_action($hook, $providerId, $this);
        $this->booting = false;
    }
}
