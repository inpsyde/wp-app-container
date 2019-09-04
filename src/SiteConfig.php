<?php # -*- coding: utf-8 -*-

namespace Inpsyde\App;

interface SiteConfig
{

    /**
     * @return Paths
     */
    public function paths(): Paths;

    /**
     * @return string
     */
    public function hosting(): string;

    /**
     * @param string $hosting
     *
     * @return bool
     */
    public function hostingIs(string $hosting): bool;

    /**
     * @return string
     */
    public function env(): string;

    /**
     * @param string $env
     * @return bool
     */
    public function envIs(string $env): bool;

    /**
     * @param string $name
     * @param null $default
     * @return mixed|null
     *
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public function get(string $name, $default = null);
}
