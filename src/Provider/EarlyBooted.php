<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider;

abstract class EarlyBooted implements ServiceProvider
{
    use Capabilities\AutoDiscoverIdTrait;
    use Capabilities\BootEarlyTrait;
    use Capabilities\RegisterTrait;
}
