<?php # -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Inpsyde\App\AppLogger;
use Inpsyde\App\AppStatus;
use Inpsyde\App\Provider\ConfigurableProvider;
use Inpsyde\App\Provider\ServiceProvider;
use Inpsyde\App\ProviderStatus;

class AppLoggerTest extends TestCase
{
    private static function newProvider($id, $delayed = false): ServiceProvider
    {
        return new ConfigurableProvider(
            $id,
            null,
            null,
            $delayed ? ConfigurableProvider::REGISTER_LATER : 0
        );
    }

    public function testThatNoLoggingHappenIfDebugIsDisabled()
    {
        $logger = AppLogger::new();
        $logger->disableDebug();

        $status = AppStatus::new();

        $logger->providerAdded(static::newProvider('p1'), $status);
        $logger->providerAdded(static::newProvider('p2'), $status);
        $logger->providerAdded(static::newProvider('p3'), $status);

        static::assertNull($logger->dump());
    }

    public function testLoggingHappenIfDebugIsEnabled()
    {
        $logger = AppLogger::new();
        $logger->enableDebug();

        $appStatus = AppStatus::new();

        $logger->providerAdded(static::newProvider('p1'), $appStatus);
        $logger->providerRegistered(static::newProvider('p1'), $appStatus);
        $logger->providerBooted(static::newProvider('p1'), $appStatus);
        $logger->providerAdded(static::newProvider('p2'), $appStatus);
        $logger->providerRegistered(static::newProvider('p2'), $appStatus);
        $logger->providerAdded(static::newProvider('p3'), $appStatus);
        $logger->providerSkipped(static::newProvider('p4'), $appStatus);
        $logger->providerAdded(static::newProvider('p5'), $appStatus);
        $logger->providerRegistered(static::newProvider('p5', true), $appStatus);

        $dump = $logger->dump();

        static::assertSame(['p1', 'p2', 'p3', 'p4', 'p5'], array_keys($dump));

        static::assertStringStartsWith(ProviderStatus::BOOTED . ' (', $dump['p1']);
        static::assertStringStartsWith(ProviderStatus::REGISTERED . ' (', $dump['p2']);
        static::assertStringStartsWith(ProviderStatus::ADDED . ' (', $dump['p3']);
        static::assertStringStartsWith(ProviderStatus::SKIPPED . ' (', $dump['p4']);
        static::assertStringStartsWith(ProviderStatus::REGISTERED_DELAYED . ' (', $dump['p5']);
    }
}
