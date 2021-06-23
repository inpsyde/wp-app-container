<?php

declare(strict_types=1);

namespace Inpsyde\App\Config;

class EnvConfig implements Config
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
     * @param string ...$namespaces
     */
    public function __construct(string ...$namespaces)
    {
        foreach ($namespaces as $namespace) {
            $trimmed = $namespace ? trim($namespace, '\\') : null;
            if ($trimmed && !in_array($trimmed, $this->namespaces, true)) {
                $this->namespaces[] = $trimmed;
            }
        }
    }

    /**
     * @return static
     */
    public function withLocations(Locations $locations): Config
    {
        $this->locations = $locations;

        return $this;
    }

    /**
     * @param string $name
     * @param Location $location
     * @return static
     */
    public function withLocation(string $name, Location $location): Config
    {
        $this->locations = $this->locations()->addLocation($name, $location);

        return $this;
    }

    /**
     * @return Locations
     */
    public function locations(): Locations
    {
        if (!$this->locations instanceof Locations) {
            $this->locations = Locations::fromConfig($this);
        }

        return $this->locations;
    }

    /**
     * @return string
     */
    public function env(): string
    {
        if (!$this->env) {
            $this->env = wp_get_environment_type();
        }

        return $this->env;
    }

    /**
     * @param string $env
     * @return static
     */
    public function withEnv(string $env): Config
    {
        $this->env = self::ENV_ALIASES[strtolower($env)] ?? self::PRODUCTION;

        return $this;
    }

    /**
     * @param string $env
     * @return bool
     */
    public function envIs(string $env): bool
    {
        $env = strtolower($env);

        return $this->env() === (self::ENV_ALIASES[$env] ?? $env);
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get(string $name, $default = null)
    {
        if (!$name) {
            return $default;
        }

        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        $namespaces = $this->namespaces;
        $namespaces[] = '';

        // Try constant
        foreach ($namespaces as $namespace) {
            $fqn = '\\' . ltrim("{$namespace}\\{$name}", '\\');
            if (defined($fqn)) {
                $this->set($name, constant($fqn));

                return $this->data[$name];
            }
        }

        // Try env var or global constant
        $byEnv = $this->readEnvVar($name);
        if ($byEnv !== null) {
            $this->set($name, $byEnv);

            return $byEnv;
        }

        return $default;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function set(string $name, $value): void
    {
        $name and $this->data[$name] = $value;
    }

    /**
     * @return array{env:string, keys:list<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'env' => $this->env(),
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
    private function readEnvVar(string $name)
    {
        // phpcs:enable

        $value = $_ENV[$name] ?? null;
        if (($value === null) && (stripos($name, 'HTTP_') !== 0)) {
            $value = $_SERVER[$name] ?? null; // phpcs:ignore
        }

        if ($value === null) {
            $value = getenv($name) ?: null;
        }

        return $value;
    }
}
