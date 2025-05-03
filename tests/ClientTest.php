<?php

namespace Csaba215\Mnb\Laravel\Tests;

use Csaba215\Mnb\Laravel\Client;
use Csaba215\Mnb\Laravel\MnbException;
use Csaba215\Mnb\Laravel\MnbServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use SoapClient;
use stdClass;

#[CoversClass(Client::class)]
#[CoversClass(MnbException::class)]
#[UsesClass(MnbServiceProvider::class)]
class ClientTest extends TestCase
{
    private Client $client;

    private SoapClient $mock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client;
        $this->client->setCache(Cache::store());
        $this->mock = Mockery::mock(SoapClient::class);
        $this->client->setClient($this->mock);
    }

    protected function getPackageProviders($app): array
    {
        return [
            MnbServiceProvider::class,
        ];
    }

    #[Test]
    public function currencies_tests()
    {
        $currencies = new stdClass;
        $currencies->GetCurrenciesResult = '<MNBCurrencies><Currencies><Curr>HUF</Curr><Curr>EUR</Curr></Currencies></MNBCurrencies>';
        $this->mock->shouldReceive('getCurrencies')
            ->once()
            ->andReturn($currencies);

        $this->assertEquals(['HUF', 'EUR'], $this->client->currencies());

        // call again to test cache
        $this->assertEquals(['HUF', 'EUR'], $this->client->currencies());

        /* test cache clearing */

        $currencies->GetCurrenciesResult = '<MNBCurrencies><Currencies><Curr>HUF</Curr><Curr>EUR</Curr><Curr>CHF</Curr></Currencies></MNBCurrencies>';
        $this->mock->shouldReceive('getCurrencies')
            ->once()
            ->andReturn($currencies);

        $this->travel(config('mnb-exchange.cache.minutes'))->minutes();
        $this->travel(1)->seconds();

        $this->assertEquals(['HUF', 'EUR', 'CHF'], $this->client->currencies());

        // call again to test cache
        $this->assertEquals(['HUF', 'EUR', 'CHF'], $this->client->currencies());
    }

    #[Test]
    public function has_currency_tests()
    {
        $currencies = new stdClass;
        $currencies->GetCurrenciesResult = '<MNBCurrencies><Currencies><Curr>HUF</Curr><Curr>EUR</Curr></Currencies></MNBCurrencies>';
        $this->mock->shouldReceive('getCurrencies')
            ->once()
            ->andReturn($currencies);

        $this->assertTrue($this->client->hasCurrency('HUF'));
        $this->assertFalse($this->client->hasCurrency('CHF'));
    }

    #[Test]
    public function opening_dates_tests()
    {
        $dateInterval = new stdClass;
        $dateInterval->GetDateIntervalResult = '<MNBStoredInterval><DateInterval startdate="1949-01-03" enddate="2025-04-22" /></MNBStoredInterval>';
        $this->mock->shouldReceive('GetDateInterval')
            ->times(2)
            ->andReturn($dateInterval);

        $this->assertEquals('2025-04-22', $this->client->lastOpeningDate());
        $this->assertEquals('1949-01-03', $this->client->firstOpeningDate());

        // call them again to test cache
        $this->assertEquals('2025-04-22', $this->client->lastOpeningDate());
        $this->assertEquals('1949-01-03', $this->client->firstOpeningDate());

        /* Test cache clearing */

        $dateInterval->GetDateIntervalResult = '<MNBStoredInterval><DateInterval startdate="1949-02-04" enddate="2025-05-22" /></MNBStoredInterval>';
        $this->mock->shouldReceive('GetDateInterval')
            ->times(2)
            ->andReturn($dateInterval);

        $this->travel(config('mnb-exchange.cache.minutes'))->minutes();
        $this->travel(1)->seconds();

        $this->assertEquals('2025-05-22', $this->client->lastOpeningDate());
        $this->assertEquals('1949-02-04', $this->client->firstOpeningDate());

        // call again to test cache
        $this->assertEquals('2025-05-22', $this->client->lastOpeningDate());
        $this->assertEquals('1949-02-04', $this->client->firstOpeningDate());

    }

    #[Test]
    public function current_exchange_rates_test()
    {
        $currentExchangeRates = new stdClass;
        $currentExchangeRates->GetCurrentExchangeRatesResult = '<MNBCurrentExchangeRates><Day date="2025-04-22"><Rate unit="1" curr="EUR">409,24</Rate><Rate unit="1" curr="USD">355,86</Rate></Day></MNBCurrentExchangeRates>';
        $this->mock->shouldReceive('GetCurrentExchangeRates')
            ->once()
            ->andReturn($currentExchangeRates);

        $this->assertEquals(['EUR' => ['rate' => 409.24, 'unit' => 1], 'USD' => ['rate' => 355.86, 'unit' => 1]], $this->client->currentExchangeRates());
        // call again to test cache
        $this->assertEquals(['EUR' => ['rate' => 409.24, 'unit' => 1], 'USD' => ['rate' => 355.86, 'unit' => 1]], $this->client->currentExchangeRates());

        $currentExchangeRates->GetCurrentExchangeRatesResult = '<MNBCurrentExchangeRates><Day date="2025-04-23"><Rate unit="1" curr="EUR">401,01</Rate><Rate unit="1" curr="USD">345,86</Rate></Day></MNBCurrentExchangeRates>';
        $this->mock->shouldReceive('GetCurrentExchangeRates')
            ->once()
            ->andReturn($currentExchangeRates);

        $this->travel(config('mnb-exchange.cache.minutes'))->minutes();
        $this->travel(1)->seconds();

        $this->assertEquals(['EUR' => ['rate' => 401.01, 'unit' => 1], 'USD' => ['rate' => 345.86, 'unit' => 1]], $this->client->currentExchangeRates());
        // call again to test cache
        $this->assertEquals(['EUR' => ['rate' => 401.01, 'unit' => 1], 'USD' => ['rate' => 345.86, 'unit' => 1]], $this->client->currentExchangeRates());
    }

    public static function singleExchangeRatesData(): array
    {
        return [
            ['<MNBExchangeRates><Day date="2025-04-22"><Rate unit="1" curr="EUR">409,24</Rate></Day></MNBExchangeRates>', '2025-04-22', 'EUR', ['rate' => 409.24, 'unit' => 1]],
            ['<MNBExchangeRates><Day date="2025-04-22"><Rate unit="1" curr="EUR">409,24</Rate></Day></MNBExchangeRates>', null, 'EUR', ['rate' => 409.24, 'unit' => 1], '<MNBStoredInterval><DateInterval startdate="1949-01-03" enddate="2025-04-22" /></MNBStoredInterval>', '2025-04-22'],
        ];
    }

    #[Test]
    #[DataProvider('singleExchangeRatesData')]
    public function single_exchange_rates_test($xml, $date, $currency, $expectation, $latestOpeningXML = null, $openingDate = null)
    {
        $openingDate = $openingDate ?? $date;
        if ($latestOpeningXML !== null) {
            $dateInterval = new stdClass;
            $dateInterval->GetDateIntervalResult = $latestOpeningXML;
            $this->mock->shouldReceive('GetDateInterval')
                ->twice()
                ->andReturn($dateInterval);
        }
        $exchangeRate = new stdClass;
        $exchangeRate->GetExchangeRatesResult = $xml;
        $this->mock->shouldReceive('GetExchangeRates')->with(['startDate' => $openingDate, 'endDate' => $openingDate, 'currencyNames' => $currency])
            ->once()
            ->andReturn($exchangeRate);

        $this->assertEquals($expectation, $this->client->exchangeRate($currency, $date));
        // call again to test cache
        $this->assertEquals($expectation, $this->client->exchangeRate($currency, $date));

        $test = rand(1, 1000);
        $exchangeRate->GetExchangeRatesResult = "<MNBExchangeRates><Day date=\"$openingDate\"><Rate unit=\"1\" curr=\"$currency\">$test</Rate></Day></MNBExchangeRates>";
        $this->mock->shouldReceive('GetExchangeRates')->with(['startDate' => $openingDate, 'endDate' => $openingDate, 'currencyNames' => $currency])
            ->once()
            ->andReturn($exchangeRate);

        $this->travel(config('mnb-exchange.cache.minutes'))->minutes();
        $this->travel(1)->seconds();

        $this->assertEquals(['rate' => $test, 'unit' => 1], $this->client->exchangeRate($currency, $date));
        // call again to test cache
        $this->assertEquals(['rate' => $test, 'unit' => 1], $this->client->exchangeRate($currency, $date));
    }

    public static function openingDatesInvalidData(): array
    {
        return [
            [''],
            ['<MNBStoredInterval></MNBStoredInterval>'],
            ['<MNBStoredInterval></MNBStoredInterval'],
        ];
    }

    #[Test]
    #[DataProvider('openingDatesInvalidData')]
    public function opening_date_invalid_data(string $xml)
    {
        $dateInterval = new stdClass;
        $dateInterval->GetDateIntervalResult = $xml;
        $this->mock->shouldReceive('GetDateInterval')
            ->times(1)
            ->andReturn($dateInterval);

        $this->expectException(MnbException::class);
        try {
            $this->client->firstOpeningDate();
        } catch (MnbException $e) {
            $this->assertEquals($xml, $e->getXML());
            throw $e;
        }
    }

    #[Test]
    #[DataProvider('openingDatesInvalidData')]
    public function closing_date_invalid_data(string $xml)
    {
        $dateInterval = new stdClass;
        $dateInterval->GetDateIntervalResult = $xml;
        $this->mock->shouldReceive('GetDateInterval')
            ->times(1)
            ->andReturn($dateInterval);
        $this->expectException(MnbException::class);

        try {
            $this->client->lastOpeningDate();
        } catch (MnbException $e) {
            $this->assertEquals($xml, $e->getXML());
            throw $e;
        }
    }

    public static function currentExchangeRatesInvalidData(): array
    {
        return [
            [''],
            ['<MNBCurrentExchangeRates></MNBCurrentExchangeRates>'],
            ['<MNBCurrentExchangeRates></MNBCurrentExchangeRates'],
        ];
    }

    #[Test]
    #[DataProvider('currentExchangeRatesInvalidData')]
    public function current_exchange_rates_invalid_xml(string $xml)
    {
        $currentExchangeRates = new stdClass;
        $currentExchangeRates->GetCurrentExchangeRatesResult = $xml;
        $this->mock->shouldReceive('GetCurrentExchangeRates')
            ->once()
            ->andReturn($currentExchangeRates);

        $this->expectException(MnbException::class);
        try {
            $this->client->currentExchangeRates();
        } catch (MnbException $e) {
            $this->assertEquals($xml, $e->getXML());
            throw $e;
        }
    }

    public static function currenciesInvalidData(): array
    {
        return [
            [''],
            ['<MNBCurrencies></MNBCurrencies>'],
            ['<MNBCurrencies></MNBCurrencies'],
        ];
    }

    #[Test]
    #[DataProvider('currenciesInvalidData')]
    public function currencies_invalid_xml(string $xml)
    {
        $currencies = new stdClass;
        $currencies->GetCurrenciesResult = $xml;
        $this->mock->shouldReceive('getCurrencies')
            ->once()
            ->andReturn($currencies);

        $this->expectException(MnbException::class);
        try {
            $this->client->currencies();
        } catch (MnbException $e) {
            $this->assertEquals($xml, $e->getXML());
            throw $e;
        }
    }

    public static function singleExchangeRateInvalidDate(): array
    {
        return [
            ['', '2025-04-22', 'EUR'],
            ['<MNBExchangeRates></MNBExchangeRates>', '2025-04-22', 'EUR'],
            ['<MNBExchangeRates><Day date="2025-04-22"></Day></MNBExchangeRates>', '2025-04-22', 'EUR'],
            ['<MNBExchangeRates><Day date="2025-04-22"><Rate unit="1" curr="EUR">409,24</Rate></Day></MNBExchangeRates', '2025-04-22', 'EUR'],
        ];
    }

    #[Test]
    #[DataProvider('singleExchangeRateInvalidDate')]
    public function single_exchange_rates_invalid_xml(string $xml, string $date, string $currency)
    {
        $exchangeRate = new stdClass;
        $exchangeRate->GetExchangeRatesResult = $xml;
        $this->mock->shouldReceive('GetExchangeRates')->with(['startDate' => $date, 'endDate' => $date, 'currencyNames' => $currency])
            ->once()
            ->andReturn($exchangeRate);

        $this->expectException(MnbException::class);
        try {
            $this->client->exchangeRate($currency, $date);
        } catch (MnbException $e) {
            $this->assertEquals($xml, $e->getXML());
            throw $e;
        }
    }

    #[Test]
    public function invalid_url()
    {
        $this->expectException(MnbException::class);
        $this->expectExceptionMessage('Failed to initialize SOAP API connection.');
        Config::set('mnb-exchange.wsdl', 'wrong');
        new Client;
    }
}
