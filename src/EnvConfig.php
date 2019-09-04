<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

class EnvConfig implements SiteConfig
{
    // hosting solutions
    public const HOSTING_VIP = 'vip';
    public const HOSTING_WPE = 'wpe';
    public const HOSTING_SPACES = 'spaces';
    public const HOSTING_OTHER = 'other';

    public const FILTER_ENV_NAME = 'wp-app.environment';

    // environments
    public const DEVELOPMENT = 'development';
    public const PRODUCTION = 'production';
    public const STAGING = 'staging';


    private const ENV_ALIASES = [
        'dev' => self::DEVELOPMENT,
        'develop' => self::DEVELOPMENT,
        'development' => self::DEVELOPMENT,
        'prod' => self::PRODUCTION,
        'production' => self::PRODUCTION,
        'live' => self::PRODUCTION,
        'stage' => self::STAGING,
        'staging' => self::STAGING,
        'preprod' => self::STAGING,
        'pre-prod' => self::STAGING,
    ];

    /**
     * @var string
     */
    private $env;

    /**
     * @var array
     */
    private $data = [];

    /**
     * @var string[]
     */
    private $namespaces = [];

    /**
     * @var Paths
     */
    private $paths;

    /**
     * @var string
     */
    private $hosting;

    /**
     * @param string ...$namespaces
     */
    public function __construct(string ...$namespaces)
    {
        foreach ($namespaces as $namespace) {
            $trimmed = $namespace
                ? trim($namespace, '\\')
                : null;
            $trimmed and $this->namespaces[] = $trimmed;
        }
    }

    /**
     * @param Paths $paths
     *
     * @return EnvConfig
     */
    public function withPaths(Paths $paths): self
    {
        $this->paths = $paths;

        return $this;
    }

    /**
     * @return Paths
     */
    public function paths(): Paths
    {
        if (! $this->paths) {
            $this->paths = $this->hostingIs(self::HOSTING_VIP)
                ? new VipPaths()
                : new BasePaths();
        }

        return $this->paths;
    }

    /**
     * @return string
     */
    public function hosting(): string
    {
        if ($this->hosting) {
            return $this->hosting;
        }

        if ($this->get('VIP_GO_ENV')) {
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

        $this->hosting = $this->get('HOSTING')
            ?? self::HOSTING_OTHER;

        return $this->hosting;
    }

    /**
     * @param string $hosting
     *
     * @return bool
     */
    public function hostingIs(string $hosting): bool
    {
        return $this->hosting() === $hosting;
    }

    /**
     * @return string
     */
    public function env(): string
    {
        if ($this->env) {
            return $this->env;
        }

        $env = $this->readEnvVarOrConstant('WP_ENV')        // WP Starter 3 or Bedrock
            ?? $this->readEnvVarOrConstant('WORDPRESS_ENV') // WP Starter 2
            ?? $this->readEnvVarOrConstant('VIP_GO_ENV');   // VIP Go

        if ($env) {
            $this->env = $this->filterEnv((string) $env);

            return $this->env;
        }

        if (function_exists('is_wpe')) {   // WP Engine legacy
            // @phan-suppress-next-line PhanUndeclaredFunction
            $env = (int) is_wpe() > 0
                ? self::PRODUCTION
                : self::STAGING;
            $this->env = $this->filterEnv((string) $env);

            return $this->env;
        }

        $env = (defined('WP_DEBUG') && WP_DEBUG)
            ? self::DEVELOPMENT
            : self::PRODUCTION;
        $this->env = $this->filterEnv((string) $env);

        return $this->env;
    }

    /**
     * @param string $env
     *
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
     *
     * @return mixed|null
     *
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public function get(string $name, $default = null)
    {
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        if (! $name) {
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
     * @param string $name
     *
     * @return mixed
     *
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
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
            $value = getenv($name)
                ?: null;
        }

        return $value;
    }

    /**
     * @param string $env
     *
     * @return string
     */
    private function filterEnv(string $env): string
    {
        $lower = strtolower($env);
        $env = self::ENV_ALIASES[$lower] ?? $lower;

        // @phan-suppress-next-line PhanTypeVoidAssignment
        $filtered = apply_filters(self::FILTER_ENV_NAME, $env);

        if (($filtered !== $env) && is_string($filtered)) {
            $filteredLower = strtolower($filtered);
            $env = self::ENV_ALIASES[$filteredLower] ?? $filteredLower;
        }

        return $env;
    }
}
