<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider;

abstract class Booted implements ServiceProvider
{
    use Capabilities\AutoDiscoverIdTrait;
    use Capabilities\BootTrait;
    use Capabilities\RegisterTrait;
}
