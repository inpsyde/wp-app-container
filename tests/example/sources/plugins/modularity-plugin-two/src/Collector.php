<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\ModularityPlugin2;

use Inpsyde\App\Tests\Project\ModularityPlugin\Collector as BaseCollector;

class Collector
{
    /**
     * @var BaseCollector
     */
    private $collector;

    /**
     * @param BaseCollector $collector
     * @return Collector
     */
    public static function new(BaseCollector $collector): Collector
    {
        return new self($collector);
    }

    /**
     * @param BaseCollector $collector
     */
    private function __construct(BaseCollector $collector)
    {
        $this->collector = $collector;
    }

    /**
     * @param string $line
     * @return void
     */
    public function collect(string $line): void
    {
        $this->collector->collect("[From Plugin 2] $line");
    }
}
