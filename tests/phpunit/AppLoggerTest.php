<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Inpsyde\App\App;
use Inpsyde\App\AppLogger;
use Inpsyde\App\AppStatus;
use Inpsyde\App\Container;
use Inpsyde\App\Provider\ConfigurableProvider;
use Inpsyde\App\Provider\ServiceProvider;
use Inpsyde\App\ProviderStatus;

class AppLoggerTest extends TestCase
{
    /**
     * @test
     */
    public function testThatNoLoggingHappenIfDebugIsDisabled()
    {
        $logger = AppLogger::new();
        $logger->disableDebug();

        $status = AppStatus::new();

        $logger->providerAdded(static::factoryProvider('p1'), $status);
        $logger->providerAdded(static::factoryProvider('p2'), $status);
        $logger->providerAdded(static::factoryProvider('p3'), $status);

        static::assertNull($logger->dump());
    }

    /**
     * @test
     */
    public function testLoggingHappenIfDebugIsEnabled(): void
    {
        $logger = AppLogger::new();
        $logger->enableDebug();

        $app = App::new(new Container(null, $this->factoryContext()));
        $appStatus = AppStatus::new();
        // status: AppStatus::IDLE

        $logger->providerAdded(static::factoryProvider('p1'), $appStatus);
        $logger->providerRegistered(static::factoryProvider('p1'), $appStatus);

        $appStatus->next($app);
        // status: AppStatus::REGISTERING_EARLY

        $logger->providerAdded(static::factoryProvider('p2'), $appStatus);
        $logger->providerRegistered(static::factoryProvider('p2'), $appStatus);

        $appStatus->next($app);
        // status: AppStatus::BOOTING_EARLY

        $logger->providerAdded(static::factoryProvider('p3'), $appStatus);
        $logger->providerSkipped(static::factoryProvider('p4'), $appStatus);

        $logger->providerAdded(static::factoryProvider('p5'), $appStatus);
        $logger->providerRegistered(static::factoryProvider('p5', true), $appStatus);

        $appStatus->next($app);
        // status: AppStatus::BOOTED_EARLY

        $appStatus->next($app);
        // status: AppStatus::REGISTERING_PLUGINS

        $appStatus->next($app);
        // status: AppStatus::BOOTING_PLUGINS

        $logger->providerBooted(static::factoryProvider('p1'), $appStatus);

        $dump = $logger->dump();

        static::assertSame(
            ['p1', 'p2', 'p3', 'p4', 'p5'],
            array_keys($dump)
        );

        static::assertSame(
            [
                ProviderStatus::ADDED => AppStatus::IDLE,
                ProviderStatus::REGISTERED => AppStatus::IDLE,
                ProviderStatus::BOOTED => AppStatus::BOOTING_PLUGINS,
            ],
            $dump['p1']
        );

        static::assertSame(
            [
                ProviderStatus::ADDED => AppStatus::REGISTERING_EARLY,
                ProviderStatus::REGISTERED => AppStatus::REGISTERING_EARLY,
            ],
            $dump['p2']
        );

        static::assertSame(
            [
                ProviderStatus::ADDED => AppStatus::BOOTING_EARLY,
            ],
            $dump['p3']
        );

        static::assertSame(
            [
                ProviderStatus::SKIPPED => AppStatus::BOOTING_EARLY,
            ],
            $dump['p4']
        );

        static::assertSame(
            [
                ProviderStatus::ADDED => AppStatus::BOOTING_EARLY,
                ProviderStatus::REGISTERED_DELAYED => AppStatus::BOOTING_EARLY,
            ],
            $dump['p5']
        );
    }

    /**
     * @param string $id
     * @param bool $delayed
     * @return ServiceProvider
     */
    private static function factoryProvider(string $id, bool $delayed = false): ServiceProvider
    {
        return new ConfigurableProvider(
            $id,
            null,
            null,
            $delayed ? ConfigurableProvider::REGISTER_LATER : 0
        );
    }
}
