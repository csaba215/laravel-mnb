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
    public function currencies_tests()
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

        // call again to test cache
        $this->assertEquals(['HUF', 'EUR'], $client->currencies());

        /* test cache clearing */

        $currencies->GetCurrenciesResult = '<MNBCurrencies><Currencies><Curr>HUF</Curr><Curr>EUR</Curr><Curr>CHF</Curr></Currencies></MNBCurrencies>';
        $mockClient = Mockery::mock(SoapClient::class);
        $mockClient->shouldReceive('getCurrencies')
            ->once()
            ->andReturn($currencies);

        $client->setClient($mockClient);

        $this->travel(config('mnb-exchange.cache.minutes'))->minutes();
        $this->travel(1)->seconds();

        $this->assertEquals(['HUF', 'EUR', 'CHF'], $client->currencies());

        // call again to test cache
        $this->assertEquals(['HUF', 'EUR', 'CHF'], $client->currencies());
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

        // call them again to test cache
        $this->assertEquals('2025-04-22', $client->lastOpeningDate());
        $this->assertEquals('1949-01-03', $client->firstOpeningDate());

        /* Test cache clearing */

        $dateInterval = new stdClass;
        $dateInterval->GetDateIntervalResult = '<MNBStoredInterval><DateInterval startdate="1949-02-04" enddate="2025-05-22" /></MNBStoredInterval>';
        $mockClient = Mockery::mock(SoapClient::class);
        $mockClient->shouldReceive('GetDateInterval')
            ->times(2)
            ->andReturn($dateInterval);

        $client->setClient($mockClient);

        $this->travel(config('mnb-exchange.cache.minutes'))->minutes();
        $this->travel(1)->seconds();

        $this->assertEquals('2025-05-22', $client->lastOpeningDate());
        $this->assertEquals('1949-02-04', $client->firstOpeningDate());

        // call again to test cache
        $this->assertEquals('2025-05-22', $client->lastOpeningDate());
        $this->assertEquals('1949-02-04', $client->firstOpeningDate());

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
        // call again to test cache
        $this->assertEquals(['EUR' => ['rate' => 409.24, 'unit' => 1], 'USD' => ['rate' => 355.86, 'unit' => 1]], $client->currentExchangeRates());

        $currentExchangeRates = new stdClass;
        $currentExchangeRates->GetCurrentExchangeRatesResult = '<MNBCurrentExchangeRates><Day date="2025-04-23"><Rate unit="1" curr="EUR">401,01</Rate><Rate unit="1" curr="USD">345,86</Rate></Day></MNBCurrentExchangeRates>';
        $mockClient = Mockery::mock(SoapClient::class);
        $mockClient->shouldReceive('GetCurrentExchangeRates')
            ->once()
            ->andReturn($currentExchangeRates);
        $client->setClient($mockClient);

        $this->travel(config('mnb-exchange.cache.minutes'))->minutes();
        $this->travel(1)->seconds();

        $this->assertEquals(['EUR' => ['rate' => 401.01, 'unit' => 1], 'USD' => ['rate' => 345.86, 'unit' => 1]], $client->currentExchangeRates());
        // call again to test cache
        $this->assertEquals(['EUR' => ['rate' => 401.01, 'unit' => 1], 'USD' => ['rate' => 345.86, 'unit' => 1]], $client->currentExchangeRates());

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
        // call again to test cache
        $this->assertEquals(['rate' => 409.24, 'unit' => 1], $client->exchangeRate('EUR', '2025-04-22'));

        $exchangeRate = new stdClass;
        $exchangeRate->GetExchangeRatesResult = '<MNBExchangeRates><Day date="2025-04-22"><Rate unit="1" curr="EUR">401,01</Rate></Day></MNBExchangeRates>';
        $mockClient = Mockery::mock(SoapClient::class);
        $mockClient->shouldReceive('GetExchangeRates')->with(['startDate' => '2025-04-22', 'endDate' => '2025-04-22', 'currencyNames' => 'EUR'])
            ->once()
            ->andReturn($exchangeRate);

        $client->setClient($mockClient);

        $this->travel(config('mnb-exchange.cache.minutes'))->minutes();
        $this->travel(1)->seconds();

        $this->assertEquals(['rate' => 401.01, 'unit' => 1], $client->exchangeRate('EUR', '2025-04-22'));
        // call again to test cache
        $this->assertEquals(['rate' => 401.01, 'unit' => 1], $client->exchangeRate('EUR', '2025-04-22'));
    }
}
