<?php

declare(strict_types=1);

namespace Inpsyde\App;

use Inpsyde\App\Location\GenericLocations;
use Inpsyde\App\Location\Locations;
use Inpsyde\App\Location\VipLocations;
use Inpsyde\App\Location\WpEngineLocations;

class EnvConfig implements SiteConfig
{
    public const FILTER_ENV_NAME = 'wp-app.environment';

    public const LOCAL = 'local';
    public const DEVELOPMENT = 'development';
    public const PRODUCTION = 'production';
    public const STAGING = 'staging';

    /** @psalm-suppress DeprecatedConstant */
    private const HOSTING_LOCATIONS_CLASS_MAP = [
        self::HOSTING_WPE => WpEngineLocations::class,
        self::HOSTING_VIP => VipLocations::class,
        self::HOSTING_OTHER => GenericLocations::class,
        self::HOSTING_SPACES => GenericLocations::class,
    ];

    /** @var array<string, mixed> */
    private array $data = [];
    /** list<non-empty-string> */
    private array $namespaces = [];
    private ?Locations $locations = null;
    private string $hosting = '';

    /**
     * @param string ...$namespaces
     */
    public function __construct(string ...$namespaces)
    {
        foreach ($namespaces as $namespace) {
            $trimmed = trim($namespace, '\\');
            ($trimmed !== '') and $this->namespaces[] = $trimmed;
        }
    }

    /**
     * @return Locations
     */
    public function locations(): Locations
    {
        if ($this->locations instanceof Locations) {
            return $this->locations;
        }

        $locationClassName = self::HOSTING_LOCATIONS_CLASS_MAP[$this->hosting()] ?? '';
        if (
            ($locationClassName === '')
            || !class_exists($locationClassName)
            || !is_subclass_of($locationClassName, Locations::class)
        ) {
            $locationClassName = GenericLocations::class;
        }

        /** @var callable(EnvConfig):Locations $factory */
        $factory = [$locationClassName, 'createFromConfig'];
        $this->locations = $factory($this);

        return $this->locations;
    }

    /**
     * @return string
     */
    public function hosting(): string
    {
        if ($this->hosting) {
            return $this->hosting;
        }

        $hosting = $this->get('HOSTING');
        if ($hosting && is_string($hosting)) {
            $this->hosting = $hosting;

            return $this->hosting;
        }

        if (($this->get('VIP_GO_ENV') !== null) || $this->canReadVipEnv()) {
            $this->hosting = self::HOSTING_VIP;

            return $this->hosting;
        }

        if (function_exists('is_wpe')) {
            $this->hosting = self::HOSTING_WPE;

            return $this->hosting;
        }

        if ($this->get('SPACES_SPACE_ID')) {
            /** @psalm-suppress DeprecatedConstant */
            $this->hosting = self::HOSTING_SPACES;

            return $this->hosting;
        }

        $hosting = apply_filters('wp-app.hosting', null);
        if (($hosting !== '') && is_string($hosting)) {
            $this->hosting = $hosting;

            return $this->hosting;
        }

        $this->hosting = self::HOSTING_OTHER;

        return $this->hosting;
    }

    /**
     * @param string $hosting
     * @return bool
     */
    public function hostingIs(string $hosting): bool
    {
        return strtolower($this->hosting()) === strtolower($hosting);
    }

    /**
     * @return string
     */
    public function env(): string
    {
        return wp_get_environment_type();
    }

    /**
     * @param string $env
     * @return bool
     */
    public function envIs(string $env): bool
    {
        return $this->env() === strtolower($env);
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get(string $name, $default = null)
    {
        if ($name === '') {
            return $default;
        }

        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        // Try namespaced constant
        foreach ($this->namespaces as $namespace) {
            if (defined("\\{$namespace}\\{$name}")) {
                $this->data[$name] = constant("\\{$namespace}\\{$name}");

                return $this->data[$name];
            }
        }

        // Try env var or global constant
        $env = $this->readEnvVarOrConstant($name);
        if ($env !== null) {
            $this->data[$name] = $env;

            return $env;
        }

        $this->data[$name] = $default;

        return $default;
    }

    /**
     * @return array{env:string, hosting:string, keys:array<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'env' => $this->env(),
            'hosting' => $this->hosting(),
            'keys' => array_keys($this->data),
        ];
    }

    /**
     * @param string $name
     * @return mixed
     */
    private function readEnvVarOrConstant(string $name)
    {
        if (defined($name)) {
            return constant($name);
        }

        /** @psalm-suppress UndefinedClass */
        if ($this->canReadVipEnv() && \Automattic\VIP\Environment::has_var($name)) {
            return \Automattic\VIP\Environment::get_var($name);
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput
        $value = $_ENV[$name] ?? null;
        if (($value === null) && stripos($name, 'HTTP_') !== 0) {
            $value = $_SERVER[$name] ?? null;
        }
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput

        if (($value === null) && ((PHP_SAPI === 'cli') || (PHP_SAPI === 'cli-server'))) {
            $value = getenv($name);
            ($value === false) and $value = null;
        }

        return $value;
    }

    /**
     * @return bool
     */
    private function canReadVipEnv(): bool
    {
        static $hasVipEnv;
        isset($hasVipEnv) or $hasVipEnv = class_exists(\Automattic\VIP\Environment::class);
        /** @var bool */
        return $hasVipEnv;
    }
}
