<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\WacLib;

use Inpsyde\App\Provider\ServiceProviders;

class Package implements \Inpsyde\App\Provider\Package
{
    public function providers(): ServiceProviders
    {
        return ServiceProviders::new()->add(new Provider());
    }
}
