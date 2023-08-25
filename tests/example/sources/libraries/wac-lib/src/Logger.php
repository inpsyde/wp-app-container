<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\WacLib;

use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger
{
    /**
     * @var list<string>
     */
    private $logs = [];

    /**
     * @return Logger
     */
    public static function new(): Logger
    {
        return new self();
    }

    /**
     */
    private function __construct()
    {
    }

    /**
     * @param mixed $level
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, array $context = []): void
    {
        assert(is_string($message));
        $this->logs[] = $message;
    }

    /**
     * @param string $message
     * @return bool
     */
    public function hasExactLog(string $message): bool
    {
        return in_array($message, $this->logs, true);
    }

    /**
     * @param string $message
     * @return bool
     */
    public function hasLog(string $message): bool
    {
        foreach ($this->logs as $log) {
            if (trim(strtolower($log)) === trim(strtolower($message))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $regex
     * @return bool
     */
    public function hasLogLike(string $regex): bool
    {
        foreach ($this->logs as $log) {
            if (preg_match($regex, $log)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function allLogs(): array
    {
        return $this->logs;
    }
}
