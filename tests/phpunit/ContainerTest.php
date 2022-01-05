<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Inpsyde\App\Container;
use Inpsyde\App\EnvConfig;
use Pimple\Exception\UnknownIdentifierException;
use Psr\Container\ContainerInterface;

class ContainerTest extends TestCase
{
    /**
     * @test
     */
    public function testGetFailWithTypeErrorIfNeeded(): void
    {
        $container = $this->factoryContainer();

        $this->expectException(\TypeError::class);

        $container->get(1);
    }

    /**
     * @test
     */
    public function testSetAndGetFromPimple(): void
    {
        $container = $this->factoryContainer();

        $container->addService('foo', static function (): \ArrayObject {
            return new \ArrayObject(['bar' => 'baz']);
        });

        static::assertSame('baz', $container->get('foo')['bar']);
    }

    /**
     * @test
     */
    public function testAddServiceMakeGetReturnSameInstance(): void
    {
        $container = $this->factoryContainer();

        $container->addService('foo', static function (): \ArrayObject {
            return new \ArrayObject(['bar' => 'baz']);
        });

        static::assertSame($container->get('foo'), $container->get('foo'));
    }

    /**
     * @test
     */
    public function testExtendService(): void
    {
        $container = $this->factoryContainer();

        $container->addService('foo', static function (): \ArrayObject {
            return new \ArrayObject(['bar' => 'baz']);
        });

        $container->addService('x', static function (): \ArrayObject {
            return new \ArrayObject(['y' => 'z']);
        });

        $container->extendService(
            'foo',
            static function (\ArrayObject $foo, Container $container): \ArrayObject {
                $foo['x'] = $container->get('x')['y'];

                return $foo;
            }
        );

        static::assertSame('z', $container->get('foo')['x']);
    }

    /**
     * @test
     */
    public function testAddFactoryMakeGetReturnDifferentInstances(): void
    {
        $container = $this->factoryContainer();

        $container->addFactory('foo', static function (): \ArrayObject {
            return new \ArrayObject(['bar' => 'baz']);
        });

        static::assertNotSame($container->get('foo'), $container->get('foo'));
        static::assertEquals($container->get('foo'), $container->get('foo'));
    }

    /**
     * @test
     */
    public function testHasFailWithTypeErrorIfNeeded(): void
    {
        $container = $this->factoryContainer();

        $this->expectException(\TypeError::class);

        $container->has(1);
    }

    /**
     * @test
     */
    public function testHasFromPimple(): void
    {
        $container = $this->factoryContainer();

        $container->addService('foo', static function (): \ArrayObject {
            return new \ArrayObject(['bar' => 'baz']);
        });

        static::assertTrue($container->has('foo'));
        static::assertFalse($container->has('bar'));
    }

    /**
     * @test
     */
    public function testWithMultiContainers(): void
    {
        $cont1 = self::factoryCustomContainer(['a' => 'A!']);
        $cont2 = self::factoryCustomContainer(['b' => 'B!']);
        $cont3 = self::factoryCustomContainer(['c' => 'C!']);

        $container = $this->factoryContainer($cont1, $cont2);
        $container->addContainer($cont3);
        $container->addService('d', static function (): \ArrayObject {
            return new \ArrayObject(['d' => 'D!']);
        });

        static::assertTrue($container->has('a'));
        static::assertSame('A!', $container->get('a'));

        static::assertTrue($container->has('b'));
        static::assertSame('B!', $container->get('b'));

        static::assertTrue($container->has('c'));
        static::assertSame('C!', $container->get('c'));

        static::assertTrue($container->has('d'));
        static::assertSame('D!', $container->get('d')['d']);
    }

    /**
     * @param array $things
     * @return ContainerInterface
     */
    private static function factoryCustomContainer(array $things): ContainerInterface
    {
        return new class ($things) implements ContainerInterface
        {
            private $things;

            public function __construct(array $things)
            {
                $this->things = $things;
            }

            public function get($id)
            {
                if (!$this->has($id)) {
                    throw new UnknownIdentifierException($id);
                }

                return $this->things[$id];
            }

            public function has($id): bool
            {
                return array_key_exists($id, $this->things);
            }
        };
    }

    /**
     * @param ContainerInterface ...$containers
     * @return Container
     */
    private function factoryContainer(ContainerInterface ...$containers): Container
    {
        return new Container(new EnvConfig(__NAMESPACE__), $this->factoryContext(), ...$containers);
    }
}
