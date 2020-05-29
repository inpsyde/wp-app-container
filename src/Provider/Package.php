<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider;

interface Package
{
    public function providers(): ServiceProviders;
}
