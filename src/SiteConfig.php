<?php # -*- coding: utf-8 -*-

namespace Inpsyde\App;

interface SiteConfig
{
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
