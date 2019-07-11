<?php # -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Inpsyde\App\App;
use Inpsyde\App\AppStatus;
use Inpsyde\App\ProviderStatus;

class ProviderStatusTest extends TestCase
{
    public function testNoSkipped()
    {
        $appStatus = AppStatus::new();

        $status = ProviderStatus::new($appStatus);

        $status->nowSkipped($appStatus); // this works before just created, so idle

        static::assertStringStartsWith(ProviderStatus::SKIPPED, (string)$status);
        static::assertTrue($status->isAnyOf(ProviderStatus::SKIPPED));
        static::assertFalse($status->isAnyOf(ProviderStatus::ADDED));

        $this->expectException(\DomainException::class);

        $status->nowSkipped($appStatus); // this does not work because already skipped
    }

    public function testNowAdded()
    {
        $appStatus = AppStatus::new();

        $status = ProviderStatus::new($appStatus);

        $status->nowAdded($appStatus); // this works before just created, so idle

        static::assertStringStartsWith(ProviderStatus::ADDED, (string)$status);
        static::assertTrue($status->isAnyOf(ProviderStatus::ADDED));
        static::assertFalse($status->isAnyOf(ProviderStatus::SKIPPED));

        $this->expectException(\DomainException::class);

        $status->nowAdded($appStatus); // this does not work because already skipped
    }

    public function testNowRegisteredFailsIfNotAdded()
    {
        $appStatus = AppStatus::new();

        $status = ProviderStatus::new($appStatus);

        $this->expectException(\DomainException::class);

        $status->nowRegistered($appStatus);
    }

    public function testNowRegistered()
    {
        $appStatus = AppStatus::new();

        $status1 = ProviderStatus::new($appStatus);
        $status1->nowAdded($appStatus);

        $status2 = ProviderStatus::new($appStatus);
        $status2->nowAdded($appStatus);

        $status1->nowRegistered($appStatus, false);
        $status2->nowRegistered($appStatus, true);

        static::assertStringStartsWith(ProviderStatus::REGISTERED, (string)$status1);
        static::assertStringStartsWith(ProviderStatus::REGISTERED_DELAYED, (string)$status2);
        static::assertTrue($status1->isAnyOf(ProviderStatus::REGISTERED));
        static::assertFalse($status1->isAnyOf(ProviderStatus::REGISTERED_DELAYED));
        static::assertTrue($status2->isAnyOf(ProviderStatus::REGISTERED_DELAYED));
        static::assertFalse($status2->isAnyOf(ProviderStatus::REGISTERED));

        $this->expectException(\DomainException::class);

        $status1->nowRegistered($appStatus, false); // Fails because already registered
    }

    public function testNowBootedFailsIfNotAddedNorRegistered()
    {
        $appStatus = AppStatus::new();

        $status = ProviderStatus::new($appStatus);

        $this->expectException(\DomainException::class);

        $status->nowBooted($appStatus);
    }

    public function testNowBooted()
    {
        $appStatus = AppStatus::new();

        $status1 = ProviderStatus::new($appStatus);
        $status1->nowAdded($appStatus);

        $status2 = ProviderStatus::new($appStatus);
        $status2->nowAdded($appStatus);
        $status2->nowRegistered($appStatus);

        $status1->nowBooted($appStatus);
        $status2->nowBooted($appStatus);

        static::assertStringStartsWith(ProviderStatus::BOOTED, (string)$status1);
        static::assertStringStartsWith(ProviderStatus::BOOTED, (string)$status2);
        static::assertTrue($status1->isAnyOf(ProviderStatus::BOOTED));
        static::assertFalse($status1->isAnyOf(ProviderStatus::REGISTERED));
        static::assertTrue($status2->isAnyOf(ProviderStatus::BOOTED));
        static::assertFalse($status2->isAnyOf(ProviderStatus::ADDED));

        $this->expectException(\DomainException::class);

        $status1->nowBooted($appStatus); // Fails because already booted
    }

    /**
     * @runInSeparateProcess
     */
    public function testBootingPluginsStepWhatRegisteredForThemesCauseException()
    {
        $app = App::new();

        $appStatus1 = $app->status(); // Idle
        $appStatus2 = clone $appStatus1; // Idle

        do_action('plugins_loaded');

        $appStatus1->next($app); // Registering plugins
        $appStatus1->next($app); // Booting plugins
        $appStatus1->next($app); // Booted plugins
        $appStatus1->next($app); // Registering themes

        $appStatus2->next($app); // Registering plugins

        $status = ProviderStatus::new($appStatus1);
        $status->nowAdded($appStatus1);
        $status->nowRegistered($appStatus1);

        $this->expectException(\DomainException::class);

        $status->nowBooted($appStatus2);
    }

    /**
     * @runInSeparateProcess
     */
    public function testRegisteringEarlyStepWhatAddedForPluginsCauseException()
    {
        $app = App::new();

        $appStatus1 = $app->status(); // Idle
        $appStatus2 = clone $appStatus1; // Idle

        $appStatus1->next($app); // Registering early
        $appStatus1->next($app); // Booting early
        $appStatus1->next($app); // Booted early
        $appStatus1->next($app); // Registering plugins

        $appStatus2->next($app); // Registering early

        $status = ProviderStatus::new($appStatus1);
        $status->nowAdded($appStatus1);

        $this->expectException(\DomainException::class);

        $status->nowRegistered($appStatus2);
    }

    /**
     * @runInSeparateProcess
     */
    public function testToString()
    {
        $app = App::new();
        $appStatus = $app->status();
        do_action('plugins_loaded');

        $appStatus->next($app);

        $status = ProviderStatus::new($appStatus);

        $status->nowAdded($appStatus);
        $status->nowRegistered($appStatus);

        $appStatus->next($app);

        $status->nowBooted($appStatus);

        $regex = '~^' . ProviderStatus::BOOTED . ' \(';
        $regex .= ProviderStatus::ADDED . '(.+?)' . AppStatus::REGISTERING_PLUGINS . ', ';
        $regex .= ProviderStatus::REGISTERED . '(.+?)' . AppStatus::REGISTERING_PLUGINS . ', ';
        $regex .= ProviderStatus::BOOTED . '(.+?)' . AppStatus::BOOTING_PLUGINS . '\)$~i';

        static::assertTrue((bool)preg_match($regex, (string)$status));
    }
}
