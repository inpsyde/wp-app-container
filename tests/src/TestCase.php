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
            'set_url_scheme' => static function (string $url): string {
                return preg_replace('~^(https?:)?//~i', 'https://', $url);
            },
            'wp_normalize_path' => static function (string $path): string {
                $path = preg_replace('|(?<=.)/+|', '/', str_replace('\\', '/', $path));
                return (substr($path, 1, 1) === ':') ? ucfirst($path) : $path;
            },
            'get_theme_root' => static function (): string {
                return WP_CONTENT_DIR . '/themes';
            },
            'get_stylesheet_directory' => static function (): string {
                return get_theme_root() . '/my-theme/';
            },
            'network_site_url' => static function (string $path = ''): string {
                $path = ($path !== '') ? '/' . ltrim($path, '/') : '';
                return 'https://example.com' . $path;
            },
            'site_url' => static function (string $path = ''): string {
                $path = ($path !== '') ? '/' . ltrim($path, '/') : '';
                return 'https://example.com' . $path;
            },
            'content_url' => static function (string $path = ''): string {
                $path = ($path !== '') ? '/' . ltrim($path, '/') : '';
                return WP_CONTENT_URL . $path;
            },
            'get_theme_root_uri' => static function (): string {
                return content_url('themes');
            },
            'get_stylesheet_directory_uri' => static function (): string {
                return get_theme_root_uri() . '/my-theme';
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
