<?php

declare(strict_types=1);

namespace Inpsyde\App;

use Inpsyde\App\Config\Locations;
use Inpsyde\Modularity\Properties\BaseProperties;
use Inpsyde\Modularity\Properties\LibraryProperties;

class Properties extends BaseProperties
{
    private const SUFFIXES = ['0-before-mu-plugins', '1-before-plugins', '2-plugins', '3-themes'];

    /**
     * @var AppStatus|null
     */
    private $status;

    /**
     * @param Locations $locations
     * @param AppStatus|null $status
     * @param bool $enableDebug
     * @throws \Exception
     */
    public function __construct(Locations $locations, ?AppStatus $status, bool $enableDebug)
    {
        $path = dirname(__DIR__);
        parent::__construct(
            'inpsyde-wp-app',
            $path,
            $locations->vendorUrl('inpsyde/wp-app-container'),
            LibraryProperties::new("{$path}/composer.json")->properties
        );
        $this->status = $status;
        $this->isDebug = $enableDebug;
    }

    /**
     * @return string
     */
    public function baseName(): string
    {
        $base = parent::baseName();
        if (!$this->status) {
            return $base;
        }

        $key = 0;
        switch (true) {
            case $this->status->isEarly():
                $key = 1;
                break;
            case $this->status->isPluginsStep():
                $key = 2;
                break;
            case $this->status->isThemesStep():
                $key = 3;
                break;
        }

        return "{$base}-" . self::SUFFIXES[$key];
    }
}
