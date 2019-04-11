<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

namespace Inpsyde\App;

final class AppStatus
{
    public const IDLE = 'Init';

    public const REGISTERING_EARLY = 'registering early';
    public const BOOTING_EARLY = 'booting early';
    public const BOOTSTRAPPED_EARLY = 'Done with early boot';

    public const REGISTERING_PLUGINS = 'registering plugins';
    public const BOOTING_PLUGINS = 'booting plugins';
    public const BOOTSTRAPPED_PLUGINS = 'Done with plugins';

    public const REGISTERING_THEMES = 'registering themes';
    public const BOOTING_THEMES = 'booting themes';
    public const BOOTSTRAPPED_THEMES = 'Done with themes';

    private const MOVE_MAP = [
        self::REGISTERING_EARLY => self::BOOTING_EARLY,
        self::BOOTING_EARLY => self::BOOTSTRAPPED_EARLY,
        self::BOOTSTRAPPED_EARLY => self::REGISTERING_PLUGINS,
        self::REGISTERING_PLUGINS => self::BOOTING_PLUGINS,
        self::BOOTING_PLUGINS => self::BOOTSTRAPPED_PLUGINS,
        self::BOOTSTRAPPED_PLUGINS => self::REGISTERING_THEMES,
        self::REGISTERING_THEMES => self::BOOTING_THEMES,
        self::BOOTING_THEMES => self::BOOTSTRAPPED_THEMES,
    ];

    /**
     * @var int
     */
    private $status;

    /**
     * @var string
     */
    private $lastStepHook = 'init';

    /**
     * @return AppStatus
     */
    public static function new(): AppStatus
    {
        return new static();
    }

    private function __construct()
    {
        $this->status = self::IDLE;
    }

    /**
     * @return AppStatus
     */
    public function lastStepOn(string $hook): AppStatus
    {
        if ($this->status !== self::IDLE) {
            throw new \DomainException("Last boot step has to be before App is initialized.");
        }

        $this->lastStepHook = $hook;

        return $this;
    }

    /**
     * @param App $app
     * @return AppStatus
     */
    public function next(App $app): AppStatus
    {
        if ($this->status === self::IDLE) {
            return $this->initialize($app);
        }

        $status = self::MOVE_MAP[$this->status] ?? null;

        if ($status === null) {
            throw new \DomainException("Can't move out of status '{$this->status}'.");
        }

        $this->status = $status;

        return $this;
    }

    /**
     * @return bool
     */
    public function isIdle(): bool
    {
        return $this->status === self::IDLE;
    }

    /**
     * @return bool
     */
    public function isEarly(): bool
    {
        return in_array(
            $this->status,
            [self::REGISTERING_EARLY, self::BOOTING_EARLY, self::BOOTSTRAPPED_EARLY],
            true
        );
    }

    /**
     * @return bool
     */
    public function isPluginsStep(): bool
    {
        return in_array(
            $this->status,
            [self::REGISTERING_PLUGINS, self::BOOTING_PLUGINS, self::BOOTSTRAPPED_PLUGINS],
            true
        );
    }

    /**
     * @return bool
     */
    public function isThemesStep(): bool
    {
        return in_array(
            $this->status,
            [self::REGISTERING_THEMES, self::BOOTING_THEMES, self::BOOTSTRAPPED_THEMES],
            true
        );
    }

    /**
     * @return bool
     */
    public function isRegistering(): bool
    {
        return in_array(
            $this->status,
            [self::REGISTERING_EARLY, self::REGISTERING_PLUGINS, self::REGISTERING_THEMES],
            true
        );
    }

    /**
     * @return bool
     */
    public function isBooting(): bool
    {
        return in_array(
            $this->status,
            [self::BOOTING_EARLY, self::BOOTING_PLUGINS, self::BOOTING_THEMES],
            true
        );
    }

    /**
     * @return bool
     */
    public function isBootstrapped(): bool
    {
        return in_array(
            $this->status,
            [self::BOOTSTRAPPED_EARLY, self::BOOTSTRAPPED_PLUGINS, self::BOOTSTRAPPED_THEMES],
            true
        );
    }

    /**
     * @return bool
     */
    public function isDone(): bool
    {
        return $this->status === self::BOOTSTRAPPED_THEMES;
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
        return $this->status;
    }

    /**
     * @param App $app
     * @return AppStatus
     */
    private function initialize(App $app): AppStatus
    {
        $doingLast = doing_action($this->lastStepHook);
        if (did_action($this->lastStepHook) && !$doingLast) {
            $message = 'It is too late to initialize the app.';
            $filter = current_filter();
            $filter and $message .= " WordPress is at {$filter} hook.";

            throw new \Exception($message);
        }

        if (!did_action('plugins_loaded')) {
            // If app is booted before "plugins_loaded", we boot it again on plugins loaded.
            add_action('plugins_loaded', [$app, 'boot'], PHP_INT_MAX);

            $this->status = self::REGISTERING_EARLY;

            return $this;
        }

        // App is booted before last step hook, we will boot it again on last step hook.
        $doingLast or add_action($this->lastStepHook, [$app, 'boot'], PHP_INT_MAX);

        $this->status = $doingLast ? self::REGISTERING_THEMES : self::REGISTERING_PLUGINS;

        return $this;
    }
}
