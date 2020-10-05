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

    private const ENV_ALIASES = [
        'local' => self::LOCAL,
        'development' => self::DEVELOPMENT,
        'dev' => self::DEVELOPMENT,
        'develop' => self::DEVELOPMENT,
        'staging' => self::STAGING,
        'stage' => self::STAGING,
        'preprod' => self::STAGING,
        'pre-prod' => self::STAGING,
        'pre-production' => self::STAGING,
        'test' => self::STAGING,
        'uat' => self::STAGING,
        'production' => self::PRODUCTION,
        'prod' => self::PRODUCTION,
        'live' =>  self::PRODUCTION,
    ];

    private const HOSTING_LOCATIONS_CLASS_MAP = [
        self::HOSTING_WPE => WpEngineLocations::class,
        self::HOSTING_VIP => VipLocations::class,
        self::HOSTING_SPACES => GenericLocations::class,
        self::HOSTING_OTHER => GenericLocations::class,
    ];

    /**
     * @var string
     */
    private $env = '';

    /**
     * @var array<string, mixed>
     */
    private $data = [];

    /**
     * @var string[]
     */
    private $namespaces = [];

    /**
     * @var Locations|null
     */
    private $locations;

    /**
     * @var string
     */
    private $hosting = '';

    /**
     * @param string ...$namespaces
     */
    public function __construct(string ...$namespaces)
    {
        foreach ($namespaces as $namespace) {
            $trimmed = $namespace ? trim($namespace, '\\') : null;
            $trimmed and $this->namespaces[] = $trimmed;
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
            !$locationClassName
            || !class_exists($locationClassName)
            || !is_subclass_of($locationClassName, Locations::class)
        ) {
            $locationClassName = GenericLocations::class;
        }

        /** @var callable $factory */
        $factory = [$locationClassName, 'createFromConfig'];
        /** @var Locations $locations */
        $locations = $factory($this);

        $this->locations = $locations;

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

        if ($this->get('VIP_GO_ENV') !== null) {
            $this->hosting = self::HOSTING_VIP;

            return $this->hosting;
        }

        if (function_exists('is_wpe')) {
            $this->hosting = self::HOSTING_WPE;

            return $this->hosting;
        }

        if ($this->get('SPACES_SPACE_ID')) {
            $this->hosting = self::HOSTING_SPACES;

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
        if ($this->env) {
            return $this->env;
        }

        // Use WP function if we can (WP 5.5+).
        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : null;
        $env = $env
            ?? $this->readEnvVarOrConstant('WP_ENVIRONMENT_TYPE')    // WP core
            ?? $this->readEnvVarOrConstant('WP_ENV')                 // WP Starter or Bedrock
            ?? $this->readEnvVarOrConstant('WORDPRESS_ENV')          // WP Starter legacy
            ?? $this->readEnvVarOrConstant('VIP_GO_APP_ENVIRONMENT') // VIP Go
            ?? $this->readEnvVarOrConstant('VIP_GO_ENV');            // VIP Go legacy

        if ($env) {
            return $this->normalizeEnv((string)$env, false);
        }

        if (function_exists('is_wpe')) {   // WP Engine legacy
            $env = ((int)is_wpe()) > 0 ? self::PRODUCTION : self::STAGING;
            $this->env = $this->normalizeEnv((string)$env, true);

            return $this->env;
        }

        $env = (defined('WP_DEBUG') && WP_DEBUG) ? self::DEVELOPMENT : self::PRODUCTION;
        $this->env = $this->normalizeEnv((string)$env, true);

        return $this->env;
    }

    /**
     * @param string $env
     * @return bool
     */
    public function envIs(string $env): bool
    {
        $env = strtolower($env);
        if (array_key_exists($env, self::ENV_ALIASES)) {
            $env = self::ENV_ALIASES[$env];
        }

        return $this->env() === $env;
    }

    /**
     * @param string $name
     * @param null $default
     * @return mixed|null
     *
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     * @psalm-suppress MissingReturnType
     * @psalm-suppress MissingParamType
     */
    public function get(string $name, $default = null)
    {
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        if (!$name) {
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
    public function jsonSerialize()
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
     *
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     * @psalm-suppress MissingReturnType
     */
    private function readEnvVarOrConstant(string $name)
    {
        // phpcs:enable

        if (defined($name)) {
            return constant($name);
        }

        $value = $_ENV[$name] ?? null;
        if ($value === null && stripos($name, 'HTTP_') !== 0) {
            $value = $_SERVER[$name] ?? null; // phpcs:ignore
        }

        if ($value === null && (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server')) {
            $value = getenv($name) ?: null;
        }

        return $value;
    }

    /**
     * Ensures the environment is one of the four supported, to ensure it matches what
     * `wp_get_environment_type()` will return.
     * When we were not able to determine environment unequivocally we use `apply_filters` to that
     * There's one more chance for developers to define the env.
     * Even in that case the environment will be normalized ot one of the supported environments,
     * in the worst case with a fallback to production, just like WP 5.5+ does.
     *
     * @param string $env
     * @param bool $applyFilters
     * @return string
     */
    private function normalizeEnv(string $env, bool $applyFilters): string
    {
        $lower = strtolower($env);

        // When we are going to apply_filters we don't want to fallback to production already,
        // we'll do later, if needed.
        $default = $applyFilters ? $lower : self::PRODUCTION;
        $env = self::ENV_ALIASES[$lower] ?? $default;
        if (!$applyFilters) {
            return $env;
        }

        $filtered = apply_filters(self::FILTER_ENV_NAME, $env);
        if ($filtered && is_string($filtered)) {
            $env = strtolower($filtered);
        }

        return self::ENV_ALIASES[$env] ?? self::PRODUCTION;
    }
}
