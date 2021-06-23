<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider;

abstract class RegisteredLaterEarlyBooted implements ServiceProvider
{
    use Capabilities\AutoDiscoverIdTrait;
    use Capabilities\BootEarlyTrait;
    use Capabilities\RegisterLateTrait;
}
