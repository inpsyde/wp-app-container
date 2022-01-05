<?php

declare(strict_types=1);

namespace Inpsyde\App;

use Inpsyde\App\Provider\Package;
use Inpsyde\App\Provider\ServiceProvider;
use Inpsyde\Modularity;
use Inpsyde\WpContext;
use Psr\Container\ContainerInterface;

final class App
{
    public const ACTION_ADD_PROVIDERS = 'wp-app.add-providers';
    public const ACTION_REGISTERED = 'wp-app.all-providers-registered';
    public const ACTION_BOOTED = 'wp-app.all-providers-booted';
    public const ACTION_ADDED_MODULE = 'wp-app.module-added';
    public const ACTION_BOOTED_MODULE = 'wp-app.module-booted';
    public const ACTION_ERROR = 'wp-app.error';

    private const PACKAGE_INTERNAL_EARLY = 1;
    private const PACKAGE_INTERNAL = 2;
    private const PACKAGE_EXTERNAL = 16;
    private const PACKAGE_EXTERNAL_RESERVED = 32;

    private const EVENTS_KEY = 'events';
    private const PACKAGES_KEY = 'packages';

    /**
     * @var array{context:WpContext, config: Config\Config, debug: bool | null, booting: bool}
     */
    private $props;

    /**
     * @var AppStatus
     */
    private $status;

    /**
     * @var \SplObjectStorage<Modularity\Package, int>|null
     */
    private $bootQueue;

    /**
     * @var \SplObjectStorage<ServiceProvider, list<string>>|null
     */
    private $deferredProviders;

    /**
     * @var CompositeContainer
     */
    private $container;

    /**
     * @var Modularity\Package|null
     */
    private $modularity = null;

    /**
     * @var Modularity\Package|null
     */
    private $modularityForEarly = null;

    /**
     * @var array{status?:string, packages?:array, events?:array, context?:array, config?:array}
     */
    private $modulesDebug = [];

    /**
     * @param Config\Config|null $config
     * @param ContainerInterface|null $container
     * @param WpContext|null $context
     * @return App
     */
    public static function new(
        ?Config\Config $config = null,
        ?ContainerInterface $container = null,
        ?WpContext $context = null
    ): App {

        return new App($config, $container, $context);
    }

    /**
     * @param \Throwable $throwable
     * @param bool|null $reThrow
     */
    public static function handleThrowable(\Throwable $throwable, ?bool $reThrow = null): void
    {
        do_action(self::ACTION_ERROR, $throwable);

        if ($reThrow ?? (defined('WP_DEBUG') && WP_DEBUG)) {
            throw $throwable;
        }
    }

    /**
     * @param Config\Config|null $config
     * @param ContainerInterface|null $container
     * @param WpContext|null $context
     */
    private function __construct(
        ?Config\Config $config,
        ?ContainerInterface $container,
        ?WpContext $context
    ) {

        $container or $container = CompositeContainer::new();
        if (!($container instanceof CompositeContainer)) {
            $container = CompositeContainer::new()->addContainer($container);
        }

        $this->container = $container;
        $this->status = AppStatus::new();

        $this->props = [
            'config' => $config ?? new Config\EnvConfig(),
            'context' => $context ?? WpContext::determine(),
            'debug' => null,
            'booting' => false,
        ];
    }

    /**
     * @param string $hook
     * @return static
     */
    public function runLastBootAt(string $hook): App
    {
        try {
            $this->status = $this->status->lastStepOn($hook);
        } catch (\Throwable $throwable) {
            static::handleThrowable($throwable, $this->isDebug());
        }

        return $this;
    }

    /**
     * @return App
     */
    public function enableDebug(): App
    {
        $this->props['debug'] = true;

        return $this;
    }

    /**
     * @return App
     */
    public function disableDebug(): App
    {
        $this->props['debug'] = false;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        if ($this->props['debug'] === null) {
            $this->props['debug'] = defined('WP_DEBUG') && WP_DEBUG;
        }

        return $this->props['debug'];
    }

    /**
     * @return array{status:string, packages:array, events:array, context:array, config:array}
     */
    public function debugInfo(): array
    {
        return [
            'status' => (string)$this->status,
            'packages' => (array)($this->modulesDebug[self::PACKAGES_KEY] ?? []),
            'events' => (array)($this->modulesDebug[self::EVENTS_KEY] ?? []),
            'context' => $this->props['context']->jsonSerialize(),
            'config' => (array)($this->props['config']->jsonSerialize()),
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
     * @return Config\Config
     */
    public function config(): Config\Config
    {
        return $this->props['config'];
    }

    /**
     * @return WpContext
     */
    public function context(): WpContext
    {
        $context = $this->props['context'];

        return clone $context;
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        try {
            if ($this->props['booting']) {
                throw new \Error("Can't call App::boot() when already booting.");
            }

            $this->status = $this->status->next($this); // registering

            $this->fireHook(self::ACTION_ADD_PROVIDERS, $this->status);

            $lastRun = $this->status->isThemesStep();

            if ($lastRun) {
                $this->registerDeferredProviders();

                do_action(self::ACTION_REGISTERED);

                if ($this->modularity) {
                    $this->pushToBootQueue($this->modularity, self::PACKAGE_INTERNAL);
                }
            }

            $this->status = $this->status->next($this); // booting

            $this->bootTheQueue();

            $this->status = $this->status->next($this); // booted

            $lastRun and do_action(self::ACTION_BOOTED, $this->container);
            //
        } catch (\Throwable $throwable) {
            static::handleThrowable($throwable, $this->isDebug());
        } finally {
            // If exception has been caught, ensure status is booted, so next `boot()` will not fail
            if ($this->status->isRegistering() || $this->status->isBooting()) {
                $this->status = $this->status->next($this);
            }

            // Remove the actions to prevent duplicate registration.
            remove_all_actions(self::ACTION_ADD_PROVIDERS);
        }
    }

    /**
     * @param string $moduleId
     * @param string ...$moduleIds
     * @return bool
     */
    public function hasModules(string $moduleId, string ...$moduleIds): bool
    {
        array_unshift($moduleIds, $moduleId);

        $toFound = $moduleIds;
        /** @var array $packageModules */
        foreach ($this->modulesDebug[self::PACKAGES_KEY] ?? [] as $packageModules) {
            /** @var array $added */
            $added = $packageModules[Modularity\Package::MODULE_ADDED] ?? [];
            $toFound = array_diff($toFound, $added);
            if (!$toFound) {
                break;
            }
        }

        return !$toFound;
    }

    /**
     * @param ServiceProvider $provider
     * @param string ...$contexts
     * @return App
     */
    public function addProvider(ServiceProvider $provider, string ...$contexts): App
    {
        try {
            $early = $provider->bootEarly();
            if (!$early && $provider->registerLater()) {
                $this->deferredProviders or $this->deferredProviders = new \SplObjectStorage();
                /** @var list<string> $contexts */
                $this->deferredProviders->attach($provider, $contexts);

                return $this;
            }

            return $this->addModularityModule($early, $provider, ...$contexts);
        } catch (\Throwable $throwable) {
            static::handleThrowable($throwable, $this->isDebug());
        }

        return $this;
    }

    /**
     * @param Package $package
     * @return App
     */
    public function addProvidersPackage(Package $package): App
    {
        $package->providers()->provideTo($this);

        return $this;
    }

    /**
     * @param Modularity\Module\Module $module
     * @param string ...$contexts
     * @return App
     */
    public function addModule(Modularity\Module\Module $module, string ...$contexts): App
    {
        return $this->addModularityModule(false, $module, ...$contexts);
    }

    /**
     * @param Modularity\Module\Module $module
     * @param string ...$contexts
     * @return static
     */
    public function addEarlyModule(Modularity\Module\Module $module, string ...$contexts): App
    {
        return $this->addModularityModule(true, $module, ...$contexts);
    }

    /**
     * @param Modularity\Package $package
     * @param string ...$contexts
     * @return App
     */
    public function addPackage(Modularity\Package $package, string ...$contexts): App
    {
        return $this->addModularityPackage(self::PACKAGE_EXTERNAL_RESERVED, $package, ...$contexts);
    }

    /**
     * @param Modularity\Package $package
     * @param string ...$contexts
     * @return App
     */
    public function sharePackage(Modularity\Package $package, string ...$contexts): App
    {
        return $this->addModularityPackage(self::PACKAGE_EXTERNAL, $package, ...$contexts);
    }

    /**
     * @param string $serviceId
     * @param mixed $default
     * @return mixed
     */
    public function resolve(string $serviceId, $default = null)
    {
        try {
            return $this->container->has($serviceId) ? $this->container->get($serviceId) : $default;
        } catch (\Throwable $throwable) {
            static::handleThrowable($throwable, $this->isDebug());

            return $default;
        }
    }

    /**
     * @param bool $early
     * @return void
     */
    private function initializeModularity(bool $early = false): void
    {
        if (($early && $this->modularityForEarly) || (!$early && $this->modularity)) {
            return;
        }

        $properties = new Properties($this->config()->locations(), $this->isDebug());
        $package = Modularity\Package::new($properties, $this->container);

        $early
            ? $this->modularityForEarly = $package
            : $this->modularity = $package;

        $early and $this->pushToBootQueue($package, self::PACKAGE_INTERNAL_EARLY);
    }

    /**
     * @param Modularity\Package $package
     * @param int $type
     * @return void
     */
    private function pushToBootQueue(Modularity\Package $package, int $type): void
    {
        $this->bootQueue or $this->bootQueue = new \SplObjectStorage();
        $this->bootQueue->attach($package, $type);
    }

    /**
     * @param bool $early
     * @param Modularity\Module\Module $module
     * @param string ...$contexts
     * @return App
     */
    private function addModularityModule(
        bool $early,
        Modularity\Module\Module $module,
        string ...$contexts
    ): App {

        $id = $module->id();

        $contexts or $contexts = [WpContext::CORE];
        $wrongContext = !$this->contextIs(...$contexts);
        $alreadyAdded = !$wrongContext && $this->hasModules($id);

        if ($wrongContext || $alreadyAdded) {
            $this->moduleNotAdded($id, $wrongContext ? 'wrong context' : 'already added');

            return $this;
        }

        $this->initializeModularity($early);
        $this->ensureWillBoot();

        /** @var Modularity\Package $package */
        $package = $early ? $this->modularityForEarly : $this->modularity;
        $package->addModule($module);

        $this->syncModularityStatus($package, Modularity\Package::MODULE_ADDED);

        $this->fireHook(self::ACTION_ADDED_MODULE, $id);

        return $this;
    }

    /**
     * @param int $type
     * @param Modularity\Package $package
     * @param string ...$contexts
     * @return App
     */
    private function addModularityPackage(
        int $type,
        Modularity\Package $package,
        string ...$contexts
    ): App {

        $contexts or $contexts = [WpContext::CORE];
        $wrongContext = !$this->contextIs(...$contexts);
        $failed = !$wrongContext && $package->statusIs(Modularity\Package::STATUS_FAILED);

        $modulesStatus = $package->modulesStatus();

        if ($wrongContext || $failed) {
            $modules = (array)($modulesStatus[Modularity\Package::MODULE_ADDED] ?? []);
            $reason = $wrongContext ? 'wrong context' : 'package failed';
            foreach ($modules as $id) {
                $this->moduleNotAdded($id, $reason);
            }

            return $this;
        }

        $this->ensureWillBoot();
        $this->syncModularityStatus($package, Modularity\Package::MODULE_ADDED);
        $this->maybeSetLibraryUrl($package);

        $added = (array)($modulesStatus[Modularity\Package::MODULE_ADDED] ?? []);
        foreach ($added as $id) {
            $this->fireHook(self::ACTION_ADDED_MODULE, $id);
        }

        if ($package->statusIs(Modularity\Package::STATUS_BOOTED)) {
            if ($type !== self::PACKAGE_EXTERNAL_RESERVED) {
                $this->container->addContainer($package->container());
            }

            return $this;
        }

        $this->pushToBootQueue($package, $type);

        return $this;
    }

    /**
     * @return void
     */
    private function registerDeferredProviders(): void
    {
        if (!$this->deferredProviders) {
            return;
        }

        $this->deferredProviders->rewind();
        while ($this->deferredProviders->valid()) {
            $provider = $this->deferredProviders->current();
            $contexts = $this->deferredProviders->getInfo();
            $this->addModularityModule(false, $provider, ...$contexts);
            $this->deferredProviders->next();
        }

        $this->deferredProviders = null;
    }

    /**
     * @return void
     */
    private function bootTheQueue(): void
    {
        if (!$this->bootQueue) {
            return;
        }

        $this->bootQueue->rewind();
        while ($this->bootQueue->valid()) {
            /** @var Modularity\Package $package */
            $package = $this->bootQueue->current();
            $type = $this->bootQueue->getInfo();

            $this->bootQueue->next();

            $booted = $package->boot();

            $this->syncModularityStatus($package, Modularity\Package::MODULE_EXECUTED);

            if (!$booted) {
                continue;
            }

            if ($type !== self::PACKAGE_EXTERNAL_RESERVED) {
                $this->container->addContainer($package->container());
            }

            $statuses = $package->modulesStatus();
            $executed = (array)($statuses[Modularity\Package::MODULE_EXECUTED] ?? []);
            foreach ($executed as $id) {
                $this->fireHook(self::ACTION_BOOTED_MODULE, $id);
            }
        }

        $this->bootQueue = null;
        $this->modularityForEarly = null;
    }

    /**
     * @return void
     */
    private function ensureWillBoot(): void
    {
        static $ensure;
        $ensure or $ensure = add_action(
            'init',
            function (): void {
                $this->status->isIdle() and $this->boot();
            },
            AppStatus::BOOT_HOOK_PRIORITY + 10
        );
    }

    /**
     * @param string $moduleId
     * @param string $reason
     * @return void
     */
    private function moduleNotAdded(string $moduleId, string $reason)
    {
        if (!isset($this->modulesDebug[self::EVENTS_KEY])) {
            $this->modulesDebug[self::EVENTS_KEY] = [];
        }

        $this->modulesDebug[self::EVENTS_KEY][] = "Module {$moduleId} not added ({$reason}).";
    }

    /**
     * @param Modularity\Package $package
     * @param string $statusKey
     * @return void
     *
     * @psalm-suppress MixedArrayAssignment
     * @psalm-suppress MixedArrayAccess
     */
    private function syncModularityStatus(Modularity\Package $package, string $statusKey): void
    {
        $packageId = $package->name();

        if (!isset($this->modulesDebug[self::PACKAGES_KEY])) {
            $this->modulesDebug[self::PACKAGES_KEY] = [];
        }
        if (!isset($this->modulesDebug[self::PACKAGES_KEY][$packageId])) {
            $this->modulesDebug[self::PACKAGES_KEY][$packageId] = [];
        }

        $modularityStatuses = $package->modulesStatus();
        $moduleIds = $modularityStatuses[$statusKey] ?? [];

        /** @var array $status */
        $status = $this->modulesDebug[self::PACKAGES_KEY][$packageId][$statusKey] ?? [];
        if ($moduleIds && !$status) {
            $this->modulesDebug[self::PACKAGES_KEY][$packageId][$statusKey] = [];
        }
        foreach ($moduleIds as $moduleId) {
            if (!in_array($moduleId, $status, true)) {
                $this->modulesDebug[self::PACKAGES_KEY][$packageId][$statusKey][] = $moduleId;
            }
        }

        $this->syncModularityPackageStatus($modularityStatuses, $packageId);
    }

    /**
     * @param array $modularityStatuses
     * @param string $packageId
     * @return void
     */
    private function syncModularityPackageStatus(array $modularityStatuses, string $packageId): void
    {
        if (!$this->isDebug()) {
            return;
        }

        $packageEvents = $modularityStatuses[Modularity\Package::MODULES_ALL] ?? [];
        if ($packageEvents && !isset($this->modulesDebug[self::EVENTS_KEY])) {
            $this->modulesDebug[self::EVENTS_KEY] = [];
        }
        foreach ($packageEvents as $event) {
            $fullEvent = "Module {$event} (Package: {$packageId})";
            /** @psalm-suppress PossiblyUndefinedArrayOffset */
            if (!in_array($fullEvent, $this->modulesDebug[self::EVENTS_KEY], true)) {
                $this->modulesDebug[self::EVENTS_KEY][] = $fullEvent;
            }
        }
    }

    /**
     * @param Modularity\Package $package
     * @return void
     */
    private function maybeSetLibraryUrl(Modularity\Package $package): void
    {
        $properties = $package->properties();
        if (
            !($properties instanceof Modularity\Properties\LibraryProperties)
            || ($properties->baseUrl() !== null)
        ) {
            return;
        }

        $pathParts = explode('/', $properties->basePath());
        $relativePath = implode('/', array_slice($pathParts, -2)); // "some-vendor/some-package"
        $locations = $this->config()->locations();
        $vendorPath = $locations->vendorDir($relativePath);
        if ($vendorPath && is_dir($vendorPath)) {
            $vendorUrl = $locations->vendorUrl($relativePath);
            $vendorUrl and $properties->withBaseUrl($vendorUrl);
        }
    }

    /**
     * @param string $hook
     * @param list<mixed> $params
     * @return void
     *
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    private function fireHook(string $hook, ...$params): void
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        ($hook === self::ACTION_ADD_PROVIDERS)
            ? array_unshift($params, $this)
            : $params[] = $this;

        // "Backup" current booting prop value, then force it to true.
        $wasBooting = $this->props['booting'];
        $this->props['booting'] = true;

        // Fire the hook after having ensured $this->booting is true, to prevent calls to methods
        // which could cause infinite recursion
        do_action($hook, ...$params);

        // Restore booting prop to the value it had before this method was called.
        $this->props['booting'] = $wasBooting;
    }

    /**
     * @param string $context
     * @param string ...$contexts
     * @return bool
     */
    private function contextIs(string $context, string ...$contexts): bool
    {
        /** @var WpContext $wpContext */
        $wpContext = $this->props['context'];

        return $wpContext->is($context, ...$contexts);
    }
}
