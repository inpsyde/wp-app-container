<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Brain\Monkey;
use Inpsyde\App\CompositeContainer;
use Inpsyde\App\Config\EnvConfig;
use Inpsyde\WpContext;
use Psr\Container\NotFoundExceptionInterface;

class CompositeContainerTest extends TestCase
{
    /**
     * @test
     */
    public function testEmpty(): void
    {
        $container = CompositeContainer::newWithContainers(
            WpContext::new(),
            new EnvConfig()
        );

        static::assertFalse($container->has('id'));

        $this->expectException(NotFoundExceptionInterface::class);
        $container->get('id');
    }

    /**
     * @test
     */
    public function testContainers(): void
    {
        $cont1 = new TestContainer(['a' => 'A']);
        $cont2 = new TestContainer(['b' => 'B', 'c' => 'C']);
        $cont3 = new TestContainer(['d' => null]);

        $container = CompositeContainer::newWithContainers(
            WpContext::new(),
            new EnvConfig(),
            $cont1,
            $cont2
        );

        $container->addContainer($cont3);

        foreach (range('a', 'd') as $id) {
            static::assertTrue($container->has($id));
            static::assertSame($id === 'd' ? null : strtoupper($id), $container->get($id));
        }

        static::assertFalse($container->has('e'));

        $this->expectException(NotFoundExceptionInterface::class);
        $container->get('e');
    }

    /**
     * @test
     */
    public function testFromExistingContainers(): void
    {
        $containerBack = CompositeContainer::newWithContainers(
            WpContext::new()->force(WpContext::BACKOFFICE),
            new EnvConfig(),
            new TestContainer(['a' => 'A', 'b' => 'B'])
        );

        $containerFront = CompositeContainer::newFromExisting(
            $containerBack,
            WpContext::new()->force(WpContext::FRONTOFFICE)
        );

        $containerFront->addContainer(new TestContainer(['b' => 'B!']));

        static::assertSame('A', $containerFront->get('a'));
        static::assertSame('B', $containerFront->get('b'));
        static::assertSame($containerBack->config(), $containerFront->config());
        static::assertNotSame($containerBack->context(), $containerFront->context());
        static::assertTrue($containerFront->context()->isFrontoffice());
        static::assertFalse($containerFront->context()->isBackoffice());
        static::assertTrue($containerBack->context()->isBackoffice());
        static::assertFalse($containerBack->context()->isFrontoffice());
    }

    /**
     * @test
     */
    public function testEmptyBackwardCompatible(): void
    {
        $this->stubWpContext();

        $container = CompositeContainer::new();

        static::assertFalse($container->has('id'));

        $this->expectException(NotFoundExceptionInterface::class);
        $container->get('id');
    }

    /**
     * @test
     */
    public function testContainersBackwardCompatible(): void
    {
        $this->stubWpContext();

        $cont1 = new TestContainer(['a' => 'A']);
        $cont2 = new TestContainer(['b' => 'B', 'c' => 'C']);

        $container = CompositeContainer::new($cont1, $cont2)
            ->addContainer(new TestContainer(['d' => null]));

        foreach (range('a', 'd') as $id) {
            static::assertTrue($container->has($id));
            static::assertSame($id === 'd' ? null : strtoupper($id), $container->get($id));
        }

        static::assertFalse($container->has('x'));

        $this->expectException(NotFoundExceptionInterface::class);
        $container->get('x');
    }

    private function stubWpContext()
    {
        Monkey\Functions\stubs([
            'wp_doing_ajax' => static function (): bool {
                return false;
            },
            'is_admin' => static function (): bool {
                return false;
            },
            'wp_doing_cron' => static function (): bool {
                return false;
            },
            'get_option' => static function (): bool {
                return false;
            },
            'wp_login_url' => static function (): string {
                return 'https://example.com/wp-login.php';
            },
        ]);
    }
}
