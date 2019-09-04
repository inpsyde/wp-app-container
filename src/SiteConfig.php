<?php # -*- coding: utf-8 -*-

namespace Inpsyde\App;

use Inpsyde\App\Location\Locations;

interface SiteConfig
{
    // hosting solutions
    public const HOSTING_VIP = 'vip';
    public const HOSTING_WPE = 'wpe';
    public const HOSTING_SPACES = 'spaces';
    public const HOSTING_OTHER = 'other';

    /**
     * @return Locations
     */
    public function locations(): Locations;

    /**
     * Returns a string which contains the name of the hosting provider. See also constants HOSTING_*
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
