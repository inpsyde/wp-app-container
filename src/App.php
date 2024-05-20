<?php

declare(strict_types=1);

namespace Inpsyde\App;

use Inpsyde\App\Container\ContainerCollector;
use Inpsyde\App\Provider\Package;
use Inpsyde\App\Provider\ServiceProvider;
use Inpsyde\WpContext;
use Pimple\Exception\UnknownIdentifierException;

final class App
{
    public const ACTION_ADD_PROVIDERS = 'wp-app.add-providers';
    public const ACTION_ADDED_PROVIDER = 'wp-app.provider-added';
    public const ACTION_BOOTED = 'wp-app.all-providers-booted';
    public const ACTION_BOOTED_PROVIDER = 'wp-app.provider-booted';
    public const ACTION_ERROR = 'wp-app.error';
    public const ACTION_REGISTERED = 'wp-app.all-providers-registered';
    public const ACTION_REGISTERED_PROVIDER = 'wp-app.provider-registered';

    private static ?App $app = null;

    private Container $container;
    private AppLogger $logger;
    private AppStatus $status;
    private \SplQueue $bootable;
    private \SplQueue $delayed;
    private bool $booting = false;
    /** @var array<string, bool> */
    private array $providers = [];

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
     * @deprecated
     */
    public static function make(string $id)
    {
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

        /** @psalm-suppress TypeDoesNotContainType */
        if (defined('WP_DEBUG') && WP_DEBUG) {
            throw $throwable;
        }
    }

    /**
     * @param Container|null $container
     */
    private function __construct(?Container $container = null)
    {
        $this->status = AppStatus::new();
        $this->logger = AppLogger::new();
        $this->container = $container ?? new Container();
        $this->delayed = new \SplQueue();
        $this->bootable = new \SplQueue();
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
     * @param string $hook
     * @return $this
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
        return [
            'status' => (string) $this->status,
            'providers' => $this->logger->dump(),
            'context' => $this->container->context()->jsonSerialize(),
            'config' => $this->container->config()->jsonSerialize(),
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
                throw new \DomainException("Can't call App::boot() when already booting.");
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
     * @param string $serviceProviderId
     * @param string ...$serviceProviderIds
     * @return bool
     */
    public function hasProviders(string $serviceProviderId, string ...$serviceProviderIds): bool
    {
        array_unshift($serviceProviderIds, $serviceProviderId);

        foreach ($serviceProviderIds as $id) {
            if (!isset($this->providers[$id])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param ServiceProvider $provider
     * @param string ...$contexts
     * @return App
     */
    public function addProvider(ServiceProvider $provider, string ...$contexts): App
    {
        try {
            $contexts or $contexts = [WpContext::CORE];

            $providerId = $provider->id();

            if ($this->hasProviders($providerId)) {
                return $this;
            }

            if (!$this->container->context()->is(...$contexts)) {
                $this->logger->providerSkipped($provider, $this->status);

                return $this;
            }

            $this->providers[$providerId] = true;
            $this->logger->providerAdded($provider, $this->status);
            $this->fireBootingHook(self::ACTION_ADDED_PROVIDER, $providerId);

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
        $package->providers()->provideTo($this);

        return $this;
    }

    /**
     * @param string $id
     * @param mixed $default
     * @return mixed
     */
    public function resolve(string $id, $default = null)
    {
        $value = $default;

        try {
            if ($this->status->isIdle()) {
                throw new \DomainException('Can\'t resolve from an uninitialised application.');
            }

            if (!$this->container->has($id)) {
                do_action(
                    self::ACTION_ERROR,
                    new UnknownIdentifierException($id)
                );

                return $default;
            }

            $value = $this->container->get($id);
        } catch (\Throwable $throwable) {
            static::handleThrowable($throwable);
        }

        return $value;
    }

    /**
     * @return void
     */
    private function registerAndBootProviders(): void
    {
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
        if ($this->delayed->count() === 0) {
            return;
        }

        $lastRun = $this->status->isThemesStep();
        $toRegisterLater = new \SplQueue();

        while ($this->delayed->count()) {
            /** @var ServiceProvider $delayed */
            $delayed = $this->delayed->dequeue();
            ($lastRun || $delayed->bootEarly())
                ? $this->registerProvider($delayed)
                : $toRegisterLater->enqueue($delayed);
        }

        $this->delayed = $toRegisterLater;
    }

    /**
     * @return void
     */
    private function bootProviders(): void
    {
        if ($this->bootable->count() === 0) {
            return;
        }

        $lastRun = $this->status->isThemesStep();

        $toBootLater = new \SplQueue();

        while ($this->bootable->count()) {
            /** @var ServiceProvider $bootable */
            $bootable = $this->bootable->dequeue();

            if ($lastRun || $bootable->bootEarly()) {
                $this->bootProvider($bootable);
                continue;
            }

            $toBootLater->enqueue($bootable);
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
