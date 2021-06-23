<?php

declare(strict_types=1);

namespace Inpsyde\App;

use Inpsyde\Modularity\Properties\BaseProperties;
use Inpsyde\Modularity\Properties\LibraryProperties;

class Properties extends BaseProperties
{
    /**
     * @param Config\Locations $locations
     * @param bool $enableDebug
     */
    public function __construct(Config\Locations $locations, bool $enableDebug)
    {
        $path = dirname(__DIR__);
        parent::__construct(
            'wp-app',
            $path,
            $locations->vendorUrl('inpsyde/wp-app-container'),
            LibraryProperties::new("{$path}/composer.json")->properties
        );
        $this->isDebug = $enableDebug;
    }
}
