<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Provider;

use Inpsyde\App\App;
use Inpsyde\App\Container;

class ConfigurableProvider implements ServiceProvider
{
    public const REGISTER_LATER = 16;
    public const BOOT_EARLY = 32;

    /**
     * @var string
     */
    private $id;

    /**
     * @var callable|null
     */
    private $register;

    /**
     * @var callable|null
     */
    private $boot;

    /**
     * @var int
     */
    private $flags;

    /**
     * @param string $id
     * @param callable|null $register
     * @param callable|null $boot
     * @param int $flags
     */
    public function __construct(
        string $id,
        callable $register = null,
        callable $boot = null,
        int $flags = 0
    ) {

        $this->id = $id;
        $this->register = $register;
        $this->boot = $boot;
        $this->flags = $flags;
    }

    /**
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function registerLater(): bool
    {
        return ($this->flags & self::REGISTER_LATER) === self::REGISTER_LATER;
    }

    /**
     * @return bool
     */
    public function bootEarly(): bool
    {
        return ($this->flags & self::BOOT_EARLY) === self::BOOT_EARLY;
    }

    /**
     * @param Container $container
     * @return bool
     */
    public function register(Container $container): bool
    {
        try {
            // @phan-suppress-next-line PhanTypePossiblyInvalidCallable
            return $this->register ? (bool)(($this->register)($container)) : false;
        } catch (\Throwable $throwable) {
            App::handleThrowable($throwable);
        }

        return false;
    }

    /**
     * @param Container $container
     * @return bool
     */
    public function boot(Container $container): bool
    {
        try {
            // @phan-suppress-next-line PhanTypePossiblyInvalidCallable
            return $this->boot ? (bool)(($this->boot)($container)) : false;
        } catch (\Throwable $throwable) {
            App::handleThrowable($throwable);
        }

        return false;
    }
}
