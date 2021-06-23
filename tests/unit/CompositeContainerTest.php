<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Inpsyde\App\CompositeContainer;
use Psr\Container\NotFoundExceptionInterface;

class CompositeContainerTest extends TestCase
{
    /**
     * @test
     */
    public function testEmpty(): void
    {
        $container = CompositeContainer::new();

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
}
