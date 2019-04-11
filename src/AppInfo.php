<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

use Inpsyde\App\Provider\ServiceProvider;

final class AppInfo
{
    /**
     * @var bool
     */
    private $debugEnabled;

    /**
     * @var array
     */
    private $providers = [];

    /**
     * @return AppInfo
     */
    public static function new(): AppInfo
    {
        return new static();
    }

    /**
     * @param string $namespace
     */
    private function __construct()
    {
        $this->debugEnabled = defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * @return void
     */
    public function enableDebug(): void
    {
        $this->debugEnabled = true;
    }

    /**
     * @return void
     */
    public function disableDebug(): void
    {
        $this->debugEnabled = false;
    }

    /**
     * @param ServiceProvider $provider
     * @param AppStatus $status
     */
    public function providerSkipped(ServiceProvider $provider, AppStatus $appStatus): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        $status = $this->providers[$provider->id()] ?? ProviderStatus::new($appStatus);
        $this->providers[$provider->id()] = $status->nowSkipped($appStatus);
    }

    /**
     * @param ServiceProvider $provider
     * @param AppStatus $status
     */
    public function providerAdded(ServiceProvider $provider, AppStatus $appStatus): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        $status = $this->providers[$provider->id()] ?? ProviderStatus::new($appStatus);
        $this->providers[$provider->id()] = $status->nowAdded($appStatus);
    }

    /**
     * @param ServiceProvider $provider
     * @param AppStatus $status
     */
    public function providerRegistered(ServiceProvider $provider, AppStatus $appStatus): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        $status = $this->providers[$provider->id()] ?? ProviderStatus::new($appStatus);

        $this->providers[$provider->id()] = $status->nowRegistered(
            $appStatus,
            $provider->registerLater()
        );
    }

    /**
     * @param ServiceProvider $provider
     */
    public function providerBooted(ServiceProvider $provider, AppStatus $appStatus): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        $status = $this->providers[$provider->id()] ?? ProviderStatus::new($appStatus);
        $this->providers[$provider->id()] = $status->nowBooted($appStatus);
    }

    /**
     * @return array|null
     */
    public function providersStatus(): ?array
    {
        if (!$this->debugEnabled) {
            return null;
        }

        return array_map('strval', $this->providers);
    }
}
