<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Brain\Monkey;
use Inpsyde\App\App;
use Inpsyde\WpContext;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as FrameworkTestCase;

abstract class TestCase extends FrameworkTestCase
{
    use MockeryPHPUnitIntegration;

    protected string $envType = 'local';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Monkey\Functions\stubEscapeFunctions();

        Monkey\Functions\stubs([
            'wp_get_environment_type' => fn () => $this->envType,
            'remove_all_actions' => null,
            'wp_normalize_path' => static function (string $path): string {
                return str_replace('\\', '/', $path);
            },
            'network_site_url' => static function (string $path = '/'): string {
                return 'https://example.com/' . ltrim($path, '/');
            },
            'content_url' => static function (string $path = '/'): string {
                return 'https://example.com/wp-content/' . ltrim($path, '/');
            },
        ]);

        Monkey\Actions\expectDone(App::ACTION_ERROR)
            ->with(\Mockery::type(\Throwable::class))
            ->zeroOrMoreTimes()
            ->whenHappen(static function (\Throwable $throwable): void {
                throw $throwable;
            });
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->envType = 'local';

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

    /**
     * @param string $envType
     * @return void
     */
    protected function forceEnvType(string $envType): void
    {
        $this->envType = $envType;
    }
}
