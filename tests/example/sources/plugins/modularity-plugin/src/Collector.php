<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\ModularityPlugin;

use Inpsyde\App\Tests\Project\WacLib\Logger;

class Collector
{
    /** @var string */
    private $output = '';
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Logger $logger
     * @return Collector
     */
    public static function new(Logger $logger): Collector
    {
        return new self($logger);
    }

    /**
     * @param Logger $logger
     */
    private function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $line
     * @return void
     */
    public function collect(string $line): void
    {
        ($this->output !== '') and $this->output .= "\n";
        $this->output .= $line;
        $this->logger->debug("Collecting line: '{$line}'");
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->output;
    }
}
