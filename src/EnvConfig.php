<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

class EnvConfig implements SiteConfig
{
    public const DEVELOPMENT = 'development';
    public const PRODUCTION = 'production';
    public const STAGING = 'staging';

    public const FILTER_ENV_NAME = 'app-environment';

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
     * @var string
     */
    private $namespace;

    /**
     * @var string
     */
    private $altNamespace;

    /**
     * @param string $namespace
     * @param string|null $altNamespace
     */
    public function __construct(string $namespace, string $altNamespace = null)
    {
        $namespace = trim($namespace, '\\');
        $namespace and $this->namespace = $namespace;

        if ($namespace && $altNamespace === null) {
            $this->altNamespace = "{$this->namespace}\\Config";

            return;
        }

        $altNamespace = $altNamespace ? trim($altNamespace, '\\') : null;
        $altNamespace and $this->altNamespace = $altNamespace;
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
            $this->env = $this->filterEnv((string)$env);

            return $this->env;
        }

        if (function_exists('is_wpe')) {   // WP Engine legacy
            // @phan-suppress-next-line PhanUndeclaredFunction
            $env = (int)is_wpe() > 0 ? self::PRODUCTION : self::STAGING;
            $this->env = $this->filterEnv((string)$env);

            return $this->env;
        }

        $env = (defined('WP_DEBUG') && WP_DEBUG) ? self::DEVELOPMENT : self::PRODUCTION;
        $this->env = $this->filterEnv((string)$env);

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
     */
    public function get(string $name, $default = null)
    {
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        if (!$name) {
            return $default;
        }

        $constantName = strtoupper($name);

        if (array_key_exists($constantName, $this->data)) {
            return $this->data[$constantName];
        }

        // Try namespaced constant
        if ($this->namespace && defined("\\{$this->namespace}\\{$constantName}")) {
            $this->data[$constantName] = constant("\\{$this->namespace}\\{$constantName}");

            return $this->data[$constantName];
        }

        // Try alt-namespaced constant
        if ($this->altNamespace && defined("\\{$this->altNamespace}\\{$constantName}")) {
            $this->data[$constantName] = constant("\\{$this->altNamespace}\\{$constantName}");

            return $this->data[$constantName];
        }

        // Try env var or global constant
        $env = $this->readEnvVarOrConstant($constantName);
        if ($env !== null) {
            $this->data[$constantName] = $env;

            return $env;
        }

        $this->data[$constantName] = $default;

        return $default;
    }

    /**
     * @param string $name
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
            $value = getenv($name) ?: null;
        }

        return $value;
    }

    /**
     * @param string $env
     * @return string
     */
    private function filterEnv(string $env): string
    {
        // @phan-suppress-next-line PhanTypeVoidAssignment
        $filtered = apply_filters(self::FILTER_ENV_NAME, strtolower($env));

        if (($filtered !== $env) && is_string($filtered)) {
            $env = strtolower($filtered);
        }

        if (array_key_exists($env, self::ENV_ALIASES)) {
            $env = self::ENV_ALIASES[$env];
        }

        return $env;
    }
}
