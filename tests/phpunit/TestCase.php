<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Brain\Monkey;
use Inpsyde\WpContext;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as FrameworkTestCase;

abstract class TestCase extends FrameworkTestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * @param string|null $case
     * @param bool $withCli
     * @return WpContext
     */
    protected function factoryContext(?string $case = null, bool $withCli = false): WpContext
    {
        $context = WpContext::new();
        $case or $case = WpContext::CORE;
        $context->force($case);

        return $withCli ? $context->withCli() : $context;
    }
}
