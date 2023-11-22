<?php

declare(strict_types=1);

namespace Inpsyde\App;

final class ProviderStatus implements \JsonSerializable
{
    public const IDLE = 'None';
    public const ADDED = 'Added';
    public const REGISTERED = 'Registered';
    public const REGISTERED_DELAYED = 'Registered with delay';
    public const BOOTED = 'Booted';
    public const SKIPPED = 'Skipped';

    /**
     * @var string
     */
    private $status;

    /**
     * @var AppStatus
     */
    private $appStatus;

    /**
     * @var array<string, string>
     */
    private $appStatuses = [];

    /**
     * @return ProviderStatus
     */
    public static function new(AppStatus $appStatus): ProviderStatus
    {
        return new static($appStatus);
    }

    /**
     * @param AppStatus $appStatus
     */
    private function __construct(AppStatus $appStatus)
    {
        $this->status = self::IDLE;
        $this->appStatus = $appStatus;
    }

    /**
     * @param AppStatus $appStatus
     * @return ProviderStatus
     */
    public function nowSkipped(AppStatus $appStatus): ProviderStatus
    {
        if ($this->status !== self::IDLE) {
            $this->cantMoveTo(self::SKIPPED);
        }

        $this->checkAndUpdateAppStatus($appStatus, self::SKIPPED);

        $this->status = self::SKIPPED;

        return $this;
    }

    /**
     * @param AppStatus $appStatus
     * @return ProviderStatus
     */
    public function nowAdded(AppStatus $appStatus): ProviderStatus
    {
        if ($this->status !== self::IDLE && $this->status !== self::SKIPPED) {
            $this->cantMoveTo(self::ADDED);
        }

        $this->checkAndUpdateAppStatus($appStatus, self::ADDED);

        $this->status = self::ADDED;

        return $this;
    }

    /**
     * @param AppStatus $appStatus
     * @param bool $delay
     * @return ProviderStatus
     */
    public function nowRegistered(AppStatus $appStatus, bool $delay = false): ProviderStatus
    {
        if ($this->status !== self::ADDED) {
            $this->cantMoveTo(self::REGISTERED);
        }

        $status = $delay ? self::REGISTERED_DELAYED : self::REGISTERED;

        $this->checkAndUpdateAppStatus($appStatus, $status);

        $this->status = $status;

        return $this;
    }

    /**
     * @param AppStatus $appStatus
     * @return ProviderStatus
     */
    public function nowBooted(AppStatus $appStatus): ProviderStatus
    {
        $allowedFromStatuses = [self::REGISTERED, self::REGISTERED_DELAYED, self::ADDED];
        if (!in_array($this->status, $allowedFromStatuses, true)) {
            $this->cantMoveTo(self::BOOTED);
        }

        $this->checkAndUpdateAppStatus($appStatus, self::BOOTED);

        $this->status = self::BOOTED;

        return $this;
    }

    /**
     * @param string $statuses
     * @return bool
     */
    public function isAnyOf(string ...$statuses): bool
    {
        return in_array($this->status, $statuses, true);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $status = "{$this->status} (";
        foreach ($this->appStatuses as $which => $when) {
            $status .= "{$which} when {$when}, ";
        }

        return ucfirst(strtolower(rtrim($status, ', ') . ')'));
    }

    /**
     * @return array<string, string>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->appStatuses;
    }

    /**
     * @param string $desired
     * @return void
     */
    private function cantMoveTo(string $desired): void
    {
        throw new \DomainException("Can't move from status '{$this->status}' to '{$desired}'.");
    }

    /**
     * @param AppStatus $appStatus
     * @param string $status
     * @return void
     */
    private function checkAndUpdateAppStatus(AppStatus $appStatus, string $status): void
    {
        $error = sprintf(
            "Can't move to status '%s': current App status '%s', is not compatible with '%s'.",
            $status,
            (string)$this->appStatus,
            (string)$appStatus
        );

        if ($this->appStatus->isThemesStep() && !$appStatus->isThemesStep()) {
            throw new \DomainException($error);
        }

        if ($this->appStatus->isPluginsStep() && $appStatus->isEarly()) {
            throw new \DomainException($error);
        }

        $this->appStatuses[$status] = (string)$appStatus;
    }
}
