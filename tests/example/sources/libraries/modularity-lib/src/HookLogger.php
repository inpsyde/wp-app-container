<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\ModularityLib;

use Inpsyde\App\Tests\Project\WacLib\Logger;

class HookLogger
{
    /**
     * @var string
     */
    private $prefix;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param string $prefix
     * @param Logger $logger
     * @return HookLogger
     */
    public static function new(string $prefix, Logger $logger): HookLogger
    {
        return new self($prefix, $logger);
    }

    /**
     * @param string $prefix
     * @param Logger $logger
     */
    private function __construct(string $prefix, Logger $logger)
    {
        $this->prefix = $prefix;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function logHook(): void
    {
        $message = sprintf(
            '-- APPLICATION HOOK %s from %s --',
            strtoupper(current_action()),
            $this->prefix
        );

        $this->logger->debug($message);
    }
}
