<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider;

abstract class RegisteredLaterOnly implements ServiceProvider
{
    use Capabilities\AutoDiscoverIdTrait;
    use Capabilities\NoBootTrait;
    use Capabilities\RegisterLateTrait;
}
