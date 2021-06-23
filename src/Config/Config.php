<?php

namespace Inpsyde\App\Config;

interface Config extends \JsonSerializable
{
    /**
     * @return Locations
     */
    public function locations(): Locations;

    /**
     * @param Locations $locations
     * @return static
     */
    public function withLocations(Locations $locations): Config;

    /**
     * @param string $name
     * @param Location $location
     * @return static
     */
    public function withLocation(string $name, Location $location): Config;

    /**
     * @return string
     */
    public function env(): string;

    /**
     * @param string $env
     * @return static
     */
    public function withEnv(string $env): Config;

    /**
     * @param string $env
     * @return bool
     */
    public function envIs(string $env): bool;

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get(string $name, $default = null);

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function set(string $name, $value): void;
}
