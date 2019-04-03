<?php # -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Inpsyde\App\Container;
use Inpsyde\App\Provider\RegisteredOnly;

class AutoDiscoverIdTest extends TestCase
{

    public function testIdFromProperty()
    {
        $provider = new class extends RegisteredOnly {
            public $id = 'hi there';
            public function register(Container $container): void
            {
            }
        };

        static::assertSame('hi there', $provider->id());
    }

    public function testIdFromConstant()
    {
        $provider = new class extends RegisteredOnly {
            public const ID = 'constant!';
            public function register(Container $container): void
            {
            }
        };

        static::assertSame('constant!', $provider->id());
    }

    public function testFromClass()
    {
        $provider = new class extends RegisteredOnly {
            public function register(Container $container): void
            {
            }
        };

        static::assertStringStartsWith('class@anonymous', $provider->id());
    }

    public function testFromClassNotUnique()
    {
        $provider1 = new class extends RegisteredOnly {
            public function register(Container $container): void
            {
            }
        };

        $provider2 = clone $provider1;
        $provider3 = clone $provider1;

        $one =  $provider1->id();
        $two =  $provider2->id();
        $three =  $provider3->id();

        static::assertStringStartsWith('class@anonymous', $one);
        static::assertSame("{$one}_2", $two);
        static::assertStringEndsWith("{$one}_3", $three);

        static::assertSame($one, $provider1->id());
        static::assertSame($one, $provider1->id());
        static::assertSame($two, $provider2->id());
        static::assertSame($two, $provider2->id());
        static::assertSame($three, $provider3->id());
        static::assertSame($three, $provider3->id());
    }
}