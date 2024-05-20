<?php

declare(strict_types=1);

namespace Inpsyde\App;

use Inpsyde\App\Location\Locations;

interface SiteConfig extends \JsonSerializable
{
    public const HOSTING_VIP = 'vip';
    public const HOSTING_WPE = 'wpe';
    public const HOSTING_OTHER = 'other';
    /** @deprecated  */
    public const HOSTING_SPACES = 'spaces';

    /**
     * @return Locations
     */
    public function locations(): Locations;

    /**
     * Returns either one of the HOSTING_* constants or a different hosting name.
     * HOSTING_OTHER should be used as fallback.
     *
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
     * @param mixed $default
     * @return mixed|null
     */
    public function get(string $name, $default = null);
}
