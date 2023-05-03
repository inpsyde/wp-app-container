<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

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
        $container = CompositeContainer::new(
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

        $container = CompositeContainer::new(
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
        $containerBack = CompositeContainer::new(
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
}
