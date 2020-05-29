<?php

declare(strict_types=1);

namespace Inpsyde\App;

use Inpsyde\App\Provider\ServiceProvider;

final class AppLogger
{
    /**
     * @var bool
     */
    private $debugEnabled;

    /**
     * @var array<string, ProviderStatus>
     */
    private $providers = [];

    /**
     * @return AppLogger
     */
    public static function new(): AppLogger
    {
        return new static();
    }

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
     * @param AppStatus $appStatus
     * @return void
     */
    public function providerSkipped(ServiceProvider $provider, AppStatus $appStatus): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        /** @var ProviderStatus $status */
        $status = $this->providers[$provider->id()] ?? ProviderStatus::new($appStatus);
        $this->providers[$provider->id()] = $status->nowSkipped($appStatus);
    }

    /**
     * @param ServiceProvider $provider
     * @param AppStatus $appStatus
     * @return void
     */
    public function providerAdded(ServiceProvider $provider, AppStatus $appStatus): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        /** @var ProviderStatus $status */
        $status = $this->providers[$provider->id()] ?? ProviderStatus::new($appStatus);
        $this->providers[$provider->id()] = $status->nowAdded($appStatus);
    }

    /**
     * @param ServiceProvider $provider
     * @param AppStatus $appStatus
     * @return void
     */
    public function providerRegistered(ServiceProvider $provider, AppStatus $appStatus): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        /** @var ProviderStatus $status */
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

        /** @var ProviderStatus $status */
        $status = $this->providers[$provider->id()] ?? ProviderStatus::new($appStatus);
        $this->providers[$provider->id()] = $status->nowBooted($appStatus);
    }

    /**
     * @return array<string, array<string, string>>|null
     */
    public function dump(): ?array
    {
        if (!$this->debugEnabled) {
            return null;
        }

        $data = [];

        /**
         * @var ProviderStatus $providerStatus
         */
        foreach ($this->providers as $id => $providerStatus) {
            $data[$id] = $providerStatus->jsonSerialize();
        }

        return $data;
    }
}
