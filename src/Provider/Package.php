<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Provider;

interface Package
{
    public function providers(): ServiceProviders;
}
