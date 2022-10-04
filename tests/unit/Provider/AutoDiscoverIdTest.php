<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Provider;

use Inpsyde\App\Provider\RegisteredOnly;
use Inpsyde\App\Tests\TestCase;

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
        $provider = new class () extends RegisteredOnly {
            public $id = 'hi there';
            public function services(): array
            {
                return [];
            }
        };

        static::assertSame('hi there', $provider->id());
    }

    /**
     * @test
     */
    public function testIdFromConstant(): void
    {
        $provider = new class () extends RegisteredOnly {
            public const ID = 'constant!';
            public function services(): array
            {
                return [];
            }
        };

        static::assertSame('constant!', $provider->id());
    }

    /**
     * @test
     */
    public function testFromClass(): void
    {
        $provider = new class () extends RegisteredOnly {
            public function services(): array
            {
                return [];
            }
        };

        static::assertSame(get_class($provider) . '_1', $provider->id());
    }

    /**
     * @test
     */
    public function testFromClassNotUnique(): void
    {
        $provider1 = new class () extends RegisteredOnly {
            public function services(): array
            {
                return [];
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
        $provider1 = new class () extends RegisteredOnly {
            public function services(): array
            {
                return [];
            }
        };

        $provider2 = new class () extends RegisteredOnly {
            public function services(): array
            {
                return [];
            }
        };

        $provider3 = clone $provider1;
        $provider4 = clone $provider2;

        static::assertNotSame($provider1->id(), $provider2->id());
        static::assertNotSame($provider1->id(), $provider3->id());
        static::assertNotSame($provider1->id(), $provider4->id());
        static::assertNotSame($provider2->id(), $provider3->id());
        static::assertNotSame($provider2->id(), $provider4->id());
        static::assertNotSame($provider3->id(), $provider4->id());
    }
}
