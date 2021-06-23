<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider;

use Inpsyde\Modularity\Module as Modularity;

interface ServiceProvider extends
    Modularity\Module,
    Modularity\ServiceModule,
    Modularity\ExecutableModule,
    Modularity\ExtendingModule,
    Modularity\FactoryModule
{
    public function registerLater(): bool;

    public function bootEarly(): bool;
}
