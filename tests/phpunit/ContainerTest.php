<?php # -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Brain\Monkey\Actions;
use Inpsyde\App\App;
use Inpsyde\App\Container;
use Inpsyde\App\Context;
use Inpsyde\App\EnvConfig;
use Pimple\Exception\UnknownIdentifierException;
use Psr\Container\ContainerInterface;

class ContainerTest extends TestCase
{
    /**
     * @param ContainerInterface ...$containers
     * @return Container
     */
    private static function newContainer(ContainerInterface ...$containers): Container
    {
        return new Container(new EnvConfig(__NAMESPACE__), Context::create(), ...$containers);
    }

    /**
     * @param array $things
     * @return ContainerInterface
     */
    private static function stubContainer(array $things) : ContainerInterface
    {
        return new class ($things) implements ContainerInterface {

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

            public function has($id)
            {
                return array_key_exists($id, $this->things);
            }
        };
    }

    public function testGetFailWithTypeErrorIfNeeded()
    {
        $container = static::newContainer();

        $this->expectException(\TypeError::class);

        $container->get(1);
    }

    public function testSetAndGetFromPimple()
    {
        $container = static::newContainer();

        $container->addService('foo', static function () {
            return new \ArrayObject(['bar' => 'baz']);
        });

        static::assertSame('baz', $container->get('foo')['bar']);
    }

    public function testAddServiceMakeGetReturnSameInstance()
    {
        $container = static::newContainer();

        $container->addService('foo', static function () {
            return new \ArrayObject(['bar' => 'baz']);
        });

        static::assertSame($container->get('foo'), $container->get('foo'));
    }

    public function testExtendService()
    {
        $container = static::newContainer();

        $container->addService('foo', static function () {
            return new \ArrayObject(['bar' => 'baz']);
        });

        $container->addService('x', static function () {
            return new \ArrayObject(['y' => 'z']);
        });

        $container->extendService('foo', static function (\ArrayObject $foo, Container $container) {
            $foo['x'] = $container->get('x')['y'];

            return $foo;
        });

        static::assertSame('z', $container->get('foo')['x']);
    }

    public function testAddFactoryMakeGetReturnDifferentInstances()
    {
        $container = static::newContainer();

        $container->addFactory('foo', static function () {
            return new \ArrayObject(['bar' => 'baz']);
        });

        static::assertNotSame($container->get('foo'), $container->get('foo'));
        static::assertEquals($container->get('foo'), $container->get('foo'));
    }

    public function testHasFailWithTypeErrorIfNeeded()
    {
        $container = static::newContainer();

        $this->expectException(\TypeError::class);

        $container->has(1);
    }

    public function testHasFromPimple()
    {
        $container = static::newContainer();

        $container->addService('foo', static function () {
            return new \ArrayObject(['bar' => 'baz']);
        });

        static::assertTrue($container->has('foo'));
        static::assertFalse($container->has('bar'));
    }

    public function testWithMultiContainers()
    {
        $c1 = self::stubContainer(['a' => 'A!']);
        $c2 = self::stubContainer(['b' => 'B!']);
        $c3 = self::stubContainer(['c' => 'C!']);

        $container = static::newContainer($c1, $c2);
        $container->addContainer($c3);
        $container->addService('d', function () {
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
}
