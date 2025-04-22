<?php

namespace Csaba215\Mnb\Laravel\Tests;

use Csaba215\Mnb\Laravel\Client;
use Csaba215\Mnb\Laravel\Facade\Mnb;
use Csaba215\Mnb\Laravel\MnbServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            MnbServiceProvider::class,
        ];
    }

    #[Test]
    public function client_is_accessible_by_facade()
    {
        $this->assertInstanceOf(Client::class, Mnb::getFacadeRoot());
    }

    #[Test]
    public function client_is_accessible_by_alias()
    {
        $this->assertInstanceOf(Client::class, $this->app->make('mnb.client'));
    }
}
