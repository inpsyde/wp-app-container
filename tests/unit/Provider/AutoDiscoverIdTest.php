<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Provider;

use Inpsyde\App\Provider\RegisteredOnly;
use Inpsyde\App\Tests\TestCase;

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
    public function testFromClass(): void
    {
        $provider = new class () extends RegisteredOnly {
            public function services(): array
            {
                return [];
            }
        };

        static::assertSame(get_class($provider), $provider->id());
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

        static::assertSame(get_class($provider1), $provider1->id());
        static::assertSame(get_class($provider2), $provider2->id());
        static::assertSame(get_class($provider3), $provider3->id());
    }
}
