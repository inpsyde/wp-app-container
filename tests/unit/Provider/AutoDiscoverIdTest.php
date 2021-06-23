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

        static::assertStringStartsWith('class@anonymous', $provider->id());
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

        static::assertStringStartsWith('class@anonymous', $one);
        static::assertStringStartsWith('class@anonymous', $two);
        static::assertStringStartsWith('class@anonymous', $three);

        static::assertNotFalse(preg_match('~^class@anonymous_[a-f0-9]+$~', $one));
        static::assertNotFalse(preg_match('~^class@anonymous_[a-f0-9]+$~', $two));
        static::assertNotFalse(preg_match('~^class@anonymous_[a-f0-9]+$~', $three));

        static::assertNotSame($one, $two);
        static::assertNotSame($one, $three);
        static::assertNotSame($two, $three);

        static::assertSame($one, $provider1->id());
        static::assertSame($one, $provider1->id());
        static::assertSame($two, $provider2->id());
        static::assertSame($two, $provider2->id());
        static::assertSame($three, $provider3->id());
        static::assertSame($three, $provider3->id());
    }
}
