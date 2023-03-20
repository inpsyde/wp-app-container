<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Inpsyde\App\Config\Locations;

class TestLocations extends Locations
{
    public const VENDOR_DIR = '/var/www/wp-content/client-mu-plugins/vendor';

    protected static function findVendorDir(): ?string
    {
        return self::VENDOR_DIR;
    }
}
