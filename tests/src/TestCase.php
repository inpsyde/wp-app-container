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

    protected $environmentType = 'development';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Monkey\Functions\stubs([
            'wp_get_environment_type' => function (): string {
                return $this->environmentType;
            },
            'set_url_scheme' => static function (string $str): string {
                return preg_replace('~^(https?:)?//~i', 'https://', $str);
            },
            'wp_normalize_path' => static function (string $str): string {
                return str_replace('\\', '/', $str);
            },
        ]);
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
