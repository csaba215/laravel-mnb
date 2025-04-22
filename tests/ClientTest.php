<?php

namespace Csaba215\Mnb\Laravel\Tests;

use Csaba215\Mnb\Laravel\Client;
use Csaba215\Mnb\Laravel\MnbServiceProvider;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use SoapClient;
use stdClass;

class ClientTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            MnbServiceProvider::class,
        ];
    }

    #[Test]
    public function can_parse_currencies_xml()
    {
        $client = new Client;
        $client->setCache(Cache::store());
        $currencies = new stdClass;
        $currencies->GetCurrenciesResult = '<MNBCurrencies><Currencies><Curr>HUF</Curr><Curr>EUR</Curr></Currencies></MNBCurrencies>';
        $mockClient = Mockery::mock(SoapClient::class);
        $mockClient->shouldReceive('getCurrencies')
            ->once()
            ->andReturn($currencies);

        $client->setClient($mockClient);

        $this->assertEquals(['HUF', 'EUR'], $client->currencies());
    }

    #[Test]
    public function has_currency_tests()
    {
        $client = new Client;
        $client->setCache(Cache::store());
        $currencies = new stdClass;
        $currencies->GetCurrenciesResult = '<MNBCurrencies><Currencies><Curr>HUF</Curr><Curr>EUR</Curr></Currencies></MNBCurrencies>';
        $mockClient = Mockery::mock(SoapClient::class);
        $mockClient->shouldReceive('getCurrencies')
            ->once()
            ->andReturn($currencies);

        $client->setClient($mockClient);

        $this->assertTrue($client->hasCurrency('HUF'));
        $this->assertFalse($client->hasCurrency('CHF'));
    }

    #[Test]
    public function opening_dates_tests()
    {
        $client = new Client;
        $client->setCache(Cache::store());
        $dateInterval = new stdClass;
        $dateInterval->GetDateIntervalResult = '<MNBStoredInterval><DateInterval startdate="1949-01-03" enddate="2025-04-22" /></MNBStoredInterval>';
        $mockClient = Mockery::mock(SoapClient::class);
        $mockClient->shouldReceive('GetDateInterval')
            ->times(2)
            ->andReturn($dateInterval);

        $client->setClient($mockClient);

        $this->assertEquals('2025-04-22', $client->lastOpeningDate());
        $this->assertEquals('1949-01-03', $client->firstOpeningDate());
    }

    #[Test]
    public function current_exchange_rates_test()
    {
        $client = new Client;
        $client->setCache(Cache::store());
        $currentExchangeRates = new stdClass;
        $currentExchangeRates->GetCurrentExchangeRatesResult = '<MNBCurrentExchangeRates><Day date="2025-04-22"><Rate unit="1" curr="EUR">409,24</Rate><Rate unit="1" curr="USD">355,86</Rate></Day></MNBCurrentExchangeRates>';
        $mockClient = Mockery::mock(SoapClient::class);
        $mockClient->shouldReceive('GetCurrentExchangeRates')
            ->once()
            ->andReturn($currentExchangeRates);

        $client->setClient($mockClient);
        $this->assertEquals(['EUR' => ['rate' => 409.24, 'unit' => 1], 'USD' => ['rate' => 355.86, 'unit' => 1]], $client->currentExchangeRates());
    }

    #[Test]
    public function single_exchange_rates_test()
    {
        $client = new Client;
        $client->setCache(Cache::store());
        $exchangeRate = new stdClass;
        $exchangeRate->GetExchangeRatesResult = '<MNBExchangeRates><Day date="2025-04-22"><Rate unit="1" curr="EUR">409,24</Rate></Day></MNBExchangeRates>';
        $mockClient = Mockery::mock(SoapClient::class);
        $mockClient->shouldReceive('GetExchangeRates')->with(['startDate' => '2025-04-22', 'endDate' => '2025-04-22', 'currencyNames' => 'EUR'])
            ->once()
            ->andReturn($exchangeRate);

        $client->setClient($mockClient);
        $this->assertEquals(['rate' => 409.24, 'unit' => 1], $client->exchangeRate('EUR', '2025-04-22'));
    }
}
