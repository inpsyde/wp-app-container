<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Provider;

use Inpsyde\App\Container;
use Inpsyde\App\Provider\RegisteredOnly;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class AutoDiscoverIdTest extends TestCase
{
    /**
     * @test
     */
    public function testIdFromProperty(): void
    {
        $provider = new class extends RegisteredOnly {
            public $id = 'hi there';
            public function register(Container $container): bool
            {
                return false;
            }
        };

        static::assertSame('hi there', $provider->id());
    }

    /**
     * @test
     */
    public function testIdFromConstant(): void
    {
        $provider = new class extends RegisteredOnly {
            public const ID = 'constant!';
            public function register(Container $container): bool
            {
                return false;
            }
        };

        static::assertSame('constant!', $provider->id());
    }

    /**
     * @test
     */
    public function testFromClass(): void
    {
        $provider = new class extends RegisteredOnly {
            public function register(Container $container): bool
            {
                return false;
            }
        };

        static::assertSame(get_class($provider) . '_1', $provider->id());
    }

    /**
     * @test
     */
    public function testFromClassNotUnique(): void
    {
        $provider1 = new class extends RegisteredOnly {
            public function register(Container $container): bool
            {
                return false;
            }
        };

        $provider2 = clone $provider1;
        $provider3 = clone $provider1;

        $one =  $provider1->id();
        $two =  $provider2->id();
        $three =  $provider3->id();

        static::assertSame(get_class($provider1) . '_1', $one);
        static::assertSame(get_class($provider2) . '_2', $two);
        static::assertStringEndsWith(get_class($provider3) . '_3', $three);

        static::assertSame($one, $provider1->id());
        static::assertSame($one, $provider1->id());
        static::assertSame($two, $provider2->id());
        static::assertSame($two, $provider2->id());
        static::assertSame($three, $provider3->id());
        static::assertSame($three, $provider3->id());
    }

    public function testIfObjectHasNotReference(): void
    {
        $providerId1 = (new class extends RegisteredOnly {
            public function register(Container $container): bool
            {
                return true;
            }
        })->id();
        $providerId2 = (new class extends RegisteredOnly {
            public function register(Container $container): bool
            {
                return true;
            }
        })->id();

        static::assertNotSame($providerId1, $providerId2);
    }
}
