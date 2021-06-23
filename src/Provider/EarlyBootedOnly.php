<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider;

abstract class EarlyBootedOnly implements ServiceProvider
{
    use Capabilities\AutoDiscoverIdTrait;
    use Capabilities\BootEarlyTrait;
    use Capabilities\NoRegisterTrait;
}
