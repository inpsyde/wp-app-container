<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project;

use Inpsyde\App\Tests\Project\WacLib\Logger;
use Psr\Log\LoggerInterface;

/**
 * @runTestsInSeparateProcesses
 */
class ProjectTest extends TestCase
{
    /**
     * @test
     */
    public function executeProject(): void
    {
        $this->runWordPress();

        static::assertTrue(app()->status()->isBooted());
        static::assertTrue(app()->resolve(Logger::class)->hasLog('Result is: 49'));

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
        fwrite(STDOUT, print_r(app()->resolve(Logger::class)->allLogs(), true));
    }

    protected function checkLateModule(bool $expectToBeThere = false): void
    {
        if (!$expectToBeThere) {
            static::assertTrue(app()->status()->isBooted());
            static::assertNull(app()->resolve('dummy-test'));
            return;
        }
        static::assertNotNull(app()->resolve('dummy-test'));
        static::assertEquals(app()->resolve('dummy-test')->text(), 'Hello World');
    }

    /**
     * @return void
     */
    protected function onBeforePlugins(): void
    {
        $this->checkLateModule();
    }

    /**
     * @return void
     */
    protected function onAfterPlugins(): void
    {
        $this->checkLateModule();
    }

    /**
     * @return void
     */
    protected function onAfterTheme(): void
    {
        $this->checkLateModule();
    }

    /**
     * @return void
     */
    protected function onAfterInit(): void
    {
        /** @var Logger $logger */
        $logger = app()->resolve(LoggerInterface::class);

        $pre = 'Collecting line:';

        static::assertFalse($logger->hasLogLike('~^Collected lines:~i'));
        static::assertTrue($logger->hasLog("{$pre} 'Lorem Ipsum'"));
        static::assertTrue($logger->hasLog("{$pre} 'Dolor Sit Amet'"));
        static::assertFalse($logger->hasLog("{$pre} '[From Plugin 2] Plugin Two is Good For You'"));

        $this->checkLateModule(true);
    }

    /**
     * @return void
     */
    protected function onBeforeShutdown(): void
    {
        /** @var Logger $logger */
        $logger = app()->resolve(LoggerInterface::class);
        static::assertTrue(
            $logger->hasLog("Collecting line: '[From Plugin 2] Plugin Two is Good For You'")
        );

        $lines = [
            'Collected lines:',
            'Lorem Ipsum',
            'Dolor Sit Amet',
            '[From Plugin 2] Plugin Two is Good For You',
        ];
        static::assertTrue($logger->hasLog(implode("\n", $lines)));

        static::assertTrue($logger->hasLog('Result is: 42'));
        static::assertFalse($logger->hasLog('Result is: 49'));
    }
}
